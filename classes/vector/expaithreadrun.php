<?php
/**
 * File containing the expAiThreadRun class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * One agent run of a thread (table expai_thread_run). Every thread has a lead run (the conversation the editor talks
 * to); fan-out runs of an approved plan are children (parent_run_id, plan_item). Each run has its own
 * expai_conversation row: parallel runs cannot share one message list. job_uuid links a run to its expqueue job once
 * runs execute in the queue; in foreground mode (the browser drives the steps, as in the panel) it is empty.
 */
class expAiThreadRun extends eZPersistentObject
{
    public static function definition()
    {
        $int = function ( $name, $default = 0 ) { return array( 'name' => $name, 'datatype' => 'integer', 'default' => $default, 'required' => true ); };
        $str = function ( $name, $default = '' ) { return array( 'name' => $name, 'datatype' => 'string', 'default' => $default, 'required' => true ); };
        return array(
            'fields' => array(
                'id' => $int( 'ID' ),
                'thread_id' => $int( 'ThreadID' ),
                'parent_run_id' => $int( 'ParentRunID' ),
                'plan_item' => $int( 'PlanItem', -1 ),
                'job_uuid' => $str( 'JobUUID' ),
                'conversation_id' => $int( 'ConversationID' ),
                'kind' => $str( 'Kind', 'turn' ),
                'state' => $str( 'State', 'idle' ),
                'label' => $str( 'Label' ),
                'progress_done' => $int( 'ProgressDone' ),
                'progress_total' => $int( 'ProgressTotal' ),
                'model_alias' => $str( 'ModelAlias' ),
                'lane' => $str( 'Lane' ),
                'cost_micros' => $int( 'CostMicros' ),
                'tokens' => $int( 'Tokens' ),
                'started' => $int( 'Started' ),
                'finished' => $int( 'Finished' ),
                'created' => $int( 'Created' ),
                'modified' => $int( 'Modified' ),
            ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expAiThreadRun',
            'sort' => array( 'id' => 'asc' ),
            'name' => 'expai_thread_run',
        );
    }

    /** @return expAiThreadRun|null */
    public static function fetchByID( $id )
    {
        if ( !(int) $id )
            return null;
        $object = eZPersistentObject::fetchObject( self::definition(), null, array( 'id' => (int) $id ) );
        return $object instanceof self ? $object : null;
    }

    /** @return expAiThreadRun[] the runs of a thread, oldest first */
    public static function fetchForThread( $threadID )
    {
        return (array) eZPersistentObject::fetchObjectList( self::definition(), null, array( 'thread_id' => (int) $threadID ), array( 'id' => 'asc' ) );
    }

    /** @return expAiThreadRun|null the run of a conversation */
    public static function fetchForConversation( $conversationID )
    {
        $list = eZPersistentObject::fetchObjectList( self::definition(), null, array( 'conversation_id' => (int) $conversationID ), array( 'id' => 'asc' ),
                                                     array( 'offset' => 0, 'length' => 1 ) );
        return $list ? $list[0] : null;
    }
}
