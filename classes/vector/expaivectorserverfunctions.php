<?php
/**
 * File containing the expAiVectorServerFunctions class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The ezjscore functions of threads, Vector and the keyboard (ezjscore.ini [ezjscServer_expaivector]; the caller needs
 * ezjscore/call with the function list "expai", as for expai::*):
 *
 *   POST <root>/ezjscore/call  ezjscServer_function_arguments=expaivector::<function>, ezxform_token, ...
 *
 *   threads, thread, history, events, nodes, palette, keybindings     (policy expai/write)
 *   bootstrap, thread_save, turn, stop, followup                      (policies expai/write and expai/vector)
 *
 * Every function checks POST, the form token and the policies itself. A thread is read only by its owner and by the
 * approvers of a version it wrote; only the owner changes or runs it (expAiThreadAccess). Failures are translated
 * expAiException messages (error_text). Nothing here is cached.
 */
class expAiVectorServerFunctions extends ezjscServerFunctions
{
    /** @return string */
    protected static function post( $name, $default = '' )
    {
        return isset( $_POST[$name] ) && is_scalar( $_POST[$name] ) ? (string) $_POST[$name] : $default;
    }

    /**
     * POST, form token, expai/write (and expai/vector), the thread tables.
     * @return expAiConfig
     */
    protected static function guard( $vector = false, $needsProvider = false )
    {
        if ( !isset( $_SERVER['REQUEST_METHOD'] ) || strtoupper( $_SERVER['REQUEST_METHOD'] ) !== 'POST' )
            throw ( new expAiException( 'Exponential Intelligence only accepts POST requests.' ) )->translated();
        if ( class_exists( 'ezxFormToken' ) && ezxFormToken::isEnabled() )
        {
            $given = self::post( 'ezxform_token', isset( $_SERVER['HTTP_X_CSRF_TOKEN'] ) ? (string) $_SERVER['HTTP_X_CSRF_TOKEN'] : '' );
            if ( $given === '' || !hash_equals( (string) ezxFormToken::getToken(), $given ) )
                throw ( new expAiException( 'The form token is missing or wrong. Please reload the page.' ) )->translated();
        }
        $config = expAiConfig::instance();
        if ( $needsProvider && !$config->isEnabled() )
            throw ( new expAiException( 'Exponential Intelligence is switched off.' ) )->translated();
        if ( $needsProvider && !$config->isConfigured() )
            throw ( new expAiException( 'Exponential Intelligence is not configured correctly.', 'Model or ApiKey missing' ) )->translated();
        if ( !expAiServerFunctions::hasPolicy( 'write' ) )
            throw ( new expAiException( 'You do not have access to Exponential Intelligence.' ) )->translated();
        if ( $vector && ( !$config->flag( 'VectorSettings', 'Enabled', true ) || !expAiThreadAccess::hasVector() ) )
            throw ( new expAiException( 'You do not have access to Vector.' ) )->translated();
        if ( !expAiThread::available() )
            throw ( new expAiException( 'The thread tables are missing. Please ask an administrator to update the database of Exponential Intelligence.' ) )->translated();
        return $config;
    }

    /**
     * Ends the session lock at once: these functions only read the session (user, form token), and PHP keeps the
     * session locked for the whole request, so a poll or a long foreground turn would otherwise hold up every other
     * request of the same browser (measured: 4-6 s on Apache and on Velocity).
     */
    protected static function releaseSession()
    {
        if ( function_exists( 'session_status' ) && session_status() === PHP_SESSION_ACTIVE )
            session_write_close();
    }

    /**
     * The next poll interval in milliseconds: [VectorSettings] PollActiveMs while the open thread runs,
     * PollWorkingMs while another thread works, PollIdleMs otherwise; doubled while the host is busy (load above
     * twice the number of processors). The browser does not poll while its tab is hidden.
     */
    public static function nextPoll( expAiConfig $config, $openRunning, $otherRunning )
    {
        if ( $openRunning )
            $ms = $config->int( 'VectorSettings', 'PollActiveMs', 1000, 500, 60000 );
        elseif ( $otherRunning )
            $ms = $config->int( 'VectorSettings', 'PollWorkingMs', 3000, 500, 60000 );
        else
            $ms = $config->int( 'VectorSettings', 'PollIdleMs', 15000, 1000, 300000 );
        if ( function_exists( 'sys_getloadavg' ) )
        {
            $load = sys_getloadavg();
            $cpus = 0;
            if ( is_readable( '/proc/cpuinfo' ) )
                $cpus = substr_count( (string) @file_get_contents( '/proc/cpuinfo' ), "\nprocessor" ) + 1;
            if ( $load && $cpus > 0 && $load[0] > $cpus * 2 )
                $ms *= 2;
        }
        return $ms;
    }

