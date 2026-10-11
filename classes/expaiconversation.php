<?php
/**
 * File containing the expAiConversation class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The conversation of one user on one node (table expai_conversation). messages is the JSON list sent to the
 * provider; assistant turns are kept exactly as received (content_json) and the list is only ever appended to.
 * A table rather than the session: a long turn would otherwise be overwritten by the session write of any page the
 * editor opens in the meantime.
 */
class expAiConversation extends eZPersistentObject
{
    public static function definition()
    {
        $int = function ( $name ) { return array( 'name' => $name, 'datatype' => 'integer', 'default' => 0, 'required' => true ); };
        return array(
            'fields' => array(
                'id' => $int( 'ID' ),
                'user_id' => $int( 'UserID' ),
                'node_id' => $int( 'NodeID' ),
                'messages' => array( 'name' => 'Messages', 'datatype' => 'string', 'default' => '[]', 'required' => true ),
                'turn_count' => $int( 'TurnCount' ),
                'iterations' => $int( 'Iterations' ),
                'draft_count' => $int( 'DraftCount' ),
                'model' => array( 'name' => 'Model', 'datatype' => 'string', 'default' => '', 'required' => true ),
                'created' => $int( 'Created' ),
                'modified' => $int( 'Modified' ),
            ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expAiConversation',
            'sort' => array( 'id' => 'desc' ),
            'name' => 'expai_conversation',
        );
    }

    /** @return expAiConversation|null the current conversation of the user on the node (not expired) */
    public static function fetchCurrent( $userID, $nodeID )
    {
        $list = eZPersistentObject::fetchObjectList( self::definition(), null,
            array( 'user_id' => (int) $userID, 'node_id' => (int) $nodeID ), array( 'id' => 'desc' ), array( 'offset' => 0, 'length' => 1 ) );
        if ( !$list )
            return null;
        $lifetime = expAiConfig::instance()->int( 'AssistantSettings', 'ConversationLifetime', 604800, 60 );
        if ( $list[0]->attribute( 'modified' ) < time() - $lifetime )
            return null;
        return $list[0];
    }

    /** @return expAiConversation a new, stored conversation */
    public static function start( $userID, $nodeID )
    {
        $now = time();
        $conversation = new self( array( 'user_id' => (int) $userID, 'node_id' => (int) $nodeID, 'messages' => '[]',
                                         'created' => $now, 'modified' => $now ) );
        $conversation->store();
        return $conversation;
    }

    /** Removes every conversation of the user on the node ("New conversation"); the draft log stays */
    public static function removeFor( $userID, $nodeID )
    {
        eZPersistentObject::removeObject( self::definition(), array( 'user_id' => (int) $userID, 'node_id' => (int) $nodeID ) );
    }

    /**
     * The stored document: { system, tools_json, messages }. A conversation keeps the instructions and the tool
     * definitions it started with: the provider binds the model's thinking to the exact start of the conversation,
     * so a renamed node or a changed tool list would otherwise break every conversation already under way.
     * (A plain list is the messages of an older conversation, without a frame.)
     * @return array
     */
    protected function document()
    {
        $data = json_decode( (string) $this->attribute( 'messages' ), true );
        if ( !is_array( $data ) )
            return array( 'messages' => array() );
        if ( array_key_exists( 'messages', $data ) )
            return $data;
        return array( 'messages' => $data );
    }

    protected function storeDocument( array $document )
    {
        $this->setAttribute( 'messages', json_encode( $document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE ) );
        $this->setAttribute( 'modified', time() );
    }

    /** @return array the stored messages: role, and content (user) or content_json (assistant) */
    public function messages()
    {
        $document = $this->document();
        return is_array( $document['messages'] ) ? $document['messages'] : array();
    }

    public function setMessages( array $messages )
    {
        $document = $this->document();
        $document['messages'] = $messages;
        $this->storeDocument( $document );
    }

    /**
     * The instructions and tools the conversation started with.
     * @return array|null system (string), tools (list, decoded so that empty JSON objects stay objects)
     */
    public function frame()
    {
        $document = $this->document();
        if ( !isset( $document['system'], $document['tools_json'] ) )
            return null;
        return array( 'system' => (string) $document['system'], 'tools' => json_decode( (string) $document['tools_json'] ) );
    }

    public function setFrame( $system, array $tools )
    {
        $document = $this->document();
        $document['system'] = (string) $system;
        $document['tools_json'] = json_encode( $tools, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $this->storeDocument( $document );
    }

    /**
     * The history the panel shows: one entry per exchange (the editor's message and everything that followed it),
     * kept apart from the messages sent to the provider, which are a bounded window (see expAiAssistant::compact()).
     * @return array list of { id, time, user, steps (list of { text, ok, writes }), reply, drafts (list of { key, name, view }) }
     */
    public function history()
    {
        $document = $this->document();
        return isset( $document['history'] ) && is_array( $document['history'] ) ? $document['history'] : array();
    }

    public function setHistory( array $history )
    {
        $document = $this->document();
        // the panel shows the last 200 exchanges at most
        $document['history'] = array_slice( $history, -200 );
        $this->storeDocument( $document );
    }

    /** @return bool the history is kept (a conversation from before 0.1.0's history has none) */
    public function hasHistory()
    {
        $document = $this->document();
        return isset( $document['history'] );
    }

    /** @return int exchanges in the window sent to the provider */
    public function windowTurns()
    {
        $document = $this->document();
        return isset( $document['window_turns'] ) ? (int) $document['window_turns'] : (int) $this->attribute( 'turn_count' );
    }

    public function setWindowTurns( $turns )
    {
        $document = $this->document();
        $document['window_turns'] = (int) $turns;
        $this->storeDocument( $document );
    }

    /** @return int bytes of the messages sent to the provider */
    public function size()
    {
        // the window sent to the provider, not the history
        return strlen( (string) json_encode( $this->messages() ) );
    }

    /** @return bool the last message is the user's (text or tool results): the assistant has to answer */
    public function awaitsAnswer()
    {
        $messages = $this->messages();
        $last = end( $messages );
        return $last && $last['role'] === 'user';
    }
}
