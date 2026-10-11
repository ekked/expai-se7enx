#!/usr/bin/env php
<?php
/**
 * The tables of expai (expai_draft_log, expai_conversation).
 *
 *   php extension/expai/bin/php/expaischema.php --install [-s <siteaccess>]   creates the missing tables in the
 *                                                                             siteaccess's database (idempotent)
 *   php extension/expai/bin/php/expaischema.php --check  [-s <siteaccess>]    says which tables are missing
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
    'install' => 'Create the missing expai tables in the database of the siteaccess (idempotent)',
    'check' => 'List the expai tables that are missing in the database of the siteaccess',
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
        $text = "-- expai (Exponential Intelligence): expai_draft_log and expai_conversation ($engine).\n"
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
    }
    $missing = expAiSchema::missingTables( $db );
    if ( $missing )
    {
        $cli->output( 'FAIL missing: ' . implode( ', ', $missing ) );
        $failed = true;
    }
    else
        $cli->output( 'PASS the tables ' . implode( ', ', expAiSchema::tables() ) . ' exist' );
}
$script->shutdown( $failed ? 1 : 0 );
