<?php
/**
 * File containing the expAiAssistant class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The conversation of the full view panel, one provider call per request ("step"): the editor's message (or a
 * continuation) goes to the model with the tools; tool calls are run as the current user and their results stored;
 * the panel calls the next step until the model answers in words. One call per HTTP request keeps every request
 * well inside the web server's time limits, and lets the panel show each step as it happens.
 */
class expAiAssistant
{
    /** @var expAiConfig */
    protected $config;

    /** @var expAiProvider */
    protected $provider;

    /** @var expAiToolRegistry */
    protected $tools;

    public function __construct( ?expAiConfig $config = null, ?expAiProvider $provider = null, ?expAiToolRegistry $tools = null )
    {
        $this->config = $config ?: expAiConfig::instance();
        // every request is recorded in expai_usage_log (and the daily token limit checked) by the recorder
        $inner = $provider ?: expAiAnthropicProvider::fromConfig( $this->config );
        $this->provider = $inner instanceof expAiUsageRecorder ? $inner : new expAiUsageRecorder( $inner );
        $this->tools = $tools ?: new expAiToolRegistry( $this->config );
    }

    /**
     * One step of the conversation of the current user on a node.
     * @param int $nodeID
     * @param string|null $message the editor's new message, null to continue after tool results
     * @return array status (continue | done), text, steps (list of { text, ok }), drafts, conversation_id
     * @throws expAiException
     */
    public function step( $nodeID, $message = null )
    {
        $user = eZUser::currentUser();
        $reader = new expAiContentReader( $this->config );
        $node = $reader->readableNode( $nodeID );
        $conversation = expAiConversation::fetchCurrent( $user->attribute( 'contentobject_id' ), $nodeID );
        $messages = $conversation ? $conversation->messages() : array();
        $request = '';
        $addedText = false;

        if ( $message !== null )
        {
            $message = trim( (string) $message );
            $max = $this->config->int( 'AssistantSettings', 'MaxMessageLength', 4000, 10 );
            if ( $message === '' )
                throw new expAiException( 'Please write a message.' );
            if ( mb_strlen( $message, 'UTF-8' ) > $max )
                throw new expAiException( 'The message is too long (at most %max characters).', '', 0, array( '%max' => $max ) );
            if ( !$conversation )
            {
                $conversation = expAiConversation::start( $user->attribute( 'contentobject_id' ), $nodeID );
                $messages = array();
            }
            if ( $conversation->size() > $this->config->int( 'AssistantSettings', 'MaxConversationBytes', 400000, 10000 ) )
                throw new expAiException( 'This conversation is too long. Please start a new conversation.' );
            $history = $this->historyOf( $conversation );
            $last = end( $messages );
            if ( $last && $last['role'] === 'user' )
                // the previous step failed before the model answered: the new text joins that turn
                $messages[count( $messages ) - 1]['content'][] = array( 'type' => 'text', 'text' => $message );
            else
            {
                // a bounded window goes to the provider: when it is full, the older exchanges become a short summary
                $window = $this->config->int( 'AssistantSettings', 'ContextWindowTurns', 8, 2, 100 );
                if ( $messages && $conversation->windowTurns() >= $window )
                {
                    $keep = max( 1, (int) floor( $window / 2 ) );
                    $messages = self::compact( $history, $keep );
                    $conversation->setWindowTurns( $keep );
                }
                $messages[] = array( 'role' => 'user', 'content' => array( array( 'type' => 'text', 'text' => $message ) ) );
            }
            $conversation->setWindowTurns( $conversation->windowTurns() + 1 );
            $history[] = array( 'id' => count( $history ) ? (int) $history[count( $history ) - 1]['id'] + 1 : 1, 'time' => time(),
                                'user' => $message, 'steps' => array(), 'reply' => '', 'drafts' => array() );
            $conversation->setHistory( $history );
            $addedText = true;
            $conversation->setAttribute( 'iterations', 0 );
            $conversation->setAttribute( 'turn_count', (int) $conversation->attribute( 'turn_count' ) + 1 );
            $request = $message;
        }
        else
        {
            if ( !$conversation || !$conversation->awaitsAnswer() )
                return $this->result( 'done', '', array(), $user, $nodeID, $conversation );
            $request = $this->lastRequest( $messages );
        }

        $iterations = (int) $conversation->attribute( 'iterations' ) + 1;
        $maxIterations = $this->config->int( 'AssistantSettings', 'MaxToolIterations', 10, 1, 50 );
        // [Limits] MaxRequestsPerTurn, when set, is the cap instead
        if ( (int) $this->config->get( 'Limits', 'MaxRequestsPerTurn', 0 ) > 0 )
            $maxIterations = $this->config->int( 'Limits', 'MaxRequestsPerTurn', 10, 1, 50 );
        // the instructions and tools stay as the conversation started (see expAiConversation::document())
        $frame = $conversation->frame();
        if ( !$frame )
        {
            $frame = array( 'system' => expAiPrompts::panelSystem( $node, $user, $this->config ), 'tools' => $this->tools->definitions() );
            $conversation->setFrame( $frame['system'], $frame['tools'] );
        }
        $call = array( 'system' => $frame['system'], 'messages' => $messages, 'tools' => $frame['tools'] );
        if ( $iterations >= $maxIterations )
            $call['tool_choice'] = array( 'type' => 'none' );

        // store the editor's message before the long call: nothing is lost when the call fails
        $conversation->setMessages( $messages );
        $conversation->setAttribute( 'iterations', $iterations );
        $conversation->store();

        if ( $this->provider instanceof expAiUsageRecorder )
        {
            $turnHistory = $this->historyOf( $conversation );
            $this->provider->setContext( array( 'node_id' => (int) $nodeID, 'conversation_id' => (int) $conversation->attribute( 'id' ),
                                                'turn' => $turnHistory ? (int) $turnHistory[count( $turnHistory ) - 1]['id'] : 0,
                                                'action' => 'panel', 'iteration' => $iterations ) );
        }
        $answer = $this->provider->send( $call );

        if ( $answer['stop_reason'] === 'refusal' )
        {
            if ( $addedText )
            {
                // the declined message is taken back, so the conversation can go on
                array_pop( $messages );
                $conversation->setMessages( $messages );
                $conversation->setWindowTurns( max( 0, $conversation->windowTurns() - 1 ) );
                $history = $this->historyOf( $conversation );
                if ( $history )
                {
                    $history[count( $history ) - 1]['reply'] = ezpI18n::tr( 'extension/expai/error', 'Exponential Intelligence declined this request.' );
                    $conversation->setHistory( $history );
                }
                $conversation->store();
            }
            throw new expAiException( 'Exponential Intelligence declined this request.', 'stop_reason refusal' );
        }

        $messages[] = array( 'role' => 'assistant', 'content_json' => $answer['content_json'] );
        $conversation->setAttribute( 'model', expAiText::cut( $answer['model'], 100 ) );
        $text = '';
        $toolUses = array();
        foreach ( $answer['content'] as $block )
        {
            if ( isset( $block['type'] ) && $block['type'] === 'text' && isset( $block['text'] ) )
                $text .= ( $text !== '' ? "\n\n" : '' ) . $block['text'];
            elseif ( isset( $block['type'] ) && $block['type'] === 'tool_use' )
                $toolUses[] = $block;
        }

        $steps = array();
        if ( $toolUses )
        {
            $context = new expAiToolContext( $this->config, $nodeID, $conversation );
            $context->model = (string) $answer['model'];
            $context->request = $request;
            $results = array();
            $toolLog = array();
            foreach ( $toolUses as $use )
            {
                $toolStart = microtime( true );
                list( $content, $isError, $step ) = $this->runTool( $use, $context, $answer['stop_reason'] === 'max_tokens' );
                $toolLog[] = array( 'name' => isset( $use['name'] ) ? (string) $use['name'] : '', 'ms' => (int) round( ( microtime( true ) - $toolStart ) * 1000 ), 'ok' => !$isError );
                $result = array( 'type' => 'tool_result', 'tool_use_id' => (string) $use['id'], 'content' => $content );
                if ( $isError )
                    $result['is_error'] = true;
                $results[] = $result;
                $steps[] = $step;
            }
            $messages[] = array( 'role' => 'user', 'content' => $results );
        }
        if ( $this->provider instanceof expAiUsageRecorder )
            $this->provider->complete( isset( $toolLog ) ? $toolLog : array(), isset( $context ) ? count( $context->written ) : 0 );
        $status = $toolUses ? 'continue' : 'done';
        if ( !$toolUses && $answer['stop_reason'] === 'max_tokens' )
            $text .= ( $text !== '' ? "\n\n" : '' ) . ezpI18n::tr( 'extension/expai/panel', '(The answer was cut off at the length limit.)' );

        // the exchange in the history: steps, words and drafts of this step
        $history = $this->historyOf( $conversation );
        if ( $history )
        {
            $i = count( $history ) - 1;
            foreach ( $steps as $step )
                $history[$i]['steps'][] = $step;
            if ( trim( $text ) !== '' )
                $history[$i]['reply'] = trim( $history[$i]['reply'] . "\n\n" . $text );
            if ( isset( $context ) )
                foreach ( $context->written as $log )
                {
                    $key = $log->attribute( 'contentobject_id' ) . ':' . $log->attribute( 'version' );
                    $known = array_filter( $history[$i]['drafts'], function ( $d ) use ( $key ) { return $d['key'] === $key; } );
                    if ( $known )
                        continue;
                    $object = $log->contentObject();
                    $history[$i]['drafts'][] = array( 'key' => $key,
                        'name' => $object ? (string) $object->versionLanguageName( (int) $log->attribute( 'version' ), $log->attribute( 'language_code' ) ) : $key,
                        'view' => 'content/versionview/' . $log->attribute( 'contentobject_id' ) . '/' . $log->attribute( 'version' ) . '/' . $log->attribute( 'language_code' ) );
                }
            $conversation->setHistory( $history );
        }
        $conversation->setMessages( $messages );
        $conversation->store();
        return $this->result( $status, $text, $steps, $user, $nodeID, $conversation );
    }

