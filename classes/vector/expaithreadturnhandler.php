<?php
/**
 * File containing the expAiThreadTurnHandler class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The job type expai.thread.turn: one turn of a thread (the editor's message and every step until the model answers
 * in words) run by the queue worker instead of the browser. The worker runs it as the job's user (the thread's owner);
 * the steps are the same as in the browser (expAiAssistant::step(): one model call, the tools as the user, the
 * conversation stored before each call), and every step writes the thread's events, so the browser follows it by
 * polling or the event stream.
 *
 * Not expai.request: that job type is one model call without tools; a turn is a loop of calls with tool runs between.
 *
 * Payload: thread_id, run_id, node_id, message ('' to continue an interrupted turn), options (alias, read_only, mode,
 * scope_note, action). A rerun after a crash never sends the message twice: once the conversation holds it, the turn
 * goes on with "continue". Stop (expaivector::stop) cancels the job: honored between two steps.
 */
class expAiThreadTurnHandler extends expQueueHandlerBase
{
    public function validate( array $payload )
    {
        if ( empty( $payload['thread_id'] ) || empty( $payload['node_id'] ) )
            return 'thread_id and node_id are required';
        return null;
    }

    public function describe( expQueueJob $job )
    {
        $p = $job->payloadArray();
        return 'Thread turn ' . ( isset( $p['thread_id'] ) ? (int) $p['thread_id'] : 0 ) . ( !empty( $p['options']['alias'] ) ? ' (' . $p['options']['alias'] . ')' : '' );
    }

    public function run( expQueueJob $job, expQueueContext $context )
    {
        $p = $job->payloadArray();
        $thread = expAiThread::fetchByID( (int) $p['thread_id'] );
        if ( !$thread )
            throw new expQueuePermanentException( 'the thread is gone', 'thread' );
        $user = eZUser::currentUser();
        // the worker runs the job as its user: it must still be the owner, with the policies of the run
        if ( !$thread->isOwner( $user->attribute( 'contentobject_id' ) ) || !expAiServerFunctions::hasPolicy( 'write' )
             || ( $thread->attribute( 'origin' ) !== expAiThread::ORIGIN_PANEL && !expAiThreadAccess::hasVector( $user ) ) )
        {
            self::finish( $thread, 'failed', ezpI18n::tr( 'extension/expai/vector', 'The owner of this thread no longer has access to it.' ) );
            throw new expQueuePermanentException( 'the owner may not run the thread any more', 'access' );
        }
        $conversation = $thread->conversation();
        if ( !$conversation )
            throw new expQueuePermanentException( 'the thread has no conversation', 'thread' );
        $config = expAiConfig::instance();
        $options = isset( $p['options'] ) && is_array( $p['options'] ) ? $p['options'] : array();
        $message = isset( $p['message'] ) ? (string) $p['message'] : '';
        $checkpoint = $context->checkpoint();
        // a rerun: the message is in the conversation already
        if ( is_array( $checkpoint ) && !empty( $checkpoint['sent'] ) )
            $message = '';
        $max = $config->int( 'AssistantSettings', 'MaxToolIterations', 10, 1, 50 ) + 2;
        $steps = 0;
        $status = 'continue';
        while ( $status === 'continue' && $steps < $max )
        {
            if ( $context->isCancelled() )
            {
                self::finish( expAiThread::fetchByID( (int) $thread->attribute( 'id' ) ), 'stopped' );
                throw new expQueueCancelledException( 'stopped by the editor' );
            }
            $assistant = ( new expAiAssistant( $config ) )->useConversation( $conversation, $options );
            try
            {
                if ( $message !== '' )
                {
                    $context->saveCheckpoint( array( 'sent' => 1 ) );
                    $result = $assistant->step( (int) $p['node_id'], $message );
                    $message = '';
                }
                else
                    $result = $assistant->step( (int) $p['node_id'], null );
            }
            catch ( expAiException $e )
            {
                // the step marked the thread failed with the reason; the editor decides whether to try again
                throw new expQueuePermanentException( strtr( $e->getMessage(), $e->arguments() ), 'model' );
            }
            $steps++;
            $status = isset( $result['status'] ) ? (string) $result['status'] : 'done';
            $last = $result['steps'] ? end( $result['steps'] ) : null;
            $context->progress( $steps, null, $last && isset( $last['text'] ) ? (string) $last['text'] : '' );
            // the conversation was stored by the step: read it fresh for the next one
            $conversation = $thread->conversation();
        }
        return array( 'steps' => $steps, 'status' => $status );
    }

    /** The thread and its lead run end in a state (stopped, failed) with an optional error event */
    public static function finish( $thread, $state, $error = '' )
    {
        if ( !$thread instanceof expAiThread )
            return;
        if ( $error !== '' )
            $thread->addEvent( 'error', array( 'message' => $error ) );
        $thread->setState( $state );
        $run = $thread->leadRun();
        if ( $run )
        {
            $run->setAttribute( 'state', $state );
            $run->setAttribute( 'finished', time() );
            $run->store();
        }
        $thread->touch();
        $thread->store();
    }
}
