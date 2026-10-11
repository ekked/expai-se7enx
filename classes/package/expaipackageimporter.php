<?php
/**
 * File containing the expAiPackageImporter class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * Imports a package as drafts, as the current user (the user's content/create and content/edit decide; nothing is
 * published here: publishing is a person's step through content/publish, where approval workflows apply).
 *
 * Per object (by remote id):
 *   create   the remote id is new: one draft (version 1) with every translation of the package, below the parent of
 *            its main location (a parent that is another object of the package must be published first: until then
 *            the object waits, action wait_parent, and a second import creates it)
 *   update   the object exists with the same class: one draft per translation of the package
 *   conflict the object exists although the package expected a new one; it was modified after the export the package
 *            was made from; its content changed since (expected.content_hash). on_conflict: skip (default), draft (a
 *            draft is made anyway and marked as a conflict, for a person to merge) or fail (nothing is imported)
 *   error    the remote id belongs to an object of another class, an unknown class, an attribute the class lacks ...
 *
 * Options: dry_run (plan only), on_conflict, remap (a prefix: every remote id of the package's own objects becomes
 * md5(prefix:remote id), so a package can be imported a second time as copies; "expected" is then ignored), parent (a
 * node id, node remote id or path: the parent of every object whose parent is not in the package), languages (only
 * these translations), package_uuid and job_uuid (for expai_draft_log).
 */
class expAiPackageImporter
{
    /** @var expAiPackage */
    protected $package;

    /** @var array */
    protected $options;

    /** @var array remote id => index of the package's objects */
    protected $index = array();

    /** @var string[] the warnings of the object being imported */
    protected $warnings = array();

    /** @var string */
    protected $tempDir = '';

    public function __construct( expAiPackage $package, array $options = array() )
    {
        $this->package = $package;
        $this->options = $options + array( 'dry_run' => false, 'on_conflict' => 'skip', 'remap' => '', 'parent' => '', 'languages' => array(),
                                           'package_uuid' => '', 'job_uuid' => '' );
        if ( !in_array( $this->options['on_conflict'], array( 'skip', 'draft', 'fail' ), true ) )
            $this->options['on_conflict'] = 'skip';
        foreach ( $package->objects() as $i => $object )
            if ( isset( $object['remote_id'] ) && is_string( $object['remote_id'] ) )
                $this->index[$object['remote_id']] = $i;
    }

    public function __destruct()
    {
        if ( $this->tempDir !== '' && is_dir( $this->tempDir ) )
            eZDir::recursiveDelete( $this->tempDir );
    }

    /** @return string the remote id an object of the package gets in this installation */
    public function target( $remoteID )
    {
        $remoteID = (string) $remoteID;
        if ( $this->options['remap'] !== '' && isset( $this->index[$remoteID] ) )
            return md5( $this->options['remap'] . ':' . $remoteID );
        return $remoteID;
    }

    // ---- the import context of expAiPackageDatatypes -----------------------------------------------------------

    /** @return int the id of the object with a (package) remote id, 0 when none */
    public function objectId( $remoteID )
    {
        $object = eZContentObject::fetchByRemoteID( $this->target( $remoteID ) );
        return $object ? (int) $object->attribute( 'id' ) : 0;
    }

    /** @return int the id of the node with a remote id, 0 when none */
    public function nodeId( $remoteID )
    {
        $node = eZContentObjectTreeNode::fetchByRemoteID( (string) $remoteID );
        return $node ? (int) $node->attribute( 'node_id' ) : 0;
    }

    /** @return int the id of a URL in ezurl (registered when it is new; never in a dry run) */
    public function urlId( $url )
    {
        return $this->options['dry_run'] ? 1 : (int) eZURL::registerURL( (string) $url );
    }

    /** @return string|null */
    public function filePath( $rel )
    {
        return $this->package->filePath( $rel );
    }

