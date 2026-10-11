<?php
/**
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * update_draft: writes fields into a draft of an existing object: the draft this conversation wrote for it when
 * there is one, else a new version (content/edit of the user). Never publishes.
 */
class expAiToolUpdateDraft extends expAiTool
{
    public function writes()
    {
        return true;
    }

    public function description()
    {
        return 'Changes an existing object by writing fields into a DRAFT: the draft you created or changed earlier in this '
             . 'conversation, or else a new draft version (the published version stays as it is until the editor publishes). '
             . 'Only the fields you give are changed. Same value formats as create_draft. In a thread with staged review it stages '
             . 'a proposal for the editor\'s review instead (no draft yet); calling it again for the same object replaces the proposal.';
    }

    public function inputSchema()
    {
        return array( 'type' => 'object', 'properties' => array(
            'object_id' => array( 'type' => 'integer', 'description' => 'The object to change' ),
            'fields' => array( 'type' => 'object', 'description' => 'field identifier => value',
                               'additionalProperties' => array( 'type' => array( 'string', 'number', 'boolean', 'array' ) ) ),
            'language' => array( 'type' => 'string', 'description' => 'Language code (default: the object\'s main language)' ),
        ), 'required' => array( 'object_id', 'fields' ), 'additionalProperties' => false );
    }

    public function execute( array $input, expAiToolContext $context )
    {
        if ( !isset( $input['fields'] ) || !is_array( $input['fields'] ) || !$input['fields'] )
            throw new expAiException( 'Give the fields to write.' );
        $object = eZContentObject::fetch( isset( $input['object_id'] ) ? (int) $input['object_id'] : 0 );
        if ( !$object instanceof eZContentObject || !$object->canRead() )
            throw new expAiException( 'Object %id does not exist or you may not read it.', '', 0, array( '%id' => isset( $input['object_id'] ) ? (int) $input['object_id'] : 0 ) );
        $context->reader->assertAllowed( $object );
        // staged review (Vector): a proposal per field for the editor's review instead of a draft
        $stager = expAiChangeStager::forContext( $context );
        $this->staged = (bool) $stager;
        if ( $stager )
            return $stager->stageUpdate( $object, isset( $input['language'] ) ? (string) $input['language'] : '', $input['fields'] );
        $reuse = 0;
        foreach ( expAiDraftLog::fetchForUserNode( $context->user->attribute( 'contentobject_id' ), $context->nodeID ) as $log )
        {
            if ( (int) $log->attribute( 'contentobject_id' ) === (int) $object->attribute( 'id' )
                 && in_array( $log->attribute( 'status' ), array( expAiDraftLog::STATUS_DRAFT, expAiDraftLog::STATUS_KEPT ), true ) )
            {
                $reuse = (int) $log->attribute( 'version' );
                break;
            }
        }
        $language = isset( $input['language'] ) ? (string) $input['language'] : '';
        list( $version, $language, $created ) = $context->writer->draftFor( $object, $language, $reuse );
        if ( $created )
            $context->checkDraftLimit();
        $written = $context->writer->writeFields( $object, $version, $language, $input['fields'] );
        $mainNode = $object->mainNode();
        if ( $created || !expAiDraftLog::fetchByVersion( $object->attribute( 'id' ), $version->attribute( 'version' ) ) )
        {
            $log = expAiDraftLog::record( array(
                'contentobject_id' => (int) $object->attribute( 'id' ), 'version' => (int) $version->attribute( 'version' ),
                'user_id' => (int) $context->user->attribute( 'contentobject_id' ), 'node_id' => $context->nodeID,
                'parent_node_id' => $mainNode ? (int) $mainNode->attribute( 'parent_node_id' ) : 0, 'language_code' => $language,
                'conversation_id' => $context->conversation ? (int) $context->conversation->attribute( 'id' ) : 0,
                'action' => 'update', 'source' => 'panel', 'model' => $context->model, 'provider' => $context->provider, 'prompt_summary' => $context->request ) );
            $context->addWritten( $log );
        }
        else
        {
            $log = expAiDraftLog::fetchByVersion( $object->attribute( 'id' ), $version->attribute( 'version' ) );
            $log->setAttribute( 'modified', time() );
            $log->store();
            $context->written[] = $log;
        }
        return array( 'status' => 'draft written (not published)', 'object_id' => (int) $object->attribute( 'id' ),
                      'version' => (int) $version->attribute( 'version' ), 'language' => $language, 'new_version' => $created,
                      'name' => (string) $object->versionLanguageName( (int) $version->attribute( 'version' ), $language ),
                      'written' => $written['written'], 'skipped' => $written['skipped'], 'errors' => $written['errors'] );
    }

    /** @var bool the last call staged a proposal (staged review) */
    protected $staged = false;

    public function stepText( array $input )
    {
        return $this->staged ? 'Proposed changes to "%name" for review' : 'Wrote the draft "%name"';
    }

    public function stepArguments( array $input, $result )
    {
        return array( '%name' => isset( $result['name'] ) ? $result['name'] : '' );
    }
}