    protected static function run( $fn )
    {
        try
        {
            return $fn();
        }
        catch ( expAiException $e )
        {
            throw $e->translated();
        }
    }

    /** @return int */
    protected static function userID()
    {
        return (int) eZUser::currentUser()->attribute( 'contentobject_id' );
    }

    /**
     * The thread named by POST "thread" (a UUID) that the user may read; $write: that the user owns.
     * @return expAiThread
     */
    protected static function loadThread( $write = false )
    {
        $thread = expAiThread::fetchByUuid( self::post( 'thread' ) );
        // the same answer for "missing" and "not yours": another editor's thread ids reveal nothing
        if ( !$thread || !expAiThreadAccess::canRead( $thread ) || ( $write && !expAiThreadAccess::canWrite( $thread ) ) )
            throw new expAiException( 'This thread does not exist or you may not open it.' );
        return $thread;
    }

    /** @return array a JSON list from POST */
    protected static function postJson( $name )
    {
        $data = json_decode( self::post( $name, '' ), true );
        return is_array( $data ) ? $data : array();
    }

    /* ------------------------------------------------------------------ lists */

    /**
     * threads: the user's threads. shelf=active (default: active and pinned) | settled | archived, search (title, at
     * least 2 characters), node (threads on that node). Also the threads shared for approval and the counts.
     */
    public static function threads( $args )
    {
        self::guard();
        self::releaseSession();
        return self::run( function ()
        {
            $userID = self::userID();
            $shelf = self::post( 'shelf', 'active' );
            $filters = array( 'shelf' => $shelf === 'settled' || $shelf === 'archived' ? $shelf : array( 'active', 'pinned' ),
                              'search' => mb_substr( self::post( 'search' ), 0, 100, 'UTF-8' ) );
            if ( (int) self::post( 'node', '0' ) > 0 )
                $filters['node_id'] = (int) self::post( 'node' );
            if ( (int) self::post( 'limit', '0' ) > 0 )
                $filters['limit'] = (int) self::post( 'limit' );
            $rows = array();
            foreach ( expAiThread::fetchForUser( $userID, $filters ) as $thread )
                $rows[] = expAiVector::listRow( $thread, $userID );
            $shared = array();
            if ( self::post( 'shared', '1' ) === '1' && empty( $filters['node_id'] ) )
                foreach ( expAiThreadAccess::sharedWithMe() as $thread )
                    $shared[] = expAiVector::listRow( $thread, $userID );
            return array( 'threads' => $rows, 'shared' => $shared,
                          'counts' => array( 'settled' => expAiThread::countForUser( $userID, array( 'shelf' => 'settled' ) ),
                                             'archived' => expAiThread::countForUser( $userID, array( 'shelf' => 'archived' ) ) ),
                          'has_vector' => expAiVector::allowed() );
        } );
    }

    /** thread: one thread (header, scope, last exchanges, drafts, follow-ups, cost); the owner's unread mark is cleared */
    public static function thread( $args )
    {
        $config = self::guard();
        self::releaseSession();
        return self::run( function () use ( $config )
        {
            $thread = self::loadThread();
            $userID = self::userID();
            $conversation = $thread->conversation();
            $history = array( 'exchanges' => array(), 'has_older' => false );
            if ( $conversation )
                $history = ( new expAiAssistant( $config ) )->useConversation( $conversation )->history( eZUser::currentUser(), (int) $thread->attribute( 'node_id' ) );
            if ( $thread->isOwner( $userID ) && (int) $thread->attribute( 'seen_event_id' ) < (int) $thread->attribute( 'last_event_id' ) )
            {
                eZPersistentObject::updateObjectList( array( 'definition' => expAiThread::definition(),
                                                             'update_fields' => array( 'seen_event_id' => (int) $thread->attribute( 'last_event_id' ) ),
                                                             'conditions' => array( 'id' => (int) $thread->attribute( 'id' ) ) ) );
                $thread->setAttribute( 'seen_event_id', (int) $thread->attribute( 'last_event_id' ) );
            }
            $followups = array();
            foreach ( expAiFollowup::waiting( (int) $thread->attribute( 'id' ) ) as $followup )
                $followups[] = $followup->present();
            return array( 'thread' => expAiVector::detail( $thread, $userID, $config ), 'history' => $history,
                          'drafts' => expAiVector::drafts( $thread ), 'followups' => $followups,
                          'pending' => $conversation ? $conversation->awaitsAnswer() : false,
                          'lanes' => expAiVector::lanes( (string) $thread->attribute( 'model_alias' ) ?: $config->effectiveModel(), $config ) );
        } );
    }

