<?php
/**
 * expai/packages (policy expai/package_import): content packages, a thin layer on the kernel's packages. Upload a
 * package (an .ezpkg in the classic or the JSON format); it is validated by the kernel and imported into the kernel's
 * package repository (Setup > Packages, repository "local"). The page shows its provenance (written by AI, models,
 * tools), what an install meets here by remote id (the kernel's report), and installs it as drafts (in the request,
 * or as a queue job for a large package); every draft is in the draft log with the package. Nothing is published.
 *
 * View parameters: (package)/<uuid> one package, (offset)/<n> the list. POST forms carry the form token (ezformtoken).
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$user = eZUser::currentUser();
$uuid = isset( $Params['UserParameters']['package'] ) ? (string) $Params['UserParameters']['package'] : '';
$offset = isset( $Params['UserParameters']['offset'] ) ? max( 0, (int) $Params['UserParameters']['offset'] ) : 0;
$notice = '';
$error = '';
$isAdmin = $user->hasAccessTo( 'expai', 'log' )['accessWord'] !== 'no';
$record = null;

// the package of the page: only its uploader, or a user with expai/log, sees it
if ( $uuid !== '' )
{
    $record = expAiPackageRecord::fetchByUUID( $uuid );
    if ( !$record || ( !$isAdmin && (int) $record->attribute( 'user_id' ) !== (int) $user->attribute( 'contentobject_id' ) ) )
        return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
}

$options = function () use ( $Module )
{
    $out = array( 'existing' => (string) $Module->actionParameter( 'Existing' ) === 'skip' ? 'skip' : 'update' );
    $parent = trim( (string) $Module->actionParameter( 'Parent' ) );
    if ( $parent !== '' )
        $out['parent'] = substr( $parent, 0, 200 );
    return $out;
};

try
{
    if ( $Module->isCurrentAction( 'Upload' ) )
    {
        if ( !eZHTTPFile::canFetch( 'ExpAiPackageFile' ) )
            throw new expAiPackageException( 'Choose a package file to upload.' );
        $file = eZHTTPFile::fetch( 'ExpAiPackageFile' );
        $max = expAiPackageService::limit( 'MaxUploadBytes', 52428800 );
        if ( !$file || (int) $file->attribute( 'filesize' ) <= 0 )
            throw new expAiPackageException( 'Choose a package file to upload.' );
        if ( (int) $file->attribute( 'filesize' ) > $max )
            throw new expAiPackageException( 'The file is larger than %max bytes; import larger packages with ./console ext:expai:package import.', array( '%max' => $max ) );
        $record = expAiPackageService::keep( $file->attribute( 'filename' ), 'upload', (int) $user->attribute( 'contentobject_id' ),
                                             (string) $file->attribute( 'original_filename' ) );
        return $Module->redirectTo( '/expai/packages/(package)/' . $record->attribute( 'uuid' ) );
    }
    if ( $record && $Module->isCurrentAction( 'Validate' ) )
    {
        $report = expAiPackageService::validate( $record );
        $notice = $report['valid'] ? ezpI18n::tr( 'extension/expai/package', 'The package is valid.' )
                                   : ezpI18n::tr( 'extension/expai/package', 'The package is not valid.' );
    }
    if ( $record && $Module->isCurrentAction( 'DryRun' ) )
    {
        expAiPackageService::dryRun( $record, $options() );
        $notice = ezpI18n::tr( 'extension/expai/package', 'Dry run done: nothing was written.' );
    }
    if ( $record && $Module->isCurrentAction( 'Import' ) )
    {
        $report = expAiPackageService::import( $record, $options() );
        if ( $report['queued'] )
            $notice = ezpI18n::tr( 'extension/expai/package', 'The import runs in the job queue. This page shows its progress.' );
        elseif ( $report['imported'] )
            $notice = ezpI18n::tr( 'extension/expai/package', 'Imported as drafts: review them below and send them for publishing.' );
        else
            $error = ezpI18n::tr( 'extension/expai/package', 'The install as drafts failed: %error', null, array( '%error' => $report['error'] ) );
    }
}
catch ( expAiPackageException $e )
{
    $error = $e->translatedText();
}
catch ( expAiException $e )
{
    $error = $e->translatedMessage();
}

$tpl = eZTemplate::factory();
if ( $record )
{
    $queued = expAiPackageService::queuedReport( $record );
    $report = $record->reportArray();
    if ( $queued )
        $report['import'] = $queued;
    $kernelPackage = $record->package();
    $tpl->setVariable( 'package', $record );
    $tpl->setVariable( 'kernel_package', $kernelPackage ? $kernelPackage : false );
    $tpl->setVariable( 'report', $report );
    $tpl->setVariable( 'provenance', $kernelPackage ? expAiPackageService::provenance( $kernelPackage ) : array() );
    $tpl->setVariable( 'queued', $queued );
}
else
{
    $limit = 25;
    $tpl->setVariable( 'packages', expAiPackageRecord::fetchList( $offset, $limit, $isAdmin ? false : (int) $user->attribute( 'contentobject_id' ) ) );
    $tpl->setVariable( 'count', expAiPackageRecord::fetchCount( $isAdmin ? false : (int) $user->attribute( 'contentobject_id' ) ) );
    $tpl->setVariable( 'offset', $offset );
    $tpl->setVariable( 'limit', $limit );
}
$tpl->setVariable( 'max_upload', expAiPackageService::limit( 'MaxUploadBytes', 52428800 ) );
$tpl->setVariable( 'queue_above', expAiPackageService::limit( 'QueueAbove', 20 ) );
$tpl->setVariable( 'queue', expAiQueue::available() );
$tpl->setVariable( 'notice', $notice );
$tpl->setVariable( 'error', $error );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:expai/packages.tpl' );
$Result['path'] = array( array( 'url' => 'expai/dashboard', 'text' => ezpI18n::tr( 'extension/expai/package', 'Exponential Intelligence' ) ),
                         array( 'url' => $record ? 'expai/packages' : false, 'text' => ezpI18n::tr( 'extension/expai/package', 'Content packages' ) ) );
if ( $record )
    $Result['path'][] = array( 'url' => false, 'text' => (string) $record->attribute( 'name' ) );