    /** @return string a copy of a package file named $name in a directory of this import (the datatypes take the name from it) */
    public function tempCopy( $source, $name )
    {
        if ( $this->tempDir === '' )
        {
            $this->tempDir = eZSys::varDirectory() . '/expai/import-' . bin2hex( random_bytes( 8 ) );
            eZDir::mkdir( $this->tempDir, 0770, true );
        }
        $dir = $this->tempDir . '/' . bin2hex( random_bytes( 4 ) );
        eZDir::mkdir( $dir, 0770, true );
        copy( $source, $dir . '/' . $name );
        return $dir . '/' . $name;
    }

    public function warn( $text )
    {
        $this->warnings[] = (string) $text;
    }

    // ---- plan ------------------------------------------------------------------------------------------------------

    /**
     * What an import would do with one object.
     * @return array index, remote_id, target, class, action (create | update | conflict | error | wait_parent),
     *               reasons, languages, object_id, parent_node_id
     */
    public function planObject( $i )
    {
        $objects = $this->package->objects();
        $item = isset( $objects[$i] ) ? $objects[$i] : array();
        $rid = isset( $item['remote_id'] ) ? (string) $item['remote_id'] : '';
        $plan = array( 'index' => (int) $i, 'remote_id' => $rid, 'target' => $this->target( $rid ),
                       'class' => isset( $item['class_identifier'] ) ? (string) $item['class_identifier'] : '', 'action' => 'error',
                       'reasons' => array(), 'languages' => $this->languagesOf( $item ), 'object_id' => 0, 'parent_node_id' => 0 );
        $class = eZContentClass::fetchByIdentifier( $plan['class'] );
        if ( !$class )
        {
            $plan['reasons'][] = 'unknown class ' . $plan['class'];
            return $plan;
        }
        if ( !$plan['languages'] )
        {
            $plan['reasons'][] = 'no translation to import';
            return $plan;
        }
        $existing = eZContentObject::fetchByRemoteID( $plan['target'] );
        if ( $existing )
        {
            $plan['object_id'] = (int) $existing->attribute( 'id' );
            if ( (string) $existing->attribute( 'class_identifier' ) !== $plan['class'] )
            {
                $plan['reasons'][] = 'the remote id belongs to an object of the class ' . $existing->attribute( 'class_identifier' );
                return $plan;
            }
            if ( (int) $existing->attribute( 'status' ) !== eZContentObject::STATUS_PUBLISHED )
            {
                $plan['action'] = 'wait_parent';
                $plan['reasons'][] = 'the object exists as a draft only (an earlier import): publish or remove that draft first';
                return $plan;
            }
            $plan['action'] = 'update';
            $plan['reasons'] = $this->conflicts( $item, $existing );
            if ( $plan['reasons'] )
                $plan['action'] = 'conflict';
            return $plan;
        }
        $parent = $this->parentNode( $item, $plan );
        if ( $parent === null )
            return $plan;
        $plan['parent_node_id'] = (int) $parent->attribute( 'node_id' );
        $plan['action'] = 'create';
        if ( !empty( $item['expected']['exists'] ) && $this->options['remap'] === '' )
            $plan['reasons'][] = 'the package expected the object to exist; it is created again';
        return $plan;
    }

    /** @return array the plan of every object */
    public function plan()
    {
        $out = array();
        foreach ( array_keys( $this->package->objects() ) as $i )
            $out[] = $this->planObject( $i );
        return $out;
    }

    /** @return string[] the languages of an object that are imported */
    protected function languagesOf( array $item )
    {
        $languages = isset( $item['translations'] ) && is_array( $item['translations'] ) ? array_keys( $item['translations'] ) : array();
        if ( $this->options['languages'] )
            $languages = array_values( array_intersect( $languages, (array) $this->options['languages'] ) );
        // the main language first
        $initial = isset( $item['initial_language'] ) ? (string) $item['initial_language'] : '';
        usort( $languages, function ( $a, $b ) use ( $initial ) { return (int) ( $b === $initial ) - (int) ( $a === $initial ); } );
        return $languages;
    }