    /** history: older exchanges of a thread (thread, before) */
    public static function history( $args )
    {
        $config = self::guard();
        self::releaseSession();
        return self::run( function () use ( $config )
        {
            $thread = self::loadThread();
            $conversation = $thread->conversation();
            if ( !$conversation )
                return array( 'exchanges' => array(), 'has_older' => false );
            $before = (int) self::post( 'before', '0' );
            return ( new expAiAssistant( $config ) )->useConversation( $conversation )
                ->history( eZUser::currentUser(), (int) $thread->attribute( 'node_id' ), $before > 0 ? $before : null );
        } );
    }

    /**
     * events: the events of threads after a cursor. threads=uuid,uuid (at most 20), after={"<uuid>": <event id>}.
     * Read only; the answer suggests the next poll interval (next_ms). Streaming of steps (background runs) builds on
     * this; foreground runs return their steps in the turn's answer as well.
     */
    public static function events( $args )
    {
        $config = self::guard();
        self::releaseSession();
        return self::run( function () use ( $config )
        {
            $after = self::postJson( 'after' );
            $out = array( 'threads' => array(), 'events' => array() );
            $openRunning = false;
            $running = false;
            // open=<uuid>: the thread the editor looks at (the fast interval applies to it alone)
            $open = self::post( 'open' );
            foreach ( array_slice( array_filter( explode( ',', self::post( 'threads' ) ) ), 0, 20 ) as $uuid )
            {
                $thread = expAiThread::fetchByUuid( $uuid );
                if ( !$thread || !expAiThreadAccess::canRead( $thread ) )
                    continue;
                $cursor = isset( $after[$uuid] ) ? (int) $after[$uuid] : 0;
                $out['threads'][] = array( 'uuid' => $uuid, 'state' => (string) $thread->attribute( 'state' ), 'last_event_id' => (int) $thread->attribute( 'last_event_id' ),
                                           'cost' => $thread->costText(), 'modified' => (int) $thread->attribute( 'modified' ) );
                if ( in_array( $thread->attribute( 'state' ), array( 'queued', 'running' ), true ) )
                {
                    if ( $uuid === $open )
                        $openRunning = true;
                    else
                        $running = true;
                }
                if ( $cursor < (int) $thread->attribute( 'last_event_id' ) )
                    foreach ( expAiThreadEvent::fetchAfter( (int) $thread->attribute( 'id' ), $cursor, 200 ) as $event )
                        $out['events'][] = array( 'thread' => $uuid ) + $event->present();
            }
            $out['next_ms'] = self::nextPoll( $config, $openRunning, $running );
            return $out;
        } );
    }

