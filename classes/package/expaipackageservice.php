<?php
/**
 * File containing the expAiPackageService class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * Content packages of Exponential Intelligence: a thin layer on the kernel's package system. The format is the
 * kernel's (classic XML or JSON, doc/bc/6.0/package-json-format.md of Exponential); validation, the report of what an
 * install meets (by remote id) and the install as drafts are the kernel's. What expai adds:
 *
 * - provenance: whether the content was written by AI, the models and tools, whether a person reviewed it, kept as
 *   package metadata (keys expai.*, eZPackage::setMetadata()) and shown on the package pages;
 * - drafts only: an import never publishes (the kernel's as_drafts install), and every draft is recorded in
 *   expai_draft_log (source "package"), so it carries the Exponential Intelligence label in the draft lists;
 * - the job queue for a large package (more than [PackageSettings] QueueAbove objects): the install runs as an
 *   expai.package.import job;
 * - expai/packages: the uploads of a user and the reports of each (table expai_package).
 */
class expAiPackageService
{
    const META_AI_GENERATED = 'expai.ai-generated';
    const META_MODELS = 'expai.models';
    const META_TOOLS = 'expai.tools';
    const META_HUMAN_REVIEWED = 'expai.human-reviewed';
    const META_NOTES = 'expai.notes';
    const META_GENERATOR = 'expai.generator';
    /** the kernel package repository expai imports into */
    const REPOSITORY = 'local';

    /**
     * A limit of [PackageSettings].
     * @return int
     */
    public static function limit( $name, $default )
    {
        $ini = eZINI::instance( 'expai.ini' );
        $value = $ini->hasVariable( 'PackageSettings', $name ) ? (int) $ini->variable( 'PackageSettings', $name ) : 0;
        return $value > 0 ? $value : $default;
    }

