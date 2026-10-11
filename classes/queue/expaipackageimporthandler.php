<?php
/**
 * File containing the expAiPackageImportHandler class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The job type expai.package.import: installs a kernel package that expai imported (expai_package) as drafts, as the
 * user who queued it (expAiPackageService::runImport(), the kernel's install as drafts). One job per package, in a job
 * group for its progress. A job that runs again does not write a second draft of an object it already wrote: with
 * the option existing=skip an object of the package that exists is left alone; with existing=update (the default)
 * a second run gives existing objects one more draft, as a second import would.
 */
class expAiPackageImportHandler extends expQueueHandlerBase
{
    public function validate( array $payload )
    {
        if ( empty( $payload['package_uuid'] ) )
            return 'package_uuid is required';
        return null;
    }

    public function describe( expQueueJob $job )
    {
        $p = $job->payloadArray();
        return 'Install package ' . ( isset( $p['package_uuid'] ) ? $p['package_uuid'] : '' ) . ' as drafts';
    }

    public function run( expQueueJob $job, expQueueContext $context )
    {
        $p = $job->payloadArray();
        $record = expAiPackageRecord::fetchByUUID( (string) $p['package_uuid'] );
        if ( !$record )
            throw new expQueuePermanentException( 'the package ' . $p['package_uuid'] . ' is gone', 'not_found' );
        $access = eZUser::currentUser()->hasAccessTo( 'expai', 'package_import' );
        if ( $access['accessWord'] === 'no' )
            throw new expQueuePermanentException( 'the user may not import packages (expai/package_import)', 'access' );
        $done = $context->checkpoint();
        if ( is_array( $done ) && isset( $done['report'] ) )
            return $done['report'];
        $context->throwIfCancelled();
        try
        {
            $report = expAiPackageService::runImport( $record, isset( $p['options'] ) ? (array) $p['options'] : array(), (string) $job->attribute( 'uuid' ) );
        }
        catch ( expAiPackageException $e )
        {
            throw new expQueuePermanentException( 'the package cannot be installed: ' . $e->text(), 'package' );
        }
        $short = array( 'package_uuid' => (string) $record->attribute( 'uuid' ), 'imported' => $report['imported'], 'counts' => $report['counts'],
                        'results' => array_slice( $report['results'], 0, 2000 ), 'waiting' => array_slice( $report['waiting'], 0, 500 ),
                        'skipped' => array_slice( $report['skipped'], 0, 200 ), 'error' => $report['error'] );
        $context->saveCheckpoint( array( 'report' => $short ) );
        $context->progress( 1, 1, count( $report['results'] ) . ' drafts' );
        if ( !$report['imported'] )
            throw new expQueuePermanentException( 'the install as drafts failed: ' . ( $report['error'] !== '' ? $report['error'] : 'see the debug output' ), 'install' );
        return $short;
    }
}
