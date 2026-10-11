<?php
/**
 * expai/packages (policy expai/package_import): content packages. Upload a package (a .exppkg / .zip file or a
 * package.json), see its validation report, make a dry run (what an import would do, and the differences against the
 * published content), import it as drafts (in the request, or as a queue job group for a large package) and follow
 * the links to the drafts. Nothing is published here.
 *
 * View parameters: (package)/<uuid> one package, (offset)/<n> the list. POST forms carry the form token (ezformtoken).
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$http = eZHTTPTool::instance();
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
    $out = array( 'on_conflict' => in_array( (string) $Module->actionParameter( 'OnConflict' ), array( 'skip', 'draft', 'fail' ), true )
                                   ? (string) $Module->actionParameter( 'OnConflict' ) : 'skip' );
    $remap = trim( (string) $Module->actionParameter( 'Remap' ) );
    if ( $remap !== '' && preg_match( '/^[A-Za-z0-9_.:-]{1,40}$/', $remap ) )
        $out['remap'] = $remap;
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
        $max = expAiPackage::limit( 'MaxUploadBytes', 52428800 );
        if ( !$file || (int) $file->attribute( 'filesize' ) <= 0 )
            throw new expAiPackageException( 'Choose a package file to upload.' );
        if ( (int) $file->attribute( 'filesize' ) > $max )
            throw new expAiPackageException( 'The file is larger than %max bytes; import larger packages with ./console ext:expai:package import.', array( '%max' => $max ) );
        $record = expAiPackageService::keep( $file->attribute( 'filename' ), 'upload', (int) $user->attribute( 'contentobject_id' ) );
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
            $notice = ezpI18n::tr( 'extension/expai/package', 'The import runs in the job queue (%jobs jobs). This page shows its progress.', null, array( '%jobs' => $report['jobs'] ) );
        elseif ( $report['imported'] )
            $notice = ezpI18n::tr( 'extension/expai/package', 'Imported as drafts: review them below and send them for publishing.' );
        else
            $error = ezpI18n::tr( 'extension/expai/package', 'Nothing was imported: the package has conflicts or errors and "on conflict" is "fail".' );
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
    $data = array();
    try
    {
        $data = $record->package()->data();
    }
    catch ( expAiPackageException $e )
    {
        $error = $error !== '' ? $error : $e->translatedText();
    }
    $tpl->setVariable( 'package', $record );
    $tpl->setVariable( 'report', $report );
    $tpl->setVariable( 'meta', array( 'package' => isset( $data['package'] ) ? (array) $data['package'] : array(),
                                      'provenance' => isset( $data['provenance'] ) ? (array) $data['provenance'] : array() ) );
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
$tpl->setVariable( 'max_upload', expAiPackage::limit( 'MaxUploadBytes', 52428800 ) );
$tpl->setVariable( 'queue_above', expAiPackage::limit( 'QueueAbove', 20 ) );
$tpl->setVariable( 'queue', expAiQueue::available() );
$tpl->setVariable( 'notice', $notice );
$tpl->setVariable( 'error', $error );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:expai/packages.tpl' );
$Result['path'] = array( array( 'url' => 'expai/dashboard', 'text' => ezpI18n::tr( 'extension/expai/package', 'Exponential Intelligence' ) ),
                         array( 'url' => $record ? 'expai/packages' : false, 'text' => ezpI18n::tr( 'extension/expai/package', 'Content packages' ) ) );
if ( $record )
    $Result['path'][] = array( 'url' => false, 'text' => (string) $record->attribute( 'name' ) );