    /** @return string[] the conflicts of an existing object (empty: none) */
    protected function conflicts( array $item, eZContentObject $existing )
    {
        if ( $this->options['remap'] !== '' )
            return array();
        $reasons = array();
        $expected = isset( $item['expected'] ) && is_array( $item['expected'] ) ? $item['expected'] : array();
        if ( array_key_exists( 'exists', $expected ) && !$expected['exists'] )
            $reasons[] = 'the package expected a new object, but the remote id exists';
        if ( !empty( $expected['modified'] ) && (int) $existing->attribute( 'modified' ) > (int) $expected['modified'] )
            $reasons[] = 'the object was modified on ' . date( 'Y-m-d H:i', (int) $existing->attribute( 'modified' ) ) . ', after the export the package was made from';
        if ( !empty( $expected['content_hash'] ) )
        {
            $exporter = new expAiPackageExporter( array( 'hash_only' => true, 'languages' => array_keys( (array) $item['translations'] ) ) );
            $live = $exporter->object( $existing );
            if ( $live['expected']['content_hash'] !== (string) $expected['content_hash'] && !in_array( 'modified', $reasons, true ) )
                $reasons[] = 'the content of the object changed since the export';
        }
        return array_values( array_unique( $reasons ) );
    }

    /**
     * The parent node of a new object (its main location).
     * @return eZContentObjectTreeNode|null null: the plan says why
     */
    protected function parentNode( array $item, array &$plan )
    {
        $locations = isset( $item['locations'] ) && is_array( $item['locations'] ) ? $item['locations'] : array();
        $main = null;
        foreach ( $locations as $location )
            if ( !empty( $location['main'] ) )
                $main = $location;
        $main = $main ?: ( $locations ? reset( $locations ) : null );
        $parent = is_array( $main ) && isset( $main['parent'] ) && is_array( $main['parent'] ) ? $main['parent'] : array();
        // a parent that is an object of the package
        if ( isset( $parent['object_remote_id'] ) && isset( $this->index[(string) $parent['object_remote_id']] ) )
        {
            $object = eZContentObject::fetchByRemoteID( $this->target( $parent['object_remote_id'] ) );
            $node = $object ? $object->mainNode() : null;
            if ( !$node )
            {
                $plan['action'] = 'wait_parent';
                $plan['reasons'][] = 'its parent ' . $parent['object_remote_id'] . ' (in this package) is not published yet: import again after publishing it';
                return null;
            }
            return $this->checkCreate( $node, $plan );
        }
        if ( (string) $this->options['parent'] !== '' )
        {
            $node = self::resolveNode( (string) $this->options['parent'] );
            if ( !$node )
            {
                $plan['reasons'][] = 'the parent ' . $this->options['parent'] . ' does not exist';
                return null;
            }
            return $this->checkCreate( $node, $plan );
        }
        $node = null;
        if ( isset( $parent['object_remote_id'] ) )
        {
            $object = eZContentObject::fetchByRemoteID( (string) $parent['object_remote_id'] );
            $node = $object ? $object->mainNode() : null;
        }
        if ( !$node && !empty( $parent['node_remote_id'] ) )
            $node = eZContentObjectTreeNode::fetchByRemoteID( (string) $parent['node_remote_id'] );
        if ( !$node && !empty( $parent['path'] ) )
            $node = self::resolveNode( (string) $parent['path'] );
        if ( !$node )
        {
            $plan['reasons'][] = 'its parent cannot be found (name a parent with --parent)';
            return null;
        }
        return $this->checkCreate( $node, $plan );
    }

