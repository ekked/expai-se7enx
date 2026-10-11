<?php
/**
 * File containing the expAiPackageExporter class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * Writes content into a package, as the current user (only what the user may read): a subtree (parents before
 * children) or objects by remote id. Ids become remote ids (parents, relations, links and embeds in rich text), files
 * are copied once each under their SHA-256. Every object carries "expected" (exists, modified, current_version,
 * content_hash): an import on the same installation finds conflicts with it.
 *
 * The data policy applies: content whose section or class has a region or zero-retention rule ([DataPolicy]) may go
 * wherever the package goes, so it is refused unless allow_restricted is set (the CLI's --allow-restricted, which is
 * logged).
 */
class expAiPackageExporter
{
    /** @var array */
    protected $options;

    /** @var array object id => remote id of the objects in the package */
    protected $inPackage = array();

    /** @var array path => file entry */
    protected $files = array();

    /** @var array path => source path on disk */
    protected $sources = array();

    /** @var string[] */
    protected $warnings = array();

    /** @var string[] remote ids refused by the data policy */
    protected $restricted = array();

    /**
     * @param array $options languages (string[]: only these), files (bool, default true), allow_restricted (bool),
     *                       hash_only (bool: hash files, do not copy them: for diffs), description, generator,
     *                       ai_generated (bool), models (string[])
     */
    public function __construct( array $options = array() )
    {
        $this->options = $options + array( 'languages' => array(), 'files' => true, 'allow_restricted' => false, 'hash_only' => false,
                                           'description' => '', 'generator' => 'expai-export', 'ai_generated' => false, 'models' => array(), 'name' => '' );
    }

    /**
     * The objects of a subtree the current user may read, parents first.
     * @return eZContentObject[]
     */
    public static function subtreeObjects( $nodeID, $childrenOnly = false, $max = 2000 )
    {
        $node = eZContentObjectTreeNode::fetch( (int) $nodeID );
        if ( !$node || !$node->canRead() )
            throw new expAiPackageException( 'Node %id does not exist or you may not read it.', array( '%id' => (int) $nodeID ) );
        $out = array();
        if ( !$childrenOnly )
            $out[(int) $node->attribute( 'contentobject_id' )] = $node->object();
        $offset = 0;
        do
        {
            $children = (array) eZContentObjectTreeNode::subTreeByNodeID( array( 'SortBy' => array( array( 'path', true ) ), 'Offset' => $offset,
                                                                                 'Limit' => 200, 'IgnoreVisibility' => true ), (int) $nodeID );
            foreach ( $children as $child )
            {
                $object = $child->object();
                if ( $object && !isset( $out[(int) $object->attribute( 'id' )] ) )
                    $out[(int) $object->attribute( 'id' )] = $object;
                if ( count( $out ) > $max )
                    throw new expAiPackageException( 'The subtree has more than %max objects ([PackageSettings] MaxObjects).', array( '%max' => $max ) );
            }
            $offset += 200;
        }
        while ( count( $children ) === 200 );
        return array_values( $out );
    }