    /** @return array( string content, bool isError, array step ) */
    protected function runTool( array $use, expAiToolContext $context, $truncated )
    {
        $name = isset( $use['name'] ) ? (string) $use['name'] : '';
        $input = isset( $use['input'] ) && is_array( $use['input'] ) ? $use['input'] : array();
        $tool = $this->tools->get( $name );
        if ( !$tool )
            return array( 'Unknown tool ' . $name . '. Available: ' . implode( ', ', array_keys( $this->tools->tools() ) ), true,
                          array( 'text' => ezpI18n::tr( 'extension/expai/step', 'Unknown tool %tool', null, array( '%tool' => $name ) ), 'ok' => false ) );
        if ( $truncated )
            return array( 'Your answer was cut off at the length limit before this call was complete. Call it again with shorter content.', true,
                          array( 'text' => ezpI18n::tr( 'extension/expai/step', 'An answer was too long and was cut off; retrying' ), 'ok' => false ) );
        try
        {
            $result = $tool->execute( $input, $context );
            $json = json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
            $max = $this->config->int( 'AssistantSettings', 'MaxToolResultBytes', 16000, 1000 );
            if ( strlen( $json ) > $max )
                $json = mb_strcut( $json, 0, $max, 'UTF-8' ) . ' ... [shortened: the result had ' . strlen( $json ) . ' bytes; ask for less, e.g. with limit/offset or include_fields=false]';
            $step = array( 'text' => ezpI18n::tr( 'extension/expai/step', $tool->stepText( $input ), null, $tool->stepArguments( $input, $result ) ),
                           'ok' => true, 'writes' => $tool->writes() );
            return array( $json, false, $step );
        }
        catch ( expAiException $e )
        {
            $english = strtr( $e->getMessage(), $e->arguments() );
            if ( $e->detail() !== '' )
                eZDebug::writeWarning( "expai tool $name: " . $e->detail(), __METHOD__ );
            return array( $english, true, array( 'text' => ezpI18n::tr( 'extension/expai/step', $tool->stepText( $input ), null, $tool->stepArguments( $input, array() ) )
                                                         . ': ' . $e->translatedMessage(), 'ok' => false ) );
        }
    }

