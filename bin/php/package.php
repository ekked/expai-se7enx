#!/usr/bin/env php
<?php
/**
 * ./console ext:expai:package — content packages of Exponential Intelligence: a thin wrapper on the kernel's packages
 * (the classic XML or the JSON format, doc/bc/6.0/package-json-format.md of Exponential; ezpm does the rest).
 *
 *   export   --subtree=<node> --out=<dir> [--name=..] [--summary=..] [--languages=eng-US,ger-DE] [--format=json|xml]
 *            [--ai-generated] [--models=a,b] [--tools=a,b] [--notes=..] [--allow-restricted]
 *            a kernel package of the subtree (JSON by default) with its provenance as package metadata
 *   validate <archive>                       the kernel's checks (schema, documents, checksums), nothing imported
 *   check    <archive>                       keeps the package and reports what an install meets here, by remote id
 *   import   <archive> [--dry-run] [--existing=update|skip] [--parent=<node|remote id|path>] [--queue] [--wait]
 *            drafts only (the kernel's install as drafts); --queue (or more than [PackageSettings] QueueAbove
 *            objects) runs it as a queue job; --wait waits for it
 *   show     <uuid>                          a kept package and its last reports
 *   --login=<user>   the user (exports read, imports write drafts with this user's permissions; required for import)
 *   --json           the report as JSON
 *
 * Kernel equivalents: ezpm export --format=json, ezpm validate, ezpm check, ezpm install --as-drafts.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 * @description Content packages: export with provenance, validate, check, import as drafts
 */

require_once dirname( __FILE__ ) . '/../../../../autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => "Content packages of Exponential Intelligence: export, validate, check, import (as drafts)\n",
                                     'use-session' => false, 'use-modules' => true, 'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions( '[subtree:][out:][name:][summary:][languages:][format:][ai-generated][models:][tools:][notes:][allow-restricted]'
                              . '[dry-run][existing:][parent:][queue][wait][login:][json]', '[command] [package]', array(
    'subtree' => 'export: the node of the subtree', 'out' => 'export: the directory the archive is written to',
    'name' => 'export: the package name', 'summary' => 'export: its summary', 'languages' => 'export: only these translations',
    'format' => 'export: json (default) or xml', 'ai-generated' => 'export: mark the content as written by AI',
    'models' => 'export: the models that wrote it', 'tools' => 'export: the tools used', 'notes' => 'export: a note',
    'allow-restricted' => 'export: content the data policy restricts (logged)', 'dry-run' => 'import: report only, write nothing',
    'existing' => 'import: an existing object gets a new draft (update, default) or is left alone (skip)',
    'parent' => 'import: the parent of the package\'s top objects', 'queue' => 'import: run as a queue job',
    'wait' => 'import --queue: wait until the job ended', 'login' => 'the user', 'json' => 'print the report as JSON' ) );
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
$list = function ( $value ) { return $value ? array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) ) : array(); };

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
$keep = function ( $path ) use ( $fail, $policy )
{
    $policy( 'package_import' );
    if ( !is_file( $path ) )
        $fail( 'no archive ' . $path );
    return expAiPackageService::keep( $path, 'cli', (int) eZUser::currentUserID() );
};
$validationLines = function ( array $v )
{
    $lines = array( ( $v['valid'] ? 'PASS' : 'FAIL' ) . ' ' . ( isset( $v['format'] ) ? $v['format'] : '' ) . ' package, ' . count( $v['errors'] ) . ' errors, ' . count( $v['warnings'] ) . ' warnings' );
    foreach ( $v['errors'] as $e )
        $lines[] = 'error   ' . $e;
    foreach ( $v['warnings'] as $w )
        $lines[] = 'warning ' . $w;
    return $lines;
};
$reportLines = function ( array $report )
{
    $lines = array( $report['object_total'] . ' objects: ' . json_encode( $report['counts'] ) );
    foreach ( $report['objects'] as $row )
    {
        $where = array();
        foreach ( $row['locations'] as $l )
            $where[] = ( $l['parent_remote_id'] !== '' ? $l['parent_remote_id'] : 'top' ) . ': ' . $l['parent'];
        $lines[] = sprintf( '%-8s %s %s %s', $row['action'], $row['remote_id'], $row['class_identifier'], implode( '; ', $where ) );
    }
    return $lines;
};