    /**
     * Builds the package.
     * @param eZContentObject[] $objects parents before children
     * @return expAiPackage (in memory; save() writes it with its files)
     * @throws expAiPackageException
     */
    public function export( array $objects )
    {
        foreach ( $objects as $object )
            $this->inPackage[(int) $object->attribute( 'id' )] = (string) $object->attribute( 'remote_id' );
        $policy = expAiDataPolicy::forProvider( expAiConfig::instance(), '' );
        $items = array();
        foreach ( $objects as $object )
        {
            if ( !$object->canRead() )
            {
                $this->warnings[] = 'object ' . (int) $object->attribute( 'id' ) . ' is not readable for you: left out';
                continue;
            }
            $requirement = $policy->requirement( $object );
            if ( $requirement['regions'] !== null || $requirement['zero_retention'] )
            {
                if ( !$this->options['allow_restricted'] )
                {
                    $this->restricted[] = (string) $object->attribute( 'remote_id' );
                    continue;
                }
                eZDebug::writeNotice( 'expai: package export of the restricted object ' . (int) $object->attribute( 'id' ) . ' by user '
                                      . eZUser::currentUserID() . ' (' . implode( ', ', $requirement['reasons'] ) . ')', __METHOD__ );
            }
            $items[] = $this->object( $object );
        }
        if ( $this->restricted )
            throw new expAiPackageException( 'The data policy restricts %count object(s) of this export (%ids); export them only with --allow-restricted.',
                                             array( '%count' => count( $this->restricted ), '%ids' => implode( ', ', array_slice( $this->restricted, 0, 5 ) ) ) );
        $data = array(
            'format' => expAiPackage::FORMAT,
            'format_version' => expAiPackage::VERSION,
            'package' => array( 'uuid' => expQueue::uuid(), 'name' => (string) $this->options['name'] !== '' ? (string) $this->options['name'] : 'export ' . date( 'Y-m-d H:i' ),
                                'created' => date( 'c' ), 'description' => (string) $this->options['description'],
                                'generator' => array( 'tool' => (string) $this->options['generator'], 'version' => self::version() ),
                                'source_site' => (string) eZINI::instance()->variable( 'SiteSettings', 'SiteName' ) ),
            'provenance' => array( 'ai_generated' => (bool) $this->options['ai_generated'], 'models' => array_values( (array) $this->options['models'] ),
                                   'human_reviewed' => !$this->options['ai_generated'], 'exported_by' => (int) eZUser::currentUserID() ),
            'objects' => $items,
            'files' => array_values( $this->files ),
            'warnings' => $this->warnings,
        );
        $dir = '';
        if ( $this->sources )
        {
            $dir = expAiPackage::storageRoot() . '/tmp-' . bin2hex( random_bytes( 8 ) );
            eZDir::mkdir( $dir . '/files', 0770, true );
            foreach ( $this->sources as $rel => $source )
                copy( $source, $dir . '/' . $rel );
            foreach ( $this->sources as $rel => $source )
                if ( strpos( $source, $dir ) !== 0 && strpos( $source, expAiPackage::storageRoot() . '/tmp-' ) === 0 )
                    @unlink( $source );
        }
        return expAiPackage::fromArray( $data, $dir );
    }

    /** @return string the version of expai */
    public static function version()
    {
        $info = class_exists( 'expaiInfo' ) ? expaiInfo::info() : array();
        return isset( $info['Version'] ) ? (string) $info['Version'] : '';
    }

    /** @return string[] */
    public function warnings()
    {
        return $this->warnings;
    }

    /** @return array one object of the package */
    public function object( eZContentObject $object )
    {
        $section = eZSection::fetch( (int) $object->attribute( 'section_id' ) );
        $initial = (string) $object->attribute( 'initial_language_code' );
        $languages = $object->availableLanguages();
        if ( $this->options['languages'] )
            $languages = array_values( array_intersect( $languages, (array) $this->options['languages'] ) );
        $translations = array();
        foreach ( $languages as $language )
            $translations[$language] = $this->translation( $object, $language, $language === $initial );
        $locations = array();
        foreach ( (array) $object->assignedNodes( false ) as $row )
        {
            $node = eZContentObjectTreeNode::fetch( (int) $row['node_id'] );
            $parent = $node ? $node->fetchParent() : null;
            if ( !$node || !$parent )
                continue;
            $location = array( 'main' => (int) $node->attribute( 'node_id' ) === (int) $node->attribute( 'main_node_id' ),
                               'priority' => (int) $node->attribute( 'priority' ), 'node_remote_id' => (string) $node->attribute( 'remote_id' ),
                               'parent' => $this->parentRef( $parent ) );
            if ( $node->attribute( 'is_hidden' ) )
                $location['hidden'] = true;
            $locations[] = $location;
        }
        usort( $locations, function ( $a, $b ) { return (int) $b['main'] - (int) $a['main']; } );
        $item = array(
            'remote_id' => (string) $object->attribute( 'remote_id' ),
            'class_identifier' => (string) $object->attribute( 'class_identifier' ),
            'section_identifier' => $section ? (string) $section->attribute( 'identifier' ) : '',
            'always_available' => (bool) $object->isAlwaysAvailable(),
            'initial_language' => $initial,
            'locations' => $locations,
            'translations' => $translations,
        );
        $item['expected'] = array( 'exists' => true, 'modified' => (int) $object->attribute( 'modified' ),
                                   'current_version' => (int) $object->attribute( 'current_version' ),
                                   'content_hash' => self::contentHash( $translations ) );
        return $item;
    }

    /** @return array the parent of a location: the object remote id when it is in the package, else node remote id and path */
    protected function parentRef( eZContentObjectTreeNode $parent )
    {
        $objectID = (int) $parent->attribute( 'contentobject_id' );
        if ( isset( $this->inPackage[$objectID] ) && (int) $parent->attribute( 'node_id' ) === (int) $parent->attribute( 'main_node_id' ) )
            return array( 'object_remote_id' => $this->inPackage[$objectID] );
        return array( 'node_remote_id' => (string) $parent->attribute( 'remote_id' ), 'path' => '/' . ltrim( (string) $parent->attribute( 'path_identification_string' ), '/' ) );
    }

