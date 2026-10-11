#!/usr/bin/env php
<?php
/**
 * The tables of expai (expai_draft_log, expai_conversation, expai_usage_log, expai_probe_result).
 *
 *   php extension/expai/bin/php/expaischema.php --install [-s <siteaccess>]   creates the missing tables, columns and
 *                                                                             indexes in the siteaccess's database
 *                                                                             (idempotent; also the upgrade)
 *   php extension/expai/bin/php/expaischema.php --check  [-s <siteaccess>]    says which tables, columns and indexes
 *                                                                             are missing
 *   php extension/expai/bin/php/expaischema.php --print=<engine>              prints the SQL of mysql, postgresql,
 *                                                                             sqlite or oracle
 *   php extension/expai/bin/php/expaischema.php --write-files                 rewrites share/db_schema.dba and
 *                                                                             sql/<engine>/schema.sql
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

require_once dirname( __FILE__ ) . '/../../../../autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => "The tables of expai (Exponential Intelligence)\n",
                                     'use-session' => false, 'use-modules' => false, 'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions( '[install][check][print:][write-files]', '', array(
    'install' => 'Create the missing expai tables, columns and indexes in the database of the siteaccess (idempotent)',
    'check' => 'List the expai tables, columns and indexes that are missing in the database of the siteaccess',
    'print' => 'Print the SQL for an engine: mysql, postgresql, sqlite or oracle',
    'write-files' => 'Rewrite share/db_schema.dba and sql/<engine>/schema.sql from expAiSchema::definition()' ) );
$script->initialize();

$failed = false;
if ( $options['print'] )
{
    $statements = expAiSchema::sqlStatements( $options['print'] );
    if ( !$statements )
    {
        $cli->error( 'FAIL no schema handler for ' . $options['print'] );
        $failed = true;
    }
    foreach ( $statements as $statement )
        $cli->output( $statement . ';' );
}
elseif ( $options['write-files'] )
{
    $base = eZExtension::baseDirectory() . '/expai';
    file_put_contents( "$base/share/db_schema.dba", expAiSchema::dbaText() );
    $cli->output( "PASS wrote share/db_schema.dba" );
    foreach ( array( 'mysql', 'postgresql', 'sqlite', 'oracle' ) as $engine )
    {
        $statements = expAiSchema::sqlStatements( $engine );
        if ( !$statements )
        {
            $cli->error( "FAIL no schema handler for $engine" );
            $failed = true;
            continue;
        }
        $text = "-- expai (Exponential Intelligence): the tables expai_conversation, expai_draft_log, expai_probe_result and expai_usage_log ($engine).\n"
              . "-- Generated from expAiSchema::definition() by bin/php/expaischema.php --write-files with the kernel's\n"
              . "-- schema handler; bin/php/expaischema.php --install creates the same tables in a running installation.\n\n";
        foreach ( $statements as $statement )
            $text .= $statement . ( substr( $statement, -1 ) === ';' ? "\n" : ";\n" ) . ( preg_match( '/^CREATE (TABLE|OR REPLACE TRIGGER)/i', $statement ) ? "\n" : '' );
        if ( $engine === 'oracle' )
            $text = preg_replace( '/^(END;)$/m', "$1\n/", $text );
        if ( !is_dir( "$base/sql/$engine" ) )
            mkdir( "$base/sql/$engine", 0755, true );
        file_put_contents( "$base/sql/$engine/schema.sql", $text );
        $cli->output( "PASS wrote sql/$engine/schema.sql (" . count( $statements ) . " statements)" );
    }
    // MongoDB has no fixed columns: the collections and their indexes
    $collections = array();
    $schema = expAiSchema::definition();
    ksort( $schema );
    foreach ( $schema as $table => $def )
    {
        $indexes = array();
        foreach ( $def['indexes'] as $name => $index )
        {
            $keys = array();
            foreach ( $index['fields'] as $field )
                $keys[$field] = 1;
            $indexes[] = array( 'keys' => $keys, 'options' => $name === 'PRIMARY' ? array( 'unique' => true, 'name' => 'PRIMARY' ) : array( 'name' => $name ) );
        }
        $collections[] = array( 'name' => $table, 'indexes' => $indexes );
    }
    $json = array( 'database' => 'expai',
                   'comment' => "expai (Exponential Intelligence) on MongoDB: the collections and indexes the kernel's MongoDB schema handler (expMongoSchema) creates from share/db_schema.dba; bin/php/expaischema.php --install does it in a running installation. Generated by bin/php/expaischema.php --write-files.",
                   'collections' => $collections );
    file_put_contents( "$base/sql/mongodb/schema.json", json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
    $cli->output( 'PASS wrote sql/mongodb/schema.json (' . count( $collections ) . ' collections)' );
}
else
{
    $db = eZDB::instance();
    $cli->output( 'database engine: ' . expAiSchema::engineOf( $db ) );
    if ( $options['install'] )
    {
        list( $created, $errors ) = expAiSchema::ensure( $db );
        foreach ( $created as $table )
            $cli->output( "created $table" );
        foreach ( $errors as $error )
            $cli->error( "error: $error" );
        $failed = (bool) $errors;
        // threads: the panel conversations of an earlier expai become threads (idempotent; bin/php/expaithreads.php)
        if ( !$errors && expAiThread::available() )
        {
            do
            {
                $report = expAiThreadMigration::run( false );
                if ( $report['created'] || $report['linked'] )
                    $cli->output( "threads: {$report['created']} created, {$report['linked']} linked from panel conversations" );
            }
            while ( $report['created'] + $report['linked'] > 0 && $report['candidates'] >= 500 );
        }
    }
    elseif ( expAiThread::available() && ( $pendingThreads = expAiThreadMigration::pending() ) )
        $cli->output( "threads: $pendingThreads panel conversation(s) are not threads yet (--install migrates them)" );
    $missing = array_merge( expAiSchema::missingTables( $db ), expAiSchema::missingColumns( $db ) );
    if ( $missing )
    {
        $cli->output( 'FAIL missing: ' . implode( ', ', $missing ) );
        if ( !$options['install'] )
            $cli->output( 'run with --install to add them' );
        $failed = true;
    }
    else
        $cli->output( 'PASS the tables ' . implode( ', ', expAiSchema::tables() ) . ' exist with every column and index' );
}
$script->shutdown( $failed ? 1 : 0 );