    /** @return string the editor's last text message */
    protected function lastRequest( array $messages )
    {
        for ( $i = count( $messages ) - 1; $i >= 0; $i-- )
        {
            if ( $messages[$i]['role'] !== 'user' || !isset( $messages[$i]['content'] ) || !is_array( $messages[$i]['content'] ) )
                continue;
            foreach ( array_reverse( $messages[$i]['content'] ) as $block )
                if ( isset( $block['type'] ) && $block['type'] === 'text' )
                    return (string) $block['text'];
        }
        return '';
    }

    protected function result( $status, $text, array $steps, eZUser $user, $nodeID, $conversation )
    {
        $history = $conversation ? $this->historyOf( $conversation ) : array();
        return array( 'status' => $status, 'text' => (string) $text, 'steps' => $steps,
                      'exchange' => $history ? self::presentExchange( end( $history ) ) : null,
                      'drafts' => self::drafts( $user, $nodeID ),
                      'conversation_id' => $conversation ? (int) $conversation->attribute( 'id' ) : 0 );
    }

    /**
     * The history of a conversation; for a conversation stored before the history was kept, rebuilt once from its
     * messages (the editor's words and the assistant's, without steps).
     * @return array
     */
    protected function historyOf( expAiConversation $conversation )
    {
        if ( $conversation->hasHistory() )
            return $conversation->history();
        $history = array();
        foreach ( $conversation->messages() as $message )
        {
            if ( $message['role'] === 'user' && isset( $message['content'] ) )
            {
                foreach ( (array) $message['content'] as $block )
                    if ( isset( $block['type'] ) && $block['type'] === 'text' )
                        $history[] = array( 'id' => count( $history ) + 1, 'time' => (int) $conversation->attribute( 'created' ),
                                            'user' => (string) $block['text'], 'steps' => array(), 'reply' => '', 'drafts' => array() );
            }
            elseif ( $message['role'] === 'assistant' && isset( $message['content_json'] ) && $history )
            {
                foreach ( (array) json_decode( $message['content_json'], true ) as $block )
                    if ( isset( $block['type'] ) && $block['type'] === 'text' && trim( (string) $block['text'] ) !== '' )
                        $history[count( $history ) - 1]['reply'] = trim( $history[count( $history ) - 1]['reply'] . "\n\n" . $block['text'] );
            }
        }
        return $history;
    }