try
{
    switch ( $command )
    {
        case 'export':
            $policy( 'package_export' );
            if ( !$options['subtree'] || !$options['out'] )
                $fail( 'name the subtree with --subtree=<node> and the output directory with --out=<dir>' );
            if ( $options['allow-restricted'] )
                eZDebug::writeNotice( 'expai: package export with --allow-restricted by ' . eZUser::currentUser()->attribute( 'login' ), 'expai package' );
            $result = expAiPackageService::export( (int) $options['subtree'], (string) $options['out'], array(
                'name' => (string) $options['name'], 'summary' => (string) $options['summary'], 'languages' => $list( $options['languages'] ),
                'format' => $options['format'] === 'xml' ? 'xml' : 'json', 'allow_restricted' => (bool) $options['allow-restricted'],
                'provenance' => array( 'ai_generated' => (bool) $options['ai-generated'], 'models' => $list( $options['models'] ), 'tools' => $list( $options['tools'] ),
                                       'human_reviewed' => $options['ai-generated'] ? false : null, 'notes' => (string) $options['notes'] ) ) );
            $out( $result, array( 'PASS exported ' . $result['objects'] . ' objects as ' . $result['format'] . ' package ' . $result['name'] . ' to ' . $result['file'] ) );
            break;

        case 'validate':
            if ( !is_file( $path ) && !is_dir( $path ) )
                $fail( 'no archive or directory ' . $path );
            if ( is_dir( $path ) )
                $report = expPackageJson::validateDirectory( rtrim( $path, '/' ) );
            else
            {
                $work = expPackageJson::workDirectory( 'expai-validate' );
                $report = expPackageJson::extractArchive( $path, $work . '/pkg', $error ) ? expPackageJson::validateDirectory( $work . '/pkg' )
                                                                                          : array( 'valid' => false, 'errors' => array( $error ), 'warnings' => array() );
                eZDir::recursiveDelete( $work );
            }
            $out( $report, $validationLines( $report ) );
            $script->shutdown( $report['valid'] ? 0 : 1 );
            break;

        case 'check':
            $record = $keep( $path );
            $v = $record->reportArray()['validation'];
            if ( !$v['valid'] )
            {
                $out( $v, $validationLines( $v ) );
                $script->shutdown( 1 );
            }
            $report = expAiPackageService::dryRun( $record );
            $out( $report + array( 'package_uuid' => (string) $record->attribute( 'uuid' ) ),
                  array_merge( array( 'package ' . $record->attribute( 'uuid' ) . ' (' . $record->attribute( 'storage' ) . ')' ), $reportLines( $report ) ) );
            break;

        case 'import':
            if ( !$options['login'] )
                $fail( 'an import writes drafts as a user: name it with --login=<user>' );
            $record = $keep( $path );
            $v = $record->reportArray()['validation'];
            if ( !$v['valid'] )
            {
                $out( $v, $validationLines( $v ) );
                $script->shutdown( 1 );
            }
            $importOptions = array( 'existing' => $options['existing'] === 'skip' ? 'skip' : 'update', 'parent' => (string) $options['parent'] );
            if ( $options['dry-run'] )
            {
                $report = expAiPackageService::dryRun( $record, $importOptions );
                $out( $report, array_merge( array( 'dry run, nothing written:' ), $reportLines( $report ) ) );
                break;
            }
            $report = expAiPackageService::import( $record, $importOptions + array( 'queue' => (bool) $options['queue'] ) );
            $lines = array( 'package ' . $record->attribute( 'uuid' ) . ' (' . $record->attribute( 'object_count' ) . ' objects)' );
            if ( $report['queued'] )
            {
                $lines[] = 'queued as job group ' . $report['group'];
                if ( $options['wait'] )
                {
                    // the workers of the queue (systemd or the cron part expqueue) run the job; this only waits, up to an hour
                    $group = expQueue::resolveGroup( $report['group'] );
                    $until = time() + 3600;
                    while ( ( $progress = $group->progress() ) && $progress['active'] > 0 && time() < $until )
                    {
                        $cli->output( 'waiting for the job' );
                        sleep( 10 );
                    }
                    $report = expAiPackageService::queuedReport( $record );
                    $lines[] = ( $report['imported'] ? 'PASS' : 'FAIL' ) . ' job ended: ' . json_encode( $report['counts'] );
                }
            }
            else
                $lines[] = ( $report['imported'] ? 'PASS imported as drafts: ' : 'FAIL nothing imported: ' ) . json_encode( $report['counts'] ) . ( $report['error'] !== '' ? ' ' . $report['error'] : '' );
            foreach ( $report['results'] as $r )
                if ( $r['version'] )
                    $lines[] = sprintf( 'draft %d/%d %s %s', $r['object_id'], $r['version'], $r['remote_id'], $r['url'] );
            $out( $report + array( 'package_uuid' => (string) $record->attribute( 'uuid' ) ), $lines );
            break;

        case 'show':
            $record = expAiPackageRecord::fetchByUUID( $path );
            if ( !$record )
                $fail( 'no kept package ' . $path );
            $queued = expAiPackageService::queuedReport( $record );
            $report = $record->reportArray();
            if ( $queued )
                $report['import'] = $queued;
            $out( array( 'package' => $record->attribute( 'name' ), 'kernel_package' => $record->attribute( 'storage' ), 'state' => $record->attribute( 'state' ), 'report' => $report ),
                  array( $record->attribute( 'uuid' ) . ' ' . $record->attribute( 'name' ) . ' (' . $record->attribute( 'storage' ) . ', ' . $record->attribute( 'format_version' ) . '): '
                         . $record->attribute( 'state' ) . ', ' . $record->attribute( 'object_count' ) . ' objects',
                         'reports: ' . implode( ', ', array_keys( $report ) ) . ( isset( $report['import']['counts'] ) ? ' import ' . json_encode( $report['import']['counts'] ) : '' ) ) );
            break;

        default:
            $fail( 'a command: export, validate, check, import or show (see --help)' );
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