    /** @return eZContentObjectTreeNode|null the node of a node id, node remote id or url path */
    public static function resolveNode( $ref )
    {
        $ref = trim( (string) $ref );
        if ( ctype_digit( $ref ) )
            return eZContentObjectTreeNode::fetch( (int) $ref ) ?: null;
        if ( preg_match( '/^[A-Za-z0-9_-]{8,100}$/', $ref ) && ( $node = eZContentObjectTreeNode::fetchByRemoteID( $ref ) ) )
            return $node;
        $node = eZContentObjectTreeNode::fetchByURLPath( trim( $ref, '/' ) );
        return $node instanceof eZContentObjectTreeNode ? $node : null;
    }

    /** @return eZContentObjectTreeNode|null the node when the user may create the object below it */
    protected function checkCreate( eZContentObjectTreeNode $node, array &$plan )
    {
        $class = eZContentClass::fetchByIdentifier( $plan['class'] );
        $language = $plan['languages'] ? $plan['languages'][0] : false;
        if ( !$node->canRead() || $node->checkAccess( 'create', $class->attribute( 'id' ), $node->object()->attribute( 'contentclass_id' ), false, $language ) != 1 )
        {
            $plan['reasons'][] = 'you may not create ' . $plan['class'] . ' objects in ' . $language . ' below node ' . (int) $node->attribute( 'node_id' );
            return null;
        }
        return $node;
    }

    // ---- import ----------------------------------------------------------------------------------------------------

    /**
     * Imports every object (in package order).
     * @return array plan (when nothing was imported because of on_conflict=fail or dry_run), results, counts
     */
    public function run()
    {
        $plan = $this->plan();
        $counts = array();
        foreach ( $plan as $p )
            $counts[$p['action']] = ( isset( $counts[$p['action']] ) ? $counts[$p['action']] : 0 ) + 1;
        if ( $this->options['dry_run'] || ( $this->options['on_conflict'] === 'fail' && ( !empty( $counts['conflict'] ) || !empty( $counts['error'] ) ) ) )
            return array( 'plan' => $plan, 'results' => array(), 'counts' => $counts, 'imported' => false );
        $results = array();
        foreach ( array_keys( $this->package->objects() ) as $i )
            $results[] = $this->importObject( $i );
        $counts = array();
        foreach ( $results as $r )
            $counts[$r['action']] = ( isset( $counts[$r['action']] ) ? $counts[$r['action']] : 0 ) + 1;
        return array( 'plan' => $plan, 'results' => $results, 'counts' => $counts, 'imported' => true );
    }

    /**
     * Imports one object (the plan is made again: an earlier object of the package may have changed it).
     * @return array the plan with drafts (object_id, version, language, url), warnings, errors
     */
    public function importObject( $i )
    {
        $this->warnings = array();
        $plan = $this->planObject( $i );
        $plan += array( 'drafts' => array(), 'warnings' => array(), 'errors' => array(), 'conflict' => false );
        $objects = $this->package->objects();
        $item = $objects[$i];
        if ( $plan['action'] === 'conflict' )
        {
            if ( $this->options['on_conflict'] !== 'draft' )
            {
                $plan['action'] = 'skipped';
                return $plan;
            }
            $plan['conflict'] = true;
            $plan['action'] = 'update';
        }
        if ( !in_array( $plan['action'], array( 'create', 'update' ), true ) || $this->options['dry_run'] )
            return $plan;
        try
        {
            if ( $plan['action'] === 'create' )
                $plan['drafts'] = $this->create( $item, $plan );
            else
                $plan['drafts'] = $this->update( $item, $plan );
        }
        catch ( expAiException $e )
        {
            $plan['errors'][] = strtr( $e->getMessage(), $e->arguments() ) . ( $e->detail() !== '' ? ' (' . $e->detail() . ')' : '' );
            $plan['action'] = 'error';
        }
        foreach ( $plan['drafts'] as $draft )
            foreach ( $draft['errors'] as $field => $error )
                $plan['errors'][] = $draft['language'] . ' ' . $field . ': ' . $error;
        $plan['warnings'] = array_values( array_unique( $this->warnings ) );
        if ( $plan['drafts'] && $plan['errors'] )
            $plan['action'] .= '_with_errors';
        return $plan;
    }