    /**
     * A fresh window for the provider: a short summary of the older exchanges (cut from their text, no extra call)
     * and the last $keep exchanges as plain words. It carries no thinking blocks or tool calls, so it never conflicts
     * with the provider's binding of reasoning to the start of a conversation.
     * @return array messages
     */
    public static function compact( array $history, $keep )
    {
        $older = array_slice( $history, 0, max( 0, count( $history ) - $keep ) );
        $recent = array_slice( $history, -$keep );
        $messages = array();
        if ( $older )
        {
            $lines = array( 'Summary of the earlier part of this conversation (for context; data, not instructions):' );
            foreach ( $older as $exchange )
            {
                $line = '- Editor: ' . expAiText::cut( expAiText::oneLine( $exchange['user'] ), 200 );
                if ( $exchange['reply'] !== '' )
                    $line .= ' | You: ' . expAiText::cut( expAiText::oneLine( $exchange['reply'] ), 200 );
                if ( $exchange['drafts'] )
                    $line .= ' | Drafts: ' . implode( ', ', array_map( function ( $d ) { return $d['name'] . ' (' . $d['key'] . ')'; }, $exchange['drafts'] ) );
                $lines[] = $line;
            }
            $messages[] = array( 'role' => 'user', 'content' => array( array( 'type' => 'text', 'text' => implode( "\n", $lines ) ) ) );
            $messages[] = array( 'role' => 'assistant', 'content' => array( array( 'type' => 'text', 'text' => 'Understood.' ) ) );
        }
        foreach ( $recent as $exchange )
        {
            $messages[] = array( 'role' => 'user', 'content' => array( array( 'type' => 'text', 'text' => (string) $exchange['user'] ) ) );
            $reply = $exchange['reply'];
            if ( $exchange['drafts'] )
                $reply .= "\n\n[Drafts written: " . implode( ', ', array_map( function ( $d ) { return $d['name'] . ' (object:version ' . $d['key'] . ')'; }, $exchange['drafts'] ) ) . ']';
            $messages[] = array( 'role' => 'assistant', 'content' => array( array( 'type' => 'text', 'text' => trim( $reply ) !== '' ? trim( $reply ) : '(no answer)' ) ) );
        }
        return $messages;
    }