    /**
     * nodes: content the user may read whose name contains q (at least 2 characters), or the node with the id q. For
     * the scope picker and the palette's "Jump to node". At most 12.
     */
    public static function nodes( $args )
    {
        self::guard();
        self::releaseSession();
        return self::run( function ()
        {
            $q = trim( mb_substr( self::post( 'q' ), 0, 100, 'UTF-8' ) );
            $found = array();
            if ( ctype_digit( $q ) )
            {
                $node = eZContentObjectTreeNode::fetch( (int) $q );
                if ( $node instanceof eZContentObjectTreeNode && $node->canRead() )
                    $found[] = $node;
            }
            if ( mb_strlen( $q, 'UTF-8' ) >= 2 )
            {
                $like = str_replace( array( '*', '%', '_' ), ' ', $q );
                $list = eZContentObjectTreeNode::subTreeByNodeID( array( 'AttributeFilter' => array( array( 'name', 'like', '*' . $like . '*' ) ),
                                                                         'Limit' => 12, 'SortBy' => array( 'modified', false ), 'IgnoreVisibility' => true ), 1 );
                foreach ( (array) $list as $node )
                    $found[] = $node;
            }
            $out = array();
            $seen = array();
            foreach ( $found as $node )
            {
                $id = (int) $node->attribute( 'node_id' );
                if ( isset( $seen[$id] ) || count( $out ) >= 12 )
                    continue;
                $seen[$id] = true;
                $parent = $node->fetchParent();
                $out[] = array( 'id' => $id, 'name' => (string) $node->attribute( 'name' ), 'class' => (string) $node->attribute( 'class_name' ),
                                'parent' => $parent && $parent->canRead() ? (string) $parent->attribute( 'name' ) : '',
                                'url' => 'content/view/full/' . $id );
            }
            return array( 'nodes' => $out );
        } );
    }

    /**
     * palette: what the command palette needs on any admin page (it is loaded on the first Ctrl/Cmd+K): its script,
     * texts, the user's key bindings, the models, and whether Vector is open to the user.
     */
    public static function palette( $args )
    {
        $config = self::guard();
        self::releaseSession();
        return self::run( function () use ( $config )
        {
            $scripts = array();
            try
            {
                $scripts = ezjscPacker::buildJavascriptFiles( array( 'expai_palette.js' ), (int) eZINI::instance( 'ezjscore.ini' )->variable( 'eZJSCore', 'Packer' ) );
            }
            catch ( Exception $e )
            {
                eZDebug::writeWarning( 'expai: the palette script could not be packed: ' . $e->getMessage(), __METHOD__ );
            }
            return array( 'scripts' => array_values( (array) $scripts ), 'strings' => expAiVectorStrings::palette(), 'labels' => expAiVectorStrings::commands(),
                          'bindings' => expAiVector::bindings(), 'has_vector' => expAiVector::allowed(),
                          'models' => $config->modelChoiceEnabled() ? expAiVector::models( $config ) : null,
                          'user_overrides' => $config->flag( 'KeyBindings', 'UserOverrides', true ),
                          'global' => $config->flag( 'KeyBindings', 'GlobalPalette', true ) );
        } );
    }

    /** keybindings: bindings={"command": "keys", ...} stores the user's shortcuts ('' resets one), reset=1 resets all */
    public static function keybindings( $args )
    {
        self::guard();
        return self::run( function ()
        {
            if ( self::post( 'reset' ) === '1' )
            {
                eZPreferences::setValue( 'expai_keybindings', '' );
                return array( 'bindings' => expAiVector::bindings() );
            }
            return array( 'bindings' => expAiVector::saveBindings( self::postJson( 'bindings' ) ) );
        } );
    }

    /* ----------------------------------------------------------------- Vector */

    /** bootstrap: the choices of the Vector header: models, classes, languages, scope kinds and review policies allowed */
    public static function bootstrap( $args )
    {
        $config = self::guard( true );
        self::releaseSession();
        return self::run( function () use ( $config )
        {
            return array( 'models' => expAiVector::models( $config ), 'classes' => expAiVector::classes(), 'languages' => expAiVector::languages(),
                          'choices' => expAiThreadAccess::choices(), 'bindings' => expAiVector::bindings(), 'labels' => expAiVectorStrings::commands(),
                          'lanes' => expAiVector::lanes( $config->effectiveModel(), $config ),
                          'model_choice' => $config->modelChoiceEnabled(),
                          'defaults' => array( 'run_mode' => $config->get( 'VectorSettings', 'DefaultMode', 'write' ) === 'plan' ? 'plan' : 'write',
                                               'review_policy' => $config->get( 'VectorSettings', 'DefaultReview', 'staged' ) === 'direct' ? 'direct' : 'staged' ),
                          'transport' => expAiVectorStream::transport( $config ), 'poll' => array( 'active' => self::nextPoll( $config, true, false ), 'working' => self::nextPoll( $config, false, true ),
                                           'idle' => self::nextPoll( $config, false, false ) ) );
        } );
    }