    /** @return array[] the drafts of a new object (one: version 1 with every translation) */
    protected function create( array $item, array $plan )
    {
        $class = eZContentClass::fetchByIdentifier( $plan['class'] );
        $parent = eZContentObjectTreeNode::fetch( (int) $plan['parent_node_id'] );
        $languages = $plan['languages'];
        $initial = $languages[0];
        $user = eZUser::currentUser();
        $db = eZDB::instance();
        $db->begin();
        $object = $class->instantiate( (int) $user->attribute( 'contentobject_id' ), (int) $parent->object()->attribute( 'section_id' ),
                                       false, $initial, eZContentObjectVersion::STATUS_DRAFT );
        if ( !$object instanceof eZContentObject )
        {
            $db->rollback();
            throw new expAiException( 'The draft could not be created.', 'instantiate failed' );
        }
        $object->setAttribute( 'remote_id', $plan['target'] );
        if ( !empty( $item['always_available'] ) )
            $object->setAlwaysAvailableLanguageID( eZContentLanguage::idByLocale( $initial ) );
        $object->store();
        $first = true;
        foreach ( isset( $item['locations'] ) ? (array) $item['locations'] : array( array( 'main' => true ) ) as $location )
        {
            $isMain = $first;
            $parentID = $first ? (int) $parent->attribute( 'node_id' ) : 0;
            if ( !$first )
            {
                $p = array( 'action' => 'create', 'class' => $plan['class'], 'languages' => $languages, 'reasons' => array() );
                $node = $this->parentNode( array( 'locations' => array( $location + array( 'main' => true ) ) ), $p );
                $parentID = $node ? (int) $node->attribute( 'node_id' ) : 0;
                if ( !$parentID || $parentID === (int) $parent->attribute( 'node_id' ) )
                {
                    if ( !$parentID )
                        $this->warn( 'an additional location was left out: ' . implode( '; ', $p['reasons'] ) );
                    continue;
                }
            }
            eZNodeAssignment::create( array(
                'contentobject_id' => $object->attribute( 'id' ), 'contentobject_version' => 1, 'parent_node' => $parentID,
                'is_main' => $isMain ? 1 : 0, 'sort_field' => $class->attribute( 'sort_field' ), 'sort_order' => $class->attribute( 'sort_order' ),
                'priority' => isset( $location['priority'] ) ? (int) $location['priority'] : 0 ) )->store();
            $first = false;
        }
        $version = $object->version( 1 );
        $version->setAttribute( 'modified', time() );
        $version->setAttribute( 'status', eZContentObjectVersion::STATUS_DRAFT );
        $version->store();
        $errors = array();
        foreach ( $languages as $n => $language )
        {
            if ( $n > 0 )
                foreach ( $object->fetchDataMap( 1, $initial ) as $attribute )
                    $attribute->translateTo( $language );
            foreach ( $this->writeTranslation( $object, $version, $language, $item['translations'][$language], $language === $initial ) as $field => $error )
                $errors[count( $languages ) > 1 ? $language . ' ' . $field : $field] = $error;
        }
        $db->commit();
        $this->log( $object, 1, $initial, (int) $parent->attribute( 'node_id' ) );
        return array( $this->draftRow( $object, 1, implode( ',', $languages ), $errors ) );
    }