    /**
     * The provenance of a package from its metadata.
     * @param eZPackage $package
     * @return array ai_generated (bool), models, tools (string[]), human_reviewed (bool|null), notes, generator
     */
    public static function provenance( eZPackage $package )
    {
        $list = function ( $value ) { return $value === null ? array() : array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) ); };
        $reviewed = $package->metadata( self::META_HUMAN_REVIEWED );
        return array( 'ai_generated' => $package->metadata( self::META_AI_GENERATED ) === 'true',
                      'models' => $list( $package->metadata( self::META_MODELS ) ),
                      'tools' => $list( $package->metadata( self::META_TOOLS ) ),
                      'human_reviewed' => $reviewed === null ? null : $reviewed === 'true',
                      'notes' => (string) $package->metadata( self::META_NOTES ),
                      'generator' => (string) $package->metadata( self::META_GENERATOR ) );
    }

    /**
     * Writes provenance into a package's metadata (not stored: the caller stores or exports the package).
     * @param eZPackage $package
     * @param array $provenance ai_generated, models, tools, human_reviewed, notes, generator
     */
    public static function setProvenance( eZPackage $package, array $provenance )
    {
        if ( array_key_exists( 'ai_generated', $provenance ) )
            $package->setMetadata( self::META_AI_GENERATED, $provenance['ai_generated'] ? 'true' : 'false' );
        if ( !empty( $provenance['models'] ) )
            $package->setMetadata( self::META_MODELS, implode( ', ', (array) $provenance['models'] ) );
        if ( !empty( $provenance['tools'] ) )
            $package->setMetadata( self::META_TOOLS, implode( ', ', (array) $provenance['tools'] ) );
        if ( array_key_exists( 'human_reviewed', $provenance ) && $provenance['human_reviewed'] !== null )
            $package->setMetadata( self::META_HUMAN_REVIEWED, $provenance['human_reviewed'] ? 'true' : 'false' );
        if ( !empty( $provenance['notes'] ) )
            $package->setMetadata( self::META_NOTES, expAiText::cut( (string) $provenance['notes'], 500 ) );
        if ( !empty( $provenance['generator'] ) )
            $package->setMetadata( self::META_GENERATOR, expAiText::cut( (string) $provenance['generator'], 100 ) );
    }

    /**
     * Exports a subtree as a kernel package (JSON format by default) with provenance. The data policy applies:
     * content whose section or class has a region or zero-retention rule may go wherever the package goes, so it is
     * refused unless $options['allow_restricted'] (logged).
     *
     * @param int $nodeID
     * @param string $outDirectory
     * @param array $options name, summary, languages (string[]), format (json|xml), allow_restricted, provenance (array)
     * @return array file (the archive), objects (int), format
     * @throws expAiPackageException
     */
    public static function export( $nodeID, $outDirectory, array $options = array() )
    {
        $options += array( 'name' => '', 'summary' => '', 'languages' => array(), 'format' => 'json', 'allow_restricted' => false, 'provenance' => array() );
        $node = eZContentObjectTreeNode::fetch( (int) $nodeID );
        if ( !$node || !$node->canRead() )
            throw new expAiPackageException( 'Node %id does not exist or you may not read it.', array( '%id' => (int) $nodeID ) );
        if ( !is_dir( $outDirectory ) )
            throw new expAiPackageException( 'The directory %dir does not exist.', array( '%dir' => $outDirectory ) );
        $policy = expAiDataPolicy::forProvider( expAiConfig::instance(), '' );
        $restricted = array();
        $count = 0;
        $objects = array_merge( array( $node ), (array) eZContentObjectTreeNode::subTreeByNodeID( array( 'IgnoreVisibility' => true, 'Limitation' => array() ), (int) $nodeID ) );
        foreach ( $objects as $child )
        {
            $object = $child->object();
            if ( !$object )
                continue;
            ++$count;
            $requirement = $policy->requirement( $object );
            if ( $requirement['regions'] !== null || $requirement['zero_retention'] )
            {
                if ( !$options['allow_restricted'] )
                    $restricted[] = (string) $object->attribute( 'remote_id' );
                else
                    eZDebug::writeNotice( 'expai: package export of the restricted object ' . (int) $object->attribute( 'id' ) . ' by user '
                                          . eZUser::currentUserID() . ' (' . implode( ', ', $requirement['reasons'] ) . ')', __METHOD__ );
            }
        }
        if ( $restricted )
            throw new expAiPackageException( 'The data policy restricts %count object(s) of this export (%ids); export them only with --allow-restricted.',
                                             array( '%count' => count( $restricted ), '%ids' => implode( ', ', array_slice( $restricted, 0, 5 ) ) ) );

        // a package name is an identifier: "Autumn articles" becomes autumn_articles
        eZPackage::isValidName( $options['name'] !== '' ? $options['name'] : 'expai_export_' . date( 'Ymd_His' ), $name );
        if ( (string) $name === '' )
            throw new expAiPackageException( 'The package name %name is not valid.', array( '%name' => $options['name'] ) );
        $work = expPackageJson::workDirectory( 'expai-export' );
        try
        {
            $package = eZPackage::create( $name, array( 'summary' => $options['summary'] !== '' ? $options['summary'] : $node->attribute( 'name' ) ), $work );
            $package->setRelease( '1.0', '1', false, 'GPL-2.0-or-later', 'stable' );
            $handler = eZPackage::packageHandler( 'ezcontentobject' );
            $arguments = array( (string) (int) $nodeID, '--exclude-classes', '--exclude-templates' );
            if ( $options['languages'] )
                $arguments[] = '--language=' . implode( ',', $options['languages'] );
            $cli = eZCLI::instance();
            $wasQuiet = $cli->isQuiet();
            $cli->setIsQuiet( true );
            $parameters = $handler->handleAddParameters( 'ezcontentobject', $package, $cli, $arguments );
            if ( !$parameters )
            {
                $cli->setIsQuiet( $wasQuiet );
                throw new expAiPackageException( 'The subtree of node %id could not be packaged.', array( '%id' => (int) $nodeID ) );
            }
            $handler->add( 'ezcontentobject', $package, $cli, $parameters );
            $cli->setIsQuiet( $wasQuiet );
            self::setProvenance( $package, $options['provenance'] + array( 'generator' => 'expai ' . self::version() ) );
            $package->store();
            // what the package holds (the kernel's handler follows the siteaccess's ShowHiddenNodes: run the command
            // line with -s <admin siteaccess> to include hidden content)
            $count = (int) expPackageConflictReport::build( $package, 1 )['object_total'];
            $format = $options['format'] === 'xml' ? eZPackage::FORMAT_XML : eZPackage::FORMAT_JSON;
            $file = rtrim( $outDirectory, '/' ) . '/' . $package->exportName( $format );
            if ( !$package->exportToArchive( $file, 'tar.gz', $format ) )
                throw new expAiPackageException( 'The package could not be written to %file.', array( '%file' => $file ) );
            return array( 'file' => $file, 'objects' => $count, 'format' => $format, 'name' => $name );
        }
        finally
        {
            eZDir::recursiveDelete( $work );
        }
    }

    /** @return string the version of expai */
    public static function version()
    {
        $info = class_exists( 'expaiInfo' ) ? expaiInfo::info() : array();
        return isset( $info['Version'] ) ? (string) $info['Version'] : '';
    }

    /**
     * Keeps an uploaded or given package archive: validates it (the kernel's checks) and, when it is valid, imports it
     * into the kernel's package repository. A package of the same name that expai imported before is replaced.
     *
     * @param string $archivePath an .ezpkg (classic or JSON)
     * @param string $origin upload or cli
     * @param int $userID
     * @param string $originalName the uploaded file's name
     * @return expAiPackageRecord
     * @throws expAiPackageException
     */
    public static function keep( $archivePath, $origin, $userID, $originalName = '' )
    {
        $inspection = eZPackageUploadInspector::inspect( $archivePath, $originalName !== '' ? $originalName : basename( $archivePath ) );
        if ( !$inspection['ok'] )
            throw new expAiPackageException( 'The file is not a package that can be imported: %reason', array( '%reason' => eZPackageUploadInspector::message( $inspection ) ) );
        $work = expPackageJson::workDirectory( 'expai-keep' );
        try
        {
            if ( !expPackageJson::extractArchive( $archivePath, $work . '/pkg', $error ) )
                throw new expAiPackageException( 'The package cannot be read: %reason', array( '%reason' => $error ) );
            $validation = expPackageJson::validateDirectory( $work . '/pkg' );
        }
        finally
        {
            eZDir::recursiveDelete( $work );
        }
        $record = new expAiPackageRecord( array( 'uuid' => expQueue::uuid(), 'name' => expAiText::cut( (string) $inspection['name'], 150 ),
                                                 'checksum' => hash_file( 'sha256', $archivePath ), 'format_version' => (string) $inspection['format'],
                                                 'state' => 'uploaded', 'bytes' => (int) filesize( $archivePath ), 'origin' => (string) $origin,
                                                 'storage' => '', 'user_id' => (int) $userID, 'created' => time(), 'modified' => time() ) );
        $record->store();
        $validation['at'] = time();
        if ( !$validation['valid'] )
        {
            $record->setReport( 'validation', $validation, 'invalid' );
            return $record;
        }
        $name = (string) $inspection['name'];
        $existing = eZPackage::fetch( $name, false, self::REPOSITORY );
        if ( $existing )
        {
            // a package expai imported before (an upload of a newer version) is replaced; any other is left alone
            $previous = eZPersistentObject::fetchObject( expAiPackageRecord::definition(), null, array( 'storage' => $name ), true );
            if ( !$previous )
            {
                $validation['valid'] = false;
                $validation['errors'][] = 'A package named ' . $name . ' is in the package repository already and was not imported here: remove it on Setup > Packages, or rename this one.';
                $record->setReport( 'validation', $validation, 'invalid' );
                return $record;
            }
            $existing->remove();
        }
        $packageName = '';
        $package = eZPackage::import( $archivePath, $packageName, true, self::REPOSITORY );
        if ( !$package instanceof eZPackage )
        {
            $validation['valid'] = false;
            $validation['errors'][] = 'The kernel did not import the package (' . ( $package === eZPackage::STATUS_INVALID_NAME ? 'invalid name' : 'see the debug output' ) . ').';
            $record->setReport( 'validation', $validation, 'invalid' );
            return $record;
        }
        $record->setAttribute( 'storage', $packageName );
        $report = expPackageConflictReport::build( $package, 1 );
        $record->setAttribute( 'object_count', (int) $report['object_total'] );
        $record->setAttribute( 'file_count', count( expPackageJson::fileList( $package->path() ) ) );
        $record->setAttribute( 'name', expAiText::cut( (string) ( $package->attribute( 'summary' ) ?: $packageName ), 150 ) );
        $record->setReport( 'validation', $validation, 'valid' );
        return $record;
    }

    /** @return array the validation report (stored on the record) */
    public static function validate( expAiPackageRecord $record )
    {
        $package = $record->package();
        $report = $package ? expPackageJson::validateDirectory( $package->path() )
                           : array( 'valid' => false, 'errors' => array( 'The package is not in the package repository (any more).' ), 'warnings' => array() );
        $report['at'] = time();
        $state = in_array( $record->attribute( 'state' ), array( 'importing', 'imported' ), true ) ? null : ( $report['valid'] ? 'valid' : 'invalid' );
        $record->setReport( 'validation', $report, $state );
        return $report;
    }

    /** @return array what an install meets here, by remote id (stored on the record as dry_run) */
    public static function dryRun( expAiPackageRecord $record, array $options = array() )
    {
        $package = $record->package();
        if ( !$package )
            throw new expAiPackageException( 'The package is not in the package repository (any more).' );
        $report = expPackageConflictReport::build( $package, 500 );
        $report['at'] = time();
        $report['options'] = $options;
        $record->setReport( 'dry_run', $report );
        return $report;
    }

    /** @return bool whether an import of the record runs in the job queue */
    public static function queued( expAiPackageRecord $record, array $options = array() )
    {
        if ( !empty( $options['queue'] ) )
            return expAiQueue::available();
        return expAiQueue::available() && (int) $record->attribute( 'object_count' ) > self::limit( 'QueueAbove', 20 );
    }

    /**
     * Imports as drafts: in the request, or as a job for a large package.
     * @param array $options existing (update|skip), parent (node id, node remote id or URL path), queue (bool)
     * @return array the report: imported (bool), queued (bool), group (uuid), counts, results, waiting, skipped
     * @throws expAiPackageException when the package is not valid
     */
    public static function import( expAiPackageRecord $record, array $options = array() )
    {
        $validation = self::validate( $record );
        if ( !$validation['valid'] )
            throw new expAiPackageException( 'The package is not valid: %errors', array( '%errors' => implode( '; ', array_slice( $validation['errors'], 0, 3 ) ) ) );
        $queue = self::queued( $record, $options );
        unset( $options['queue'] );
        if ( $queue )
            return self::queueImport( $record, $options );
        $report = self::runImport( $record, $options, '' );
        $record->setReport( 'import', $report, $report['imported'] ? 'imported' : 'failed' );
        return $report;
    }

    /**
     * The install as drafts itself (in the request or in the queue's job), as the current user.
     * @return array the report
     */
    public static function runImport( expAiPackageRecord $record, array $options, $jobUUID )
    {
        $package = $record->package();
        if ( !$package )
            throw new expAiPackageException( 'The package is not in the package repository (any more).' );
        $userID = (int) eZUser::currentUserID();
        $parentNodeID = self::parentNodeID( isset( $options['parent'] ) ? (string) $options['parent'] : '' );
        $siteAccess = (string) eZINI::instance()->variable( 'SiteSettings', 'DefaultAccess' );
        $installParameters = array( 'site_access_map' => array( '*' => $siteAccess ), 'top_nodes_map' => array( '*' => $parentNodeID ),
                                    'design_map' => array( '*' => $siteAccess ), 'restore_dates' => false, 'user_id' => $userID,
                                    'non-interactive' => true, 'language_map' => $package->defaultLanguageMap(),
                                    'as_drafts' => true,
                                    'drafts_existing' => isset( $options['existing'] ) && $options['existing'] === 'skip' ? 'skip' : 'update' );
        $ok = $package->install( $installParameters );
        $results = array();
        $counts = array();
        foreach ( isset( $installParameters['drafts'] ) ? $installParameters['drafts'] : array() as $draft )
        {
            $counts[$draft['action']] = ( isset( $counts[$draft['action']] ) ? $counts[$draft['action']] : 0 ) + 1;
            $object = $draft['version'] ? eZContentObject::fetch( (int) $draft['object_id'] ) : null;
            if ( $object )
                self::log( $object, (int) $draft['version'], $record, $jobUUID, $parentNodeID );
            $results[] = array( 'remote_id' => $draft['remote_id'], 'action' => $draft['action'], 'object_id' => (int) $draft['object_id'],
                                'version' => $draft['version'] === null ? null : (int) $draft['version'], 'name' => (string) $draft['name'],
                                'url' => $draft['version'] ? '/content/versionview/' . (int) $draft['object_id'] . '/' . (int) $draft['version'] : '' );
        }
        return array( 'at' => time(), 'options' => $options, 'queued' => false, 'imported' => (bool) $ok, 'counts' => $counts, 'results' => $results,
                      'waiting' => isset( $installParameters['drafts-waiting'] ) ? $installParameters['drafts-waiting'] : array(),
                      'skipped' => isset( $installParameters['skipped-items'] ) ? $installParameters['skipped-items'] : array(),
                      'error' => !$ok && isset( $installParameters['error']['description'] ) ? (string) $installParameters['error']['description'] : '',
                      'user_id' => $userID );
    }

    /**
     * The node id of a parent given as a node id, a node remote id or a URL path; the content root (2) when empty.
     * @throws expAiPackageException
     */
    public static function parentNodeID( $parent )
    {
        $parent = trim( (string) $parent );
        if ( $parent === '' )
            return 2;
        $node = ctype_digit( $parent ) ? eZContentObjectTreeNode::fetch( (int) $parent ) : null;
        if ( !$node )
            $node = eZContentObjectTreeNode::fetchByRemoteID( $parent );
        if ( !$node )
            $node = eZContentObjectTreeNode::fetchByURLPath( trim( $parent, '/' ) );
        if ( !$node )
            throw new expAiPackageException( 'The parent %parent was not found.', array( '%parent' => $parent ) );
        return (int) $node->attribute( 'node_id' );
    }

    /** The draft in expai_draft_log (source package), for the label and the lists of drafts. */
    protected static function log( eZContentObject $object, $version, expAiPackageRecord $record, $jobUUID, $parentNodeID )
    {
        $versionObject = $object->version( $version );
        $row = array( 'contentobject_id' => (int) $object->attribute( 'id' ), 'version' => (int) $version, 'user_id' => (int) eZUser::currentUserID(),
                      'node_id' => (int) $object->attribute( 'main_node_id' ), 'parent_node_id' => (int) $parentNodeID,
                      'language_code' => $versionObject ? (string) $versionObject->initialLanguageCode() : '',
                      'action' => 'package', 'source' => 'package', 'provider' => '', 'model' => '',
                      'prompt_summary' => 'package ' . $record->attribute( 'name' ),
                      'package_uuid' => (string) $record->attribute( 'uuid' ), 'job_uuid' => (string) $jobUUID );
        expAiDraftLog::record( $row );
    }

    /**
     * Queues the import as one expai.package.import job (in a job group, for its progress).
     * @return array the report
     */
    public static function queueImport( expAiPackageRecord $record, array $options = array() )
    {
        $group = expQueue::createGroup( 'Import package ' . expAiText::cut( (string) $record->attribute( 'name' ), 80 ), 'expai.package.import',
                                        array( 'user_id' => (int) eZUser::currentUserID() ) );
        $uuid = (string) $record->attribute( 'uuid' );
        expQueue::enqueue( 'expai.package.import', array( 'package_uuid' => $uuid, 'options' => $options ),
                           array( 'group' => $group, 'idempotency_key' => 'expai.package.import:' . $uuid . ':' . (int) $group->attribute( 'id' ) ) );
        $record->setAttribute( 'group_uuid', (string) $group->attribute( 'uuid' ) );
        $report = array( 'at' => time(), 'options' => $options, 'queued' => true, 'imported' => false, 'group' => (string) $group->attribute( 'uuid' ),
                         'jobs' => 1, 'counts' => array(), 'results' => array(), 'user_id' => (int) eZUser::currentUserID() );
        $record->setReport( 'import', $report, 'importing' );
        return $report;
    }

    /**
     * The report of a queued import, from its job (stored on the record once the job ended).
     * @return array|null
     */
    public static function queuedReport( expAiPackageRecord $record )
    {
        $uuid = (string) $record->attribute( 'group_uuid' );
        if ( $uuid === '' || !expAiQueue::available() )
            return null;
        $group = expQueue::resolveGroup( $uuid );
        if ( !$group )
            return null;
        $report = array( 'at' => time(), 'queued' => true, 'group' => $uuid, 'progress' => $group->progress(), 'counts' => array(), 'results' => array(),
                         'waiting' => array(), 'skipped' => array(), 'failed_jobs' => array(), 'imported' => false );
        foreach ( expQueue::listJobs( array( 'group_id' => (int) $group->attribute( 'id' ) ), 0, 10 ) as $job )
        {
            $result = $job->resultArray();
            foreach ( array( 'counts', 'results', 'waiting', 'skipped' ) as $key )
                if ( isset( $result[$key] ) )
                    $report[$key] = $result[$key];
            if ( in_array( $job->attribute( 'state' ), array( 'dead', 'cancelled' ), true ) )
                $report['failed_jobs'][] = array( 'job' => $job->attribute( 'uuid' ), 'state' => $job->attribute( 'state' ), 'error' => $job->attribute( 'last_error' ) );
        }
        $report['imported'] = $report['progress']['active'] === 0 && !$report['failed_jobs'];
        if ( $report['progress']['active'] === 0 && $record->attribute( 'state' ) === 'importing' )
        {
            $previous = $record->reportArray();
            $report['options'] = isset( $previous['import']['options'] ) ? $previous['import']['options'] : array();
            $record->setReport( 'import', $report, $report['failed_jobs'] ? 'failed' : 'imported' );
        }
        return $report;
    }
}