    /**
     * thread_save: creates a thread (no "thread") or changes one of the user's: title, scope (JSON), run_mode, review_policy,
     * model_alias, lane, shelf, position. A new thread takes its scope from "node" when no scope is given. Rules: the
     * scope must pass expai/vector (Subtree, Section, Scope) and be readable; bulk scopes run in plan mode until a plan
     * is approved; direct review only for one object in one language.
     */
    public static function thread_save( $args )
    {
        $config = self::guard( true );
        self::releaseSession();
        return self::run( function () use ( $config )
        {
            $userID = self::userID();
            $isNew = self::post( 'thread' ) === '';
            if ( $isNew )
            {
                $scope = self::postJson( 'scope' );
                if ( !$scope && (int) self::post( 'node', '0' ) > 0 )
                    $scope = array( 'nodes' => array( (int) self::post( 'node' ) ) );
                $scope = expAiThread::cleanScope( $scope );
                $bulk = expAiThread::scopeIsBulk( $scope, $config );
                $review = $config->get( 'VectorSettings', 'DefaultReview', 'staged' ) === 'direct' && !$bulk ? 'direct' : 'staged';
                if ( !in_array( $review, expAiThreadAccess::choices()['review'], true ) )
                    $review = expAiThreadAccess::choices()['review'] ? expAiThreadAccess::choices()['review'][0] : 'staged';
                $reason = expAiThreadAccess::check( $scope, $review );
                if ( $reason !== '' )
                    throw new expAiException( $reason );
                $mode = $bulk || $config->get( 'VectorSettings', 'DefaultMode', 'write' ) === 'plan' ? 'plan' : 'write';
                $thread = expAiThread::create( array( 'user_id' => $userID, 'origin' => expAiThread::ORIGIN_VECTOR, 'scope' => $scope,
                                                      'run_mode' => $mode, 'review_policy' => $review, 'model_alias' => $config->effectiveModel(),
                                                      'lane' => 'auto', 'title' => mb_substr( trim( self::post( 'title' ) ), 0, 255, 'UTF-8' ) ) );
                return array( 'thread' => expAiVector::detail( $thread, $userID, $config ), 'created' => true );
            }
            $thread = self::loadThread( true );
            $notes = array();
            $scope = $thread->scope();
            if ( isset( $_POST['scope'] ) )
                $scope = expAiThread::cleanScope( self::postJson( 'scope' ) );
            $review = isset( $_POST['review_policy'] ) && in_array( self::post( 'review_policy' ), array( 'staged', 'direct' ), true )
                    ? self::post( 'review_policy' ) : (string) $thread->attribute( 'review_policy' );
            $bulk = expAiThread::scopeIsBulk( $scope, $config );
            if ( $review === 'direct' && $bulk && $thread->attribute( 'origin' ) !== expAiThread::ORIGIN_PANEL )
            {
                $review = 'staged';
                $notes[] = ezpI18n::tr( 'extension/expai/vector', 'Bulk work is always reviewed before it is written.' );
            }
            if ( isset( $_POST['scope'] ) || isset( $_POST['review_policy'] ) )
            {
                $reason = expAiThreadAccess::check( $scope, $review );
                if ( $reason !== '' )
                    throw new expAiException( $reason );
                $thread->setScope( $scope );
                $thread->setAttribute( 'review_policy', $review );
            }
            $mode = isset( $_POST['run_mode'] ) && in_array( self::post( 'run_mode' ), array( 'plan', 'write' ), true ) ? self::post( 'run_mode' ) : (string) $thread->attribute( 'run_mode' );
            if ( $mode === 'write' && $bulk && !(int) $thread->attribute( 'plan_id' ) )
            {
                $mode = 'plan';
                $notes[] = ezpI18n::tr( 'extension/expai/vector', 'Bulk work starts with a plan: approve it before anything is written.' );
            }
            $thread->setAttribute( 'run_mode', $mode );
            if ( isset( $_POST['model_alias'] ) )
            {
                $alias = $config->registry()->resolveAlias( self::post( 'model_alias' ) ) ?: self::post( 'model_alias' );
                if ( !expAiVector::mayUseModel( $alias, $config ) )
                    throw new expAiException( 'This model is not available.' );
                $thread->setAttribute( 'model_alias', $alias );
                $model = $config->registry()->getModel( $alias );
                $thread->setAttribute( 'provider', $model ? (string) $model['provider'] : '' );
            }
            if ( isset( $_POST['lane'] ) )
            {
                $lane = self::post( 'lane' );
                $names = array_map( function ( $l ) { return $l['name']; }, expAiVector::lanes( (string) $thread->attribute( 'model_alias' ) ?: $config->effectiveModel(), $config ) );
                if ( !in_array( $lane, $names, true ) )
                    throw new expAiException( 'This lane is not available for the model.' );
                $thread->setAttribute( 'lane', $lane );
            }
            if ( isset( $_POST['title'] ) )
                $thread->setAttribute( 'title', mb_substr( trim( self::post( 'title' ) ), 0, 255, 'UTF-8' ) );
            if ( isset( $_POST['shelf'] ) && in_array( self::post( 'shelf' ), expAiThread::$shelves, true ) )
                $thread->setAttribute( 'shelf', self::post( 'shelf' ) );
            if ( isset( $_POST['position'] ) )
                $thread->setAttribute( 'position', max( 0, min( 1000000, (int) self::post( 'position' ) ) ) );
            $thread->touch();
            $thread->store();
            return array( 'thread' => expAiVector::detail( $thread, $userID, $config ), 'notes' => $notes,
                          'lanes' => expAiVector::lanes( (string) $thread->attribute( 'model_alias' ) ?: $config->effectiveModel(), $config ) );
        } );
    }