    /** @return array name, attributes of one translation */
    public function translation( eZContentObject $object, $language, $initial )
    {
        $attributes = array();
        foreach ( $object->fetchDataMap( false, $language ) as $identifier => $attribute )
        {
            // an attribute that is not translatable has the value of the main language in every translation
            if ( !$initial && !$attribute->attribute( 'can_translate' ) )
                continue;
            $value = expAiPackageDatatypes::export( $attribute, $this );
            if ( $value === null )
            {
                $this->warnings[] = 'object ' . $object->attribute( 'remote_id' ) . ' ' . $language . ': the attribute ' . $identifier
                                  . ' (' . $attribute->attribute( 'data_type_string' ) . ') is not supported by packages and left out';
                continue;
            }
            $attributes[$identifier] = $value;
        }
        return array( 'name' => (string) $object->name( false, $language ), 'attributes' => $attributes );
    }

    /** @return string the content hash of translations (sha256 of their comparable values) */
    public static function contentHash( array $translations )
    {
        $flat = array();
        ksort( $translations );
        foreach ( $translations as $language => $translation )
        {
            $attributes = isset( $translation['attributes'] ) ? (array) $translation['attributes'] : array();
            ksort( $attributes );
            foreach ( $attributes as $identifier => $value )
                $flat[] = $language . '/' . $identifier . '=' . expAiPackageDatatypes::comparable( (array) $value );
        }
        return hash( 'sha256', implode( "\n", $flat ) );
    }

    // ---- the export context of expAiPackageDatatypes -----------------------------------------------------------

    /** @return string|null the remote id of an object */
    public function objectRemoteId( $objectID )
    {
        if ( isset( $this->inPackage[(int) $objectID] ) )
            return $this->inPackage[(int) $objectID];
        $rows = eZDB::instance()->arrayQuery( 'SELECT remote_id FROM ezcontentobject WHERE id = ' . (int) $objectID );
        return is_array( $rows ) && isset( $rows[0]['remote_id'] ) ? (string) $rows[0]['remote_id'] : null;
    }

    /** @return string|null the remote id of a node */
    public function nodeRemoteId( $nodeID )
    {
        $rows = eZDB::instance()->arrayQuery( 'SELECT remote_id FROM ezcontentobject_tree WHERE node_id = ' . (int) $nodeID );
        return is_array( $rows ) && isset( $rows[0]['remote_id'] ) ? (string) $rows[0]['remote_id'] : null;
    }

    /**
     * Adds a file of the installation (a path relative to the installation, or in the cluster).
     * @return string|null files/<sha256>.<ext>
     */
    public function addFile( $path, $mime, $name )
    {
        if ( !$this->options['files'] )
            return null;
        $local = $path;
        $temporary = false;
        if ( !is_file( $local ) )
        {
            $handler = eZClusterFileHandler::instance( $path );
            if ( !$handler->exists() )
            {
                $this->warnings[] = 'the file of ' . basename( (string) $name ) . ' is missing on this installation: left out';
                return null;
            }
            $local = $handler->fetchUnique();
            $temporary = true;
        }
        $hash = hash_file( 'sha256', $local );
        $ext = strtolower( pathinfo( (string) $name !== '' ? (string) $name : $path, PATHINFO_EXTENSION ) );
        $ext = preg_match( '/^[a-z0-9]{1,8}$/', $ext ) ? $ext : 'bin';
        $rel = 'files/' . $hash . '.' . $ext;
        if ( !isset( $this->files[$rel] ) )
        {
            $finfo = function_exists( 'finfo_open' ) ? finfo_open( FILEINFO_MIME_TYPE ) : null;
            $this->files[$rel] = array( 'path' => $rel, 'sha256' => $hash, 'size' => (int) filesize( $local ),
                                        'mime' => $finfo ? (string) finfo_file( $finfo, $local ) : (string) $mime, 'name' => basename( (string) $name ) );
            if ( !$this->options['hash_only'] )
            {
                if ( $temporary )
                {
                    // the unique copy of the cluster is moved to the export's own place
                    $keep = expAiPackage::storageRoot() . '/tmp-file-' . bin2hex( random_bytes( 6 ) );
                    rename( $local, $keep );
                    $this->sources[$rel] = $keep;
                    $temporary = false;
                }
                else
                    $this->sources[$rel] = $local;
            }
        }
        if ( $temporary )
            @unlink( $local );
        return $rel;
    }
}
