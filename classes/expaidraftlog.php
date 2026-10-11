<?php
/**
 * File containing the expAiDraftLog class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * One version the assistant wrote (table expai_draft_log): the object and version, the user it was written for,
 * when, a summary of the request, the model, where it was written (panel or edit form) and what happened to it.
 * This is what the "Exponential Intelligence" label is drawn from.
 *
 * status: draft (written, still a draft), kept ("Save as drafts" pressed), pending (sent, waiting for approval),
 *         published, discarded
 * action: create (new object), update (new version of an existing object), field (a field action in the edit form)
 * source: panel, edit
 */
class expAiDraftLog extends eZPersistentObject
{
    const STATUS_DRAFT = 'draft';
    const STATUS_KEPT = 'kept';
    const STATUS_PENDING = 'pending';
    const STATUS_PUBLISHED = 'published';
    const STATUS_DISCARDED = 'discarded';

    public static function definition()
    {
        $int = function ( $name ) { return array( 'name' => $name, 'datatype' => 'integer', 'default' => 0, 'required' => true ); };
        $str = function ( $name, $default = '' ) { return array( 'name' => $name, 'datatype' => 'string', 'default' => $default, 'required' => true ); };
        return array(
            'fields' => array(
                'id' => $int( 'ID' ),
                'contentobject_id' => $int( 'ContentObjectID' ),
                'version' => $int( 'Version' ),
                'user_id' => $int( 'UserID' ),
                'node_id' => $int( 'NodeID' ),
                'parent_node_id' => $int( 'ParentNodeID' ),
                'conversation_id' => $int( 'ConversationID' ),
                'language_code' => $str( 'LanguageCode' ),
                'action' => $str( 'Action' ),
                'source' => $str( 'Source' ),
                'status' => $str( 'Status', self::STATUS_DRAFT ),
                'model' => $str( 'Model' ),
                'prompt_summary' => $str( 'PromptSummary' ),
                'created' => $int( 'Created' ),
                'modified' => $int( 'Modified' ),
            ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'function_attributes' => array( 'object' => 'contentObject', 'content_version' => 'contentVersion' ),
            'class_name' => 'expAiDraftLog',
            'sort' => array( 'id' => 'desc' ),
            'name' => 'expai_draft_log',
        );
    }

    /**
     * @param array $row
     * @return expAiDraftLog stored
     */
    public static function record( array $row )
    {
        $now = time();
        $row += array( 'created' => $now, 'modified' => $now, 'status' => self::STATUS_DRAFT );
        $summaryMax = expAiConfig::instance()->int( 'LogSettings', 'MaxPromptSummaryLength', 200, 20, 255 );
        $row['prompt_summary'] = expAiText::cut( isset( $row['prompt_summary'] ) ? $row['prompt_summary'] : '', $summaryMax );
        $row['model'] = expAiText::cut( isset( $row['model'] ) ? $row['model'] : '', 100 );
        $log = new self( $row );
        $log->store();
        return $log;
    }

    /** @return expAiDraftLog|null the newest entry of a version */
    public static function fetchByVersion( $objectID, $version )
    {
        $list = eZPersistentObject::fetchObjectList( self::definition(), null,
            array( 'contentobject_id' => (int) $objectID, 'version' => (int) $version ), array( 'id' => 'desc' ), array( 'offset' => 0, 'length' => 1 ) );
        return $list ? $list[0] : null;
    }

    /**
     * @param array $pairs list of array( objectID, version )
     * @return array "objectID:version" => expAiDraftLog (newest entry of each)
     */
    public static function fetchForVersions( array $pairs )
    {
        $result = array();
        $objectIDs = array();
        foreach ( $pairs as $pair )
            $objectIDs[(int) $pair[0]] = true;
        if ( !$objectIDs )
            return $result;
        $wanted = array();
        foreach ( $pairs as $pair )
            $wanted[(int) $pair[0] . ':' . (int) $pair[1]] = true;
        $list = eZPersistentObject::fetchObjectList( self::definition(), null,
            array( 'contentobject_id' => array( array_keys( $objectIDs ) ) ), array( 'id' => 'asc' ) );
        foreach ( $list as $log )
        {
            $key = $log->attribute( 'contentobject_id' ) . ':' . $log->attribute( 'version' );
            if ( isset( $wanted[$key] ) )
                $result[$key] = $log;
        }
        return $result;
    }

    /** @return expAiDraftLog[] the entries of a user on a node (newest first) */
    public static function fetchForUserNode( $userID, $nodeID, $limit = 50 )
    {
        return eZPersistentObject::fetchObjectList( self::definition(), null,
            array( 'user_id' => (int) $userID, 'node_id' => (int) $nodeID, 'source' => 'panel' ), array( 'id' => 'desc' ),
            array( 'offset' => 0, 'length' => (int) $limit ) );
    }

    /** @return expAiDraftLog[] */
    public static function fetchList( $userID = false, $offset = 0, $limit = 50 )
    {
        $conds = $userID !== false ? array( 'user_id' => (int) $userID ) : null;
        return eZPersistentObject::fetchObjectList( self::definition(), null, $conds, array( 'id' => 'desc' ),
            array( 'offset' => (int) $offset, 'length' => (int) $limit ) );
    }

    /** @return int */
    public static function fetchListCount( $userID = false )
    {
        $conds = $userID !== false ? array( 'user_id' => (int) $userID ) : null;
        return (int) eZPersistentObject::count( self::definition(), $conds );
    }

    /** @return eZContentObject|null */
    public function contentObject()
    {
        return eZContentObject::fetch( (int) $this->attribute( 'contentobject_id' ) );
    }

    /** @return eZContentObjectVersion|null */
    public function contentVersion()
    {
        return eZContentObjectVersion::fetchVersion( (int) $this->attribute( 'version' ), (int) $this->attribute( 'contentobject_id' ) );
    }

    public function setStatus( $status )
    {
        $this->setAttribute( 'status', $status );
        $this->setAttribute( 'modified', time() );
        $this->store();
    }
}