    /**
     * turn: one step of the thread's lead run in foreground mode (the browser calls the next step while status is
     * "continue", as the panel does): thread, message (a new message) or continue=1. Runs as the thread's owner with
     * the thread's model. Write tools only in write mode with direct review on one object in one language; plan mode
     * and staged review read only. Background runs in the queue replace this for bulk work.
     */
    public static function turn( $args )
    {
        $config = self::guard( true, true );
        self::releaseSession();
        return self::run( function () use ( $config )
        {
            $thread = self::loadThread( true );
            $scope = $thread->scope();
            $review = (string) $thread->attribute( 'review_policy' );
            $reason = expAiThreadAccess::check( $scope, $review );
            if ( $reason !== '' )
                throw new expAiException( $reason );
            $nodeID = $scope['nodes'] ? $scope['nodes'][0] : ( $scope['subtrees'] ? $scope['subtrees'][0] : 0 );
            if ( !$nodeID )
                throw new expAiException( 'Choose the scope first: a node or a subtree.' );
            $alias = (string) $thread->attribute( 'model_alias' );
            if ( $alias !== '' && !expAiVector::mayUseModel( $alias, $config ) )
                throw new expAiException( 'The model of this thread is no longer available to you. Choose another model.' );
            $bulk = $thread->isBulk( $config );
            $mode = (string) $thread->attribute( 'run_mode' );
            $readOnly = $mode === 'plan' || ( $review === 'staged' && $thread->attribute( 'origin' ) !== expAiThread::ORIGIN_PANEL ) || $bulk;
            $conversation = $thread->conversation();
            if ( !$conversation )
                throw new expAiException( 'This thread has no conversation any more.' );
            $settings = $config->providerSettings();
            $timeout = max( 1, min( 600, (int) ( isset( $settings['Timeout'] ) && $settings['Timeout'] !== '' ? $settings['Timeout'] : $config->int( 'AISettings', 'Timeout', 115, 1, 600 ) ) ) );
            if ( function_exists( 'set_time_limit' ) )
                @set_time_limit( $timeout + 30 );
            ignore_user_abort( true );
            // a settled or archived thread that gets a new message is active again
            if ( self::post( 'continue' ) !== '1' && in_array( $thread->attribute( 'shelf' ), array( 'settled', 'archived' ), true ) )
            {
                $thread->setAttribute( 'shelf', 'active' );
                $thread->store();
            }
            if ( self::post( 'continue' ) !== '1' )
            {
                $thread->setState( 'running' );
                $thread->touch();
                $thread->store();
            }
            $assistant = ( new expAiAssistant( $config ) )->useConversation( $conversation, array(
                'alias' => $alias, 'read_only' => $readOnly, 'mode' => $mode,
                'scope_note' => $thread->attribute( 'origin' ) === expAiThread::ORIGIN_PANEL ? '' : expAiThreadPrompts::scopeNote( $scope ),
                'action' => $thread->attribute( 'origin' ) === expAiThread::ORIGIN_PANEL ? 'panel' : 'vector' ) );
            $result = $assistant->step( $nodeID, self::post( 'continue' ) === '1' ? null : self::post( 'message' ) );
            $fresh = expAiThread::fetchByID( (int) $thread->attribute( 'id' ) );
            $result['thread'] = expAiVector::detail( $fresh ?: $thread, self::userID(), $config );
            $result['drafts'] = expAiVector::drafts( $fresh ?: $thread );
            $result['read_only'] = $readOnly;
            return $result;
        } );
    }