    /** @return array[] the drafts of an existing object (one per translation) */
    protected function update( array $item, array $plan )
    {
        $object = eZContentObject::fetch( (int) $plan['object_id'] );
        $out = array();
        foreach ( $plan['languages'] as $language )
        {
            if ( !$object->canEdit( false, false, false, $language ) )
            {
                $out[] = $this->draftRow( $object, 0, $language, array( '*' => 'you may not edit this object in ' . $language ) );
                continue;
            }
            if ( !in_array( $language, eZContentLanguage::fetchLocaleList(), true ) )
            {
                $out[] = $this->draftRow( $object, 0, $language, array( '*' => 'the language ' . $language . ' is not used in this installation' ) );
                continue;
            }
            $db = eZDB::instance();
            $db->begin();
            $copyFrom = in_array( $language, $object->availableLanguages(), true ) ? false : (string) $object->attribute( 'initial_language_code' );
            $version = $object->createNewVersionIn( $language, $copyFrom, false, true, eZContentObjectVersion::STATUS_DRAFT );
            $version->setAttribute( 'modified', time() );
            $version->setAttribute( 'creator_id', (int) eZUser::currentUserID() );
            $version->store();
            $errors = $this->writeTranslation( $object, $version, $language, $item['translations'][$language],
                                               $language === (string) $object->attribute( 'initial_language_code' ) );
            $db->commit();
            $this->log( $object, (int) $version->attribute( 'version' ), $language, (int) $object->attribute( 'main_node_id' ) );
            $out[] = $this->draftRow( $object, (int) $version->attribute( 'version' ), $language, $errors );
        }
        return $out;
    }

    /**
     * Writes the attributes of one translation into a draft.
     * @return array identifier => error
     */
    protected function writeTranslation( eZContentObject $object, eZContentObjectVersion $version, $language, array $translation, $isInitial )
    {
        $dataMap = $object->fetchDataMap( (int) $version->attribute( 'version' ), $language );
        $errors = array();
        foreach ( isset( $translation['attributes'] ) ? (array) $translation['attributes'] : array() as $identifier => $value )
        {
            if ( !isset( $dataMap[$identifier] ) )
            {
                $errors[$identifier] = 'the class has no attribute ' . $identifier;
                continue;
            }
            $attribute = $dataMap[$identifier];
            if ( !$isInitial && !$attribute->attribute( 'can_translate' ) )
                continue;
            $result = expAiPackageDatatypes::apply( $object, $attribute, (array) $value, $this );
            if ( $result !== true )
                $errors[$identifier] = $result;
        }
        $version->setAttribute( 'modified', time() );
        $version->store();
        $class = $object->contentClass();
        $object->setName( $class->contentObjectName( $object, (int) $version->attribute( 'version' ), $language ), (int) $version->attribute( 'version' ), $language );
        return $errors;
    }

    /** @return array a draft for the report */
    protected function draftRow( eZContentObject $object, $version, $language, array $errors )
    {
        $first = explode( ',', (string) $language );
        return array( 'object_id' => (int) $object->attribute( 'id' ), 'version' => (int) $version, 'language' => (string) $language,
                      'name' => (string) $object->attribute( 'name' ), 'errors' => $errors,
                      'url' => $version ? '/content/versionview/' . (int) $object->attribute( 'id' ) . '/' . (int) $version . '/' . $first[0] : '' );
    }

    /** The draft in expai_draft_log (source package), for the label and the lists of drafts. */
    protected function log( eZContentObject $object, $version, $language, $nodeID )
    {
        $row = array( 'contentobject_id' => (int) $object->attribute( 'id' ), 'version' => (int) $version, 'user_id' => (int) eZUser::currentUserID(),
                      'node_id' => (int) $object->attribute( 'main_node_id' ), 'parent_node_id' => (int) $nodeID, 'language_code' => (string) $language,
                      'action' => 'package', 'source' => 'package', 'provider' => '', 'model' => '',
                      'prompt_summary' => 'package ' . $this->package->name() );
        $fields = expAiDraftLog::definition()['fields'];
        if ( isset( $fields['package_uuid'] ) )
            $row['package_uuid'] = (string) $this->options['package_uuid'];
        if ( isset( $fields['job_uuid'] ) )
            $row['job_uuid'] = (string) $this->options['job_uuid'];
        expAiDraftLog::record( $row );
    }
}
