#!/usr/bin/env php
<?php
/**
 * ./console ext:expai:package — Content packages: export, validate, diff, import (as drafts), checksum.
 *
 *   export   --subtree=<node> [--children-only] | --objects=<remote id,...>   [--languages=eng-US,ger-DE]
 *            [--no-files] [--allow-restricted] [--name=..] [--description=..] [--ai-generated] --out=<dir|file.exppkg>
 *   validate <package>                       the format, checksums, files, classes, attributes, languages, parents
 *   diff     <package> [--remap=<prefix>]    every attribute against the published content (nothing is written)
 *   import   <package> [--dry-run] [--on-conflict=skip|draft|fail] [--remap=<prefix>] [--parent=<node|remote id|path>]
 *            [--languages=..] [--queue] [--wait]   drafts only; --queue (or more than [PackageSettings] QueueAbove
 *            objects) runs it as a job group; --wait runs a worker until the group ended
 *   checksum <package> [--write]             prints (or writes) the manifest checksum of a package directory
 *   show     <uuid>                          a stored package and its last reports
 *   --login=<user>   the user (exports read, imports write drafts with this user's permissions; required for import)
 *   --json           the report as JSON
 *
 * <package> is a directory with package.json, a .exppkg/.zip file, or a package.json file.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 * @description Content packages: export, validate, diff, import as drafts, checksum
 */

require_once dirname( __FILE__ ) . '/../../../../autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => "Content packages of Exponential Intelligence: export, validate, diff, import (as drafts)\n",
                                     'use-session' => false, 'use-modules' => true, 'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions( '[subtree:][children-only][objects:][languages:][no-files][allow-restricted][name:][description:][ai-generated][out:]'
                              . '[dry-run][on-conflict:][remap:][parent:][queue][wait][write][login:][json]', '[command] [package]', array(
    'subtree' => 'export: the node of the subtree', 'children-only' => 'export: without the subtree\'s own object',
    'objects' => 'export: remote ids, separated by commas', 'languages' => 'only these translations (export, import, diff)',
    'no-files' => 'export: without images and files', 'allow-restricted' => 'export: content the data policy restricts (logged)',
    'name' => 'export: the package name', 'description' => 'export: a description', 'ai-generated' => 'export: mark the content as written by AI',
    'out' => 'export: a directory, or a file ending in .exppkg or .zip', 'dry-run' => 'import: plan only, write nothing',
    'on-conflict' => 'import: skip (default), draft or fail', 'remap' => 'import and diff: import as copies, remote ids remapped with this prefix',
    'parent' => 'import: the parent of objects whose parent is not in the package', 'queue' => 'import: run as a queue job group',
    'wait' => 'import --queue: run a worker until the group ended', 'write' => 'checksum: write it into package.json',
    'login' => 'the user', 'json' => 'print the report as JSON' ) );
$script->initialize();