    /**
     * stop: stops the thread's runs. In foreground mode the browser stops calling the next step; the state says
     * "stopped". (Background runs: honored at the next call boundary.)
     */
    public static function stop( $args )
    {
        $config = self::guard( true );
        self::releaseSession();
        return self::run( function () use ( $config )
        {
            $thread = self::loadThread( true );
            if ( in_array( $thread->attribute( 'state' ), array( 'queued', 'running', 'waiting_input' ), true ) )
            {
                $thread->setState( 'stopped' );
                $run = $thread->leadRun();
                if ( $run )
                {
                    $run->setAttribute( 'state', 'stopped' );
                    $run->setAttribute( 'finished', time() );
                    $run->store();
                }
                $thread->touch();
                $thread->store();
            }
            return array( 'thread' => expAiVector::detail( $thread, self::userID(), $config ) );
        } );
    }

    /**
     * followup: messages queued while a turn runs. op=add (body, delivery=queue|steer), drop (id), take (the first
     * waiting one, marked sent: the browser sends it as the next message), list.
     */
    public static function followup( $args )
    {
        $config = self::guard( true );
        self::releaseSession();
        return self::run( function () use ( $config )
        {
            $thread = self::loadThread( true );
            $threadID = (int) $thread->attribute( 'id' );
            $op = self::post( 'op', 'list' );
            $taken = null;
            if ( $op === 'add' )
            {
                $body = trim( self::post( 'body' ) );
                $max = $config->int( 'AssistantSettings', 'MaxMessageLength', 4000, 10 );
                if ( $body === '' )
                    throw new expAiException( 'Please write a message.' );
                if ( mb_strlen( $body, 'UTF-8' ) > $max )
                    throw new expAiException( 'The message is too long (at most %max characters).', '', 0, array( '%max' => $max ) );
                $waiting = expAiFollowup::waiting( $threadID );
                if ( count( $waiting ) >= 20 )
                    throw new expAiException( 'At most 20 messages can wait.' );
                $now = time();
                $followup = new expAiFollowup( array( 'thread_id' => $threadID, 'body' => $body, 'position' => count( $waiting ) ? (int) end( $waiting )->attribute( 'position' ) + 1 : 1,
                                                      'delivery' => self::post( 'delivery' ) === 'steer' ? 'steer' : 'queue', 'state' => 'waiting',
                                                      'created' => $now, 'modified' => $now ) );
                $followup->store();
                $thread->addEvent( 'followup', array( 'op' => 'add', 'id' => (int) $followup->attribute( 'id' ) ) );
                $thread->store();
            }
            elseif ( $op === 'drop' || $op === 'take' )
            {
                $followup = $op === 'take' ? current( expAiFollowup::waiting( $threadID ) ) : expAiFollowup::fetchByID( (int) self::post( 'id' ) );
                if ( $followup && (int) $followup->attribute( 'thread_id' ) === $threadID && $followup->attribute( 'state' ) === 'waiting' )
                {
                    $followup->setAttribute( 'state', $op === 'take' ? 'sent' : 'dropped' );
                    $followup->setAttribute( 'modified', time() );
                    $followup->store();
                    if ( $op === 'take' )
                        $taken = $followup->present();
                }
            }
            $list = array();
            foreach ( expAiFollowup::waiting( $threadID ) as $followup )
                $list[] = $followup->present();
            return array( 'followups' => $list, 'taken' => $taken );
        } );
    }

    /** No caching */
    public static function getCacheTime( $functionName )
    {
        return 0;
    }
}