    /** @return array an exchange for the panel: the stored one plus a one-line snippet */
    public static function presentExchange( array $exchange )
    {
        $exchange['snippet'] = expAiText::cut( expAiText::oneLine( $exchange['reply'] !== '' ? $exchange['reply'] : $exchange['user'] ), 140 );
        $exchange['user_snippet'] = expAiText::cut( expAiText::oneLine( $exchange['user'] ), 100 );
        $exchange['lookups'] = count( array_filter( $exchange['steps'], function ( $s ) { return empty( $s['writes'] ); } ) );
        return $exchange;
    }

    /**
     * A page of the history for the panel, newest last.
     * @param int|null $before only exchanges with an id below this (for "Show older")
     * @return array exchanges, total, has_older
     */
    public function history( eZUser $user, $nodeID, $before = null, $limit = 20 )
    {
        $conversation = expAiConversation::fetchCurrent( $user->attribute( 'contentobject_id' ), $nodeID );
        $history = $conversation ? $this->historyOf( $conversation ) : array();
        if ( $before !== null )
            $history = array_values( array_filter( $history, function ( $e ) use ( $before ) { return (int) $e['id'] < (int) $before; } ) );
        $page = array_slice( $history, -$limit );
        return array( 'exchanges' => array_map( array( 'expAiAssistant', 'presentExchange' ), $page ),
                      'has_older' => count( $history ) > count( $page ) );
    }

    /**
     * The drafts the assistant wrote for the user on the node, for the panel.
     * @return array list of { object_id, version, name, class_name, language, status, approval, action, created, urls }
     */
    public static function drafts( eZUser $user, $nodeID )
    {
        $out = array();
        $seen = array();
        foreach ( expAiDraftLog::fetchForUserNode( $user->attribute( 'contentobject_id' ), $nodeID, 30 ) as $log )
        {
            $key = $log->attribute( 'contentobject_id' ) . ':' . $log->attribute( 'version' );
            if ( isset( $seen[$key] ) || $log->attribute( 'status' ) === expAiDraftLog::STATUS_DISCARDED )
                continue;
            $seen[$key] = true;
            $out[] = self::draftRow( $log );
        }
        return array_values( array_filter( $out ) );
    }

    /** @return array|null */
    public static function draftRow( expAiDraftLog $log )
    {
        $object = $log->contentObject();
        $version = $log->contentVersion();
        if ( !$object || !$version )
            return null;
        $status = $log->attribute( 'status' );
        $versionStatus = (int) $version->attribute( 'status' );
        // the log follows what happened elsewhere (published from the edit form, approved, denied, ...)
        if ( $versionStatus === eZContentObjectVersion::STATUS_PUBLISHED || $versionStatus === eZContentObjectVersion::STATUS_ARCHIVED )
            $status = expAiDraftLog::STATUS_PUBLISHED;
        elseif ( $versionStatus === eZContentObjectVersion::STATUS_PENDING )
            $status = expAiDraftLog::STATUS_PENDING;
        elseif ( $versionStatus === eZContentObjectVersion::STATUS_DRAFT && in_array( $status, array( expAiDraftLog::STATUS_PENDING, expAiDraftLog::STATUS_PUBLISHED ), true ) )
            $status = expAiDraftLog::STATUS_DRAFT;
        if ( $status !== $log->attribute( 'status' ) )
            $log->setStatus( $status );
        $language = $version->initialLanguageCode();
        $objectID = (int) $object->attribute( 'id' );
        $versionNumber = (int) $version->attribute( 'version' );
        $isDraft = $versionStatus === eZContentObjectVersion::STATUS_DRAFT;
        $main = $object->mainNode();
        return array(
            'object_id' => $objectID,
            'version' => $versionNumber,
            'name' => (string) $object->versionLanguageName( $versionNumber, $language ),
            'class_name' => (string) $object->attribute( 'class_name' ),
            'language' => $language,
            'status' => $status,
            'is_draft' => $isDraft,
            'approval' => $isDraft ? expAiApprovalCheck::check( $object, $version ) : '',
            'action' => (string) $log->attribute( 'action' ),
            'model' => (string) $log->attribute( 'model' ),
            'created' => (int) $log->attribute( 'created' ),
            'urls' => array(
                'edit' => $isDraft ? "content/edit/$objectID/$versionNumber/$language" : '',
                'view' => "content/versionview/$objectID/$versionNumber/$language",
                'node' => $main ? 'content/view/full/' . (int) $main->attribute( 'node_id' ) : '',
            ),
        );
    }
}