$args = $options['arguments'];
$command = isset( $args[0] ) ? (string) $args[0] : '';
$path = isset( $args[1] ) ? (string) $args[1] : '';
$json = (bool) $options['json'];
$out = function ( $report, $lines ) use ( $cli, $json )
{
    if ( $json )
        $cli->output( json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    else
        foreach ( $lines as $line )
            $cli->output( $line );
};
$fail = function ( $text ) use ( $cli, $script )
{
    $cli->error( 'FAIL ' . $text );
    $script->shutdown( 1 );
};
$languages = $options['languages'] ? array_values( array_filter( array_map( 'trim', explode( ',', $options['languages'] ) ) ) ) : array();

if ( $options['login'] )
{
    $user = eZUser::fetchByName( $options['login'] );
    if ( !$user )
        $fail( 'no user ' . $options['login'] );
    eZUser::setCurrentlyLoggedInUser( $user, (int) $user->attribute( 'contentobject_id' ), eZUser::NO_SESSION_REGENERATE );
}
$policy = function ( $function ) use ( $fail )
{
    $access = eZUser::currentUser()->hasAccessTo( 'expai', $function );
    if ( $access['accessWord'] === 'no' )
        $fail( 'the user ' . eZUser::currentUser()->attribute( 'login' ) . ' lacks the policy expai/' . $function . ' (use --login)' );
};

try
{
    switch ( $command )
    {
        case 'export':
            $policy( 'package_export' );
            if ( !$options['out'] )
                $fail( 'name the output with --out=<directory or file.exppkg>' );
            $exporter = new expAiPackageExporter( array( 'languages' => $languages, 'files' => !$options['no-files'], 'allow_restricted' => (bool) $options['allow-restricted'],
                                                         'name' => (string) $options['name'], 'description' => (string) $options['description'],
                                                         'ai_generated' => (bool) $options['ai-generated'] ) );
            if ( $options['subtree'] )
                $objects = expAiPackageExporter::subtreeObjects( (int) $options['subtree'], (bool) $options['children-only'], expAiPackage::limit( 'MaxObjects', 2000 ) );
            elseif ( $options['objects'] )
            {
                $objects = array();
                foreach ( array_filter( array_map( 'trim', explode( ',', $options['objects'] ) ) ) as $rid )
                {
                    $object = eZContentObject::fetchByRemoteID( $rid );
                    if ( !$object )
                        $fail( 'no object with the remote id ' . $rid );
                    $objects[] = $object;
                }
            }
            else
                $fail( 'name --subtree=<node> or --objects=<remote ids>' );
            if ( $options['allow-restricted'] )
                eZDebug::writeNotice( 'expai: package export with --allow-restricted by ' . eZUser::currentUser()->attribute( 'login' ), 'expai package' );
            $package = $exporter->export( $objects );
            $written = $package->save( (string) $options['out'] );
            $report = array( 'written' => $written, 'objects' => count( $package->objects() ), 'files' => count( $package->files() ),
                             'checksum' => $package->statedChecksum(), 'warnings' => $exporter->warnings() );
            $lines = array( 'PASS exported ' . $report['objects'] . ' objects and ' . $report['files'] . ' files to ' . $written, 'checksum ' . $report['checksum'] );
            foreach ( $exporter->warnings() as $w )
                $lines[] = 'warning ' . $w;
            if ( $package->dir() !== '' && strpos( $package->dir(), expAiPackage::storageRoot() . '/tmp-' ) === 0 && realpath( $package->dir() ) !== realpath( $written ) )
                eZDir::recursiveDelete( $package->dir() );
            $out( $report, $lines );
            break;

        case 'validate':
            $package = expAiPackage::open( $path );
            register_shutdown_function( array( $package, 'release' ) );
            $report = ( new expAiPackageValidator( $package ) )->validate();
            $lines = array( ( $report['valid'] ? 'PASS' : 'FAIL' ) . ' ' . $report['objects'] . ' objects, ' . $report['files'] . ' files, '
                            . count( $report['errors'] ) . ' errors, ' . count( $report['warnings'] ) . ' warnings' );
            foreach ( $report['errors'] as $e )
                $lines[] = 'error   ' . $e;
            foreach ( $report['warnings'] as $w )
                $lines[] = 'warning ' . $w;
            $out( $report, $lines );
            $script->shutdown( $report['valid'] ? 0 : 1 );
            break;

        case 'diff':
            $package = expAiPackage::open( $path );
            register_shutdown_function( array( $package, 'release' ) );
            $report = ( new expAiPackageDiff( $package, array( 'remap' => (string) $options['remap'], 'languages' => $languages ) ) )->diff();
            $t = $report['totals'];
            $lines = array( sprintf( '%d objects: %d new, %d same, %d different, %d errors; %d differences', $t['objects'], $t['new'], $t['same'], $t['different'], $t['error'], $t['differences'] ) );
            foreach ( $report['objects'] as $o )
                foreach ( $o['languages'] as $lang => $l )
                    foreach ( $l['differences'] as $d )
                        $lines[] = sprintf( '%s %s %s: live %s | package %s', $o['target'], $lang, $d['attribute'], $d['live'], $d['package'] );
            $out( $report, $lines );
            break;

        case 'import':
            $policy( 'package_import' );
            if ( !$options['login'] )
                $fail( 'an import writes drafts as a user: name it with --login=<user>' );
            $importOptions = array( 'on_conflict' => $options['on-conflict'] ? (string) $options['on-conflict'] : 'skip', 'remap' => (string) $options['remap'],
                                    'parent' => (string) $options['parent'], 'languages' => $languages );
            if ( $options['dry-run'] )
            {
                $package = expAiPackage::open( $path );
            register_shutdown_function( array( $package, 'release' ) );
                $validation = ( new expAiPackageValidator( $package ) )->validate();
                if ( !$validation['valid'] )
                    $fail( 'the package is not valid: ' . implode( '; ', array_slice( $validation['errors'], 0, 5 ) ) );
                $result = ( new expAiPackageImporter( $package, $importOptions + array( 'dry_run' => true ) ) )->run();
                $diff = ( new expAiPackageDiff( $package, $importOptions ) )->diff();
                $lines = array( 'dry run: ' . json_encode( $result['counts'] ) . ', ' . $diff['totals']['differences'] . ' differences against the published content' );
                foreach ( $result['plan'] as $p )
                    $lines[] = sprintf( '%-8s %s %s%s', $p['action'], $p['target'], $p['class'], $p['reasons'] ? ' (' . implode( '; ', $p['reasons'] ) . ')' : '' );
                $out( array( 'plan' => $result['plan'], 'counts' => $result['counts'], 'diff' => $diff['totals'] ), $lines );
                break;
            }
            $record = expAiPackageService::keep( $path, 'cli', (int) eZUser::currentUserID() );
            $report = expAiPackageService::import( $record, $importOptions + array( 'queue' => (bool) $options['queue'] ) );
            $lines = array( 'package ' . $record->attribute( 'uuid' ) . ' (' . $record->attribute( 'object_count' ) . ' objects)' );
            if ( $report['queued'] )
            {
                $lines[] = 'queued as job group ' . $report['group'] . ' with ' . $report['jobs'] . ' jobs';
                if ( $options['wait'] )
                {
                    // the workers of the queue (systemd or the cron part expqueue) run the jobs; this only waits, up to an hour
                    $group = expQueue::resolveGroup( $report['group'] );
                    $until = time() + 3600;
                    while ( ( $progress = $group->progress() ) && $progress['active'] > 0 && time() < $until )
                    {
                        $cli->output( sprintf( 'waiting: %d of %d jobs ended', $progress['total'] - $progress['active'], $progress['total'] ) );
                        sleep( 10 );
                    }
                    $report = expAiPackageService::queuedReport( $record );
                    $lines[] = 'group: ' . json_encode( $report['progress'] ) . ', results ' . json_encode( $report['counts'] );
                }
            }
            else
                $lines[] = ( $report['imported'] ? 'PASS imported: ' : 'FAIL nothing imported: ' ) . json_encode( $report['counts'] );
            foreach ( $report['results'] as $r )
                foreach ( $r['drafts'] as $d )
                    if ( $d['version'] )
                        $lines[] = sprintf( 'draft %d/%d %s %s', $d['object_id'], $d['version'], $d['language'], $d['url'] );
            $out( $report + array( 'package_uuid' => (string) $record->attribute( 'uuid' ) ), $lines );
            break;

        case 'checksum':
            $package = expAiPackage::open( $path );
            register_shutdown_function( array( $package, 'release' ) );
            $sum = $package->computeChecksum();
            if ( $options['write'] )
            {
                if ( !is_dir( $path ) )
                    $fail( '--write needs a package directory' );
                $data = $package->data();
                unset( $data['checksum'] );
                $package = expAiPackage::fromArray( $data, $path );
                file_put_contents( rtrim( $path, '/' ) . '/package.json', $package->encode( true ) );
                $cli->output( 'PASS wrote checksum ' . expAiPackage::open( $path )->statedChecksum() );
            }
            else
                $cli->output( $sum . ( $package->statedChecksum() === $sum ? ' (matches)' : ' (package.json says ' . ( $package->statedChecksum() ?: 'nothing' ) . ')' ) );
            break;

        case 'show':
            $record = expAiPackageRecord::fetchByUUID( $path );
            if ( !$record )
                $fail( 'no stored package ' . $path );
            $queued = expAiPackageService::queuedReport( $record );
            $report = $record->reportArray();
            if ( $queued )
                $report['import'] = $queued;
            $out( array( 'package' => $record->attribute( 'name' ), 'state' => $record->attribute( 'state' ), 'report' => $report ),
                  array( $record->attribute( 'uuid' ) . ' ' . $record->attribute( 'name' ) . ': ' . $record->attribute( 'state' ) . ', ' . $record->attribute( 'object_count' ) . ' objects',
                         'reports: ' . implode( ', ', array_keys( $report ) ) . ( isset( $report['import']['counts'] ) ? ' import ' . json_encode( $report['import']['counts'] ) : '' ) ) );
            break;

        default:
            $fail( 'a command: export, validate, diff, import, checksum or show (see --help)' );
    }
}
catch ( expAiPackageException $e )
{
    $fail( $e->text() );
}
catch ( expAiException $e )
{
    $fail( strtr( $e->getMessage(), $e->arguments() ) . ( $e->detail() !== '' ? ' (' . $e->detail() . ')' : '' ) );
}
$script->shutdown( 0 );
