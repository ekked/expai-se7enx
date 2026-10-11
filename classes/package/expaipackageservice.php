<?php
/**
 * File containing the expAiPackageService class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * What the admin view expai/packages and the CLI do with a stored package: keep an upload, validate it, make a dry
 * run (the import plan and the diff against the published content), import it as drafts (in the request for a small
 * package, as a queue job group for a large one) and report on the import.
 */
class expAiPackageService
{
    /**
     * Keeps a package (a zip, a directory or a package.json) and validates it.
     * @return expAiPackageRecord
     * @throws expAiPackageException
     */
    public static function keep( $path, $origin, $userID )
    {
        $package = expAiPackage::open( $path, $origin !== 'upload' );
        $record = expAiPackage::store( $package, $origin, $userID );
        self::validate( $record );
        return $record;
    }

    /** @return array the validation report (stored on the record) */
    public static function validate( expAiPackageRecord $record )
    {
        try
        {
            $report = ( new expAiPackageValidator( $record->package() ) )->validate();
        }
        catch ( expAiPackageException $e )
        {
            $report = array( 'valid' => false, 'errors' => array( $e->text() ), 'warnings' => array(), 'objects' => 0, 'files' => 0, 'checksum' => array() );
        }
        $report['at'] = time();
        $state = in_array( $record->attribute( 'state' ), array( 'importing', 'imported' ), true ) ? null : ( $report['valid'] ? 'valid' : 'invalid' );
        $record->setReport( 'validation', $report, $state );
        return $report;
    }

    /** @return array plan, counts, diff (stored on the record) */
    public static function dryRun( expAiPackageRecord $record, array $options = array() )
    {
        $package = $record->package();
        $importer = new expAiPackageImporter( $package, array( 'dry_run' => true ) + $options );
        $result = $importer->run();
        $diff = ( new expAiPackageDiff( $package, $options ) )->diff();
        $report = array( 'at' => time(), 'options' => $options, 'counts' => $result['counts'], 'plan' => $result['plan'], 'diff' => $diff );
        $record->setReport( 'dry_run', $report );
        return $report;
    }

    /** @return bool whether an import of the record runs as a queue job group */
    public static function queued( expAiPackageRecord $record, array $options = array() )
    {
        if ( !empty( $options['queue'] ) )
            return expAiQueue::available();
        $above = expAiPackage::limit( 'QueueAbove', 20 );
        return expAiQueue::available() && (int) $record->attribute( 'object_count' ) > $above;
    }

    /**
     * Imports as drafts.
     * @param array $options on_conflict, remap, parent, languages, queue (bool)
     * @return array the report: imported (bool), queued (bool), group (uuid), counts, results
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
        $importer = new expAiPackageImporter( $record->package(), $options + array( 'package_uuid' => (string) $record->attribute( 'uuid' ) ) );
        $result = $importer->run();
        $report = array( 'at' => time(), 'options' => $options, 'queued' => false, 'imported' => $result['imported'], 'counts' => $result['counts'],
                         'results' => array_map( array( 'expAiPackageImportHandler', 'short' ), $result['results'] ),
                         'plan' => $result['imported'] ? array() : $result['plan'], 'user_id' => (int) eZUser::currentUserID() );
        $record->setReport( 'import', $report, $result['imported'] ? 'imported' : null );
        return $report;
    }

    /**
     * Queues the import as one job group of expai.package.import jobs ([PackageSettings] ChunkSize objects each).
     * @return array the report
     */
    public static function queueImport( expAiPackageRecord $record, array $options = array() )
    {
        $count = (int) $record->attribute( 'object_count' );
        $chunk = expAiPackage::limit( 'ChunkSize', 5 );
        $group = expQueue::createGroup( 'Import package ' . expAiText::cut( (string) $record->attribute( 'name' ), 80 ), 'expai.package.import',
                                        array( 'user_id' => (int) eZUser::currentUserID() ) );
        $uuid = (string) $record->attribute( 'uuid' );
        $jobs = 0;
        // objects in package order: a slice never needs a later slice (parents come first; a child of a new parent waits)
        expQueue::withLock( function () use ( $count, $chunk, $group, $uuid, $options, &$jobs )
        {
            for ( $start = 0; $start < $count; $start += $chunk )
            {
                expQueue::enqueue( 'expai.package.import', array( 'package_uuid' => $uuid, 'indices' => range( $start, min( $count, $start + $chunk ) - 1 ),
                                                                  'options' => $options ),
                                   array( 'group' => $group, 'idempotency_key' => 'expai.package.import:' . $uuid . ':' . (int) $group->attribute( 'id' ) . ':' . $start ) );
                ++$jobs;
            }
        } );
        $record->setAttribute( 'group_uuid', (string) $group->attribute( 'uuid' ) );
        $report = array( 'at' => time(), 'options' => $options, 'queued' => true, 'imported' => false, 'group' => (string) $group->attribute( 'uuid' ),
                         'jobs' => $jobs, 'counts' => array(), 'results' => array(), 'user_id' => (int) eZUser::currentUserID() );
        $record->setReport( 'import', $report, 'importing' );
        return $report;
    }

    /**
     * The report of a queued import, collected from the jobs of its group (stored on the record once the group ended).
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
        $results = array();
        $counts = array();
        $failed = array();
        foreach ( expQueue::listJobs( array( 'group_id' => (int) $group->attribute( 'id' ) ), 0, 10000 ) as $job )
        {
            $r = $job->resultArray();
            foreach ( isset( $r['results'] ) ? (array) $r['results'] : array() as $row )
            {
                $results[(int) $row['index']] = $row;
                $counts[$row['action']] = ( isset( $counts[$row['action']] ) ? $counts[$row['action']] : 0 ) + 1;
            }
            if ( in_array( $job->attribute( 'state' ), array( 'dead', 'cancelled' ), true ) )
                $failed[] = array( 'job' => $job->attribute( 'uuid' ), 'state' => $job->attribute( 'state' ), 'error' => $job->attribute( 'last_error' ) );
        }
        ksort( $results );
        $progress = $group->progress();
        $report = array( 'at' => time(), 'queued' => true, 'group' => $uuid, 'progress' => $progress, 'counts' => $counts,
                         'results' => array_values( $results ), 'failed_jobs' => $failed, 'imported' => $progress['active'] === 0 );
        if ( $progress['active'] === 0 && $record->attribute( 'state' ) === 'importing' )
        {
            $previous = $record->reportArray();
            $report['options'] = isset( $previous['import']['options'] ) ? $previous['import']['options'] : array();
            $record->setReport( 'import', $report, $failed ? 'failed' : 'imported' );
        }
        return $report;
    }
}
