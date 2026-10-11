<?php
/**
 * File containing the expAiPackageImportHandler class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The job type expai.package.import: imports objects of a stored package (expai_package) as drafts, as the user who
 * queued the import. A large package is one job group of these jobs (expAiPackageService::queueImport()), each with
 * a slice of the objects (payload indices), so the import shows progress, can be paused and cancelled, and is resumed
 * after a crash: an object whose remote id already has a draft from this package is planned again and not created
 * twice (the plan sees the draft and waits).
 */
class expAiPackageImportHandler extends expQueueHandlerBase
{
    public function validate( array $payload )
    {
        if ( empty( $payload['package_uuid'] ) || !isset( $payload['indices'] ) || !is_array( $payload['indices'] ) )
            return 'package_uuid and indices are required';
        return null;
    }

    public function describe( expQueueJob $job )
    {
        $p = $job->payloadArray();
        $indices = isset( $p['indices'] ) ? (array) $p['indices'] : array();
        return 'Import objects ' . ( $indices ? min( $indices ) + 1 . '-' . ( max( $indices ) + 1 ) : '' ) . ' of package ' . ( isset( $p['package_uuid'] ) ? $p['package_uuid'] : '' );
    }

    public function run( expQueueJob $job, expQueueContext $context )
    {
        $p = $job->payloadArray();
        $record = expAiPackageRecord::fetchByUUID( (string) $p['package_uuid'] );
        if ( !$record )
            throw new expQueuePermanentException( 'the package ' . $p['package_uuid'] . ' is gone', 'not_found' );
        $user = eZUser::currentUser();
        $access = $user->hasAccessTo( 'expai', 'package_import' );
        if ( $access['accessWord'] === 'no' )
            throw new expQueuePermanentException( 'the user may not import packages (expai/package_import)', 'access' );
        try
        {
            $package = $record->package();
        }
        catch ( expAiPackageException $e )
        {
            throw new expQueuePermanentException( 'the package cannot be read: ' . $e->text(), 'package' );
        }
        $options = isset( $p['options'] ) ? (array) $p['options'] : array();
        $options['package_uuid'] = (string) $record->attribute( 'uuid' );
        $options['job_uuid'] = (string) $job->attribute( 'uuid' );
        $options['dry_run'] = false;
        $importer = new expAiPackageImporter( $package, $options );
        $done = $context->checkpoint();
        $results = is_array( $done ) && isset( $done['results'] ) ? (array) $done['results'] : array();
        $indices = array_values( array_map( 'intval', (array) $p['indices'] ) );
        foreach ( $indices as $n => $i )
        {
            if ( isset( $results[$i] ) )
                continue;
            $context->throwIfCancelled();
            $result = $importer->importObject( $i );
            $results[$i] = self::short( $result );
            // after every object: a rerun does not import it again
            $context->saveCheckpoint( array( 'results' => $results ) );
            $context->progress( $n + 1, count( $indices ), $result['remote_id'] . ': ' . $result['action'] );
        }
        $counts = array();
        foreach ( $results as $r )
            $counts[$r['action']] = ( isset( $counts[$r['action']] ) ? $counts[$r['action']] : 0 ) + 1;
        return array( 'package_uuid' => (string) $record->attribute( 'uuid' ), 'counts' => $counts, 'results' => array_values( $results ) );
    }

    /** @return array a result as the job keeps it */
    public static function short( array $result )
    {
        return array( 'index' => $result['index'], 'remote_id' => $result['remote_id'], 'action' => $result['action'],
                      'reasons' => array_slice( (array) $result['reasons'], 0, 5 ),
                      'drafts' => array_map( function ( $d ) { return array( 'object_id' => $d['object_id'], 'version' => $d['version'], 'language' => $d['language'], 'url' => $d['url'] ); },
                                             isset( $result['drafts'] ) ? (array) $result['drafts'] : array() ),
                      'errors' => array_slice( isset( $result['errors'] ) ? (array) $result['errors'] : array(), 0, 10 ),
                      'warnings' => array_slice( isset( $result['warnings'] ) ? (array) $result['warnings'] : array(), 0, 10 ) );
    }
}
