<?php
/**
 * File containing the expAiSchema class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The two tables of expai, defined once here in the kernel's generic schema format. share/db_schema.dba and
 * sql/<engine>/schema.sql are generated from it (bin/php/expaischema.php), and ensure() creates missing tables in
 * the current database with the kernel's schema handler of that database (MySQL, PostgreSQL, SQLite, Oracle,
 * MongoDB). Names stay within Oracle's limits; no column is an Oracle reserved word.
 *
 *   expai_draft_log     one row per version the assistant wrote or filled ("Exponential Intelligence" label)
 *   expai_conversation  the conversation of one user on one node (the full view panel)
 */
class expAiSchema
{
    /** @return array generic schema (without _info) */
    public static function definition()
    {
        $int = array( 'length' => 11, 'type' => 'int', 'not_null' => '1', 'default' => 0 );
        $varchar = function ( $length, $default = '' )
        {
            return array( 'length' => $length, 'type' => 'varchar', 'not_null' => '1', 'default' => $default );
        };
        $primary = array( 'type' => 'primary', 'fields' => array( 'id' ) );
        return array(
            'expai_conversation' => array(
                'name' => 'expai_conversation',
                'fields' => array(
                    'created' => $int,
                    'draft_count' => $int,
                    'id' => array( 'type' => 'auto_increment', 'default' => false ),
                    'iterations' => $int,
                    'messages' => array( 'type' => 'longtext', 'default' => false ),
                    'model' => $varchar( 100 ),
                    'modified' => $int,
                    'node_id' => $int,
                    'turn_count' => $int,
                    'user_id' => $int,
                ),
                'indexes' => array(
                    'PRIMARY' => $primary,
                    'expai_conversation_user_node' => array( 'type' => 'non-unique', 'fields' => array( 'user_id', 'node_id' ) ),
                ),
            ),
            'expai_usage_log' => array(
                'name' => 'expai_usage_log',
                'fields' => array(
                    'action' => $varchar( 40 ),
                    'cache_read_tokens' => $int,
                    'cache_write_tokens' => $int,
                    'conversation_id' => $int,
                    'created' => $int,
                    'drafts_written' => $int,
                    'error_class' => $varchar( 60 ),
                    'error_message' => $varchar( 255 ),
                    'http_status' => $int,
                    'id' => array( 'type' => 'auto_increment', 'default' => false ),
                    'input_tokens' => $int,
                    'iteration' => $int,
                    'latency_ms' => $int,
                    'model' => $varchar( 100 ),
                    'node_id' => $int,
                    'output_tokens' => $int,
                    'provider' => $varchar( 40, 'anthropic' ),
                    'rate_limits' => array( 'type' => 'longtext', 'default' => false ),
                    'request_debug' => array( 'type' => 'longtext', 'default' => false ),
                    'retries' => $int,
                    'session_key' => $varchar( 40 ),
                    'stop_reason' => $varchar( 30 ),
                    'tool_calls' => array( 'type' => 'longtext', 'default' => false ),
                    'tool_count' => $int,
                    'tool_ms' => $int,
                    'turn' => $int,
                    'user_id' => $int,
                ),
                'indexes' => array(
                    'PRIMARY' => $primary,
                    'expai_usage_log_created' => array( 'type' => 'non-unique', 'fields' => array( 'created' ) ),
                    'expai_usage_log_user' => array( 'type' => 'non-unique', 'fields' => array( 'user_id', 'created' ) ),
                    'expai_usage_log_turn' => array( 'type' => 'non-unique', 'fields' => array( 'conversation_id', 'turn' ) ),
                ),
            ),
            'expai_draft_log' => array(
                'name' => 'expai_draft_log',
                'fields' => array(
                    'action' => $varchar( 20 ),
                    'contentobject_id' => $int,
                    'conversation_id' => $int,
                    'created' => $int,
                    'id' => array( 'type' => 'auto_increment', 'default' => false ),
                    'language_code' => $varchar( 20 ),
                    'model' => $varchar( 100 ),
                    'modified' => $int,
                    'node_id' => $int,
                    'parent_node_id' => $int,
                    'prompt_summary' => $varchar( 255 ),
                    'source' => $varchar( 20 ),
                    'status' => $varchar( 20, 'draft' ),
                    'user_id' => $int,
                    'version' => $int,
                ),
                'indexes' => array(
                    'PRIMARY' => $primary,
                    'expai_draft_log_object' => array( 'type' => 'non-unique', 'fields' => array( 'contentobject_id', 'version' ) ),
                    'expai_draft_log_user' => array( 'type' => 'non-unique', 'fields' => array( 'user_id', 'status' ) ),
                    'expai_draft_log_node' => array( 'type' => 'non-unique', 'fields' => array( 'node_id' ) ),
                ),
            ),
        );
    }

    /** @return string[] table names */
    public static function tables()
    {
        return array_keys( self::definition() );
    }

    /** @return string the share/db_schema.dba file content */
    public static function dbaText()
    {
        $schema = self::definition();
        ksort( $schema );
        $schema['_info'] = array( 'format' => 'generic' );
        return "<?php\n"
             . "// The database schema of expai (Exponential Intelligence): generated from expAiSchema::definition() by\n"
             . "// extension/expai/bin/php/expaischema.php --write-files. Do not edit by hand.\n"
             . '$schema = ' . var_export( $schema, true ) . ";\n\n?>\n";
    }

    /**
     * The schema handler for an engine (mysql, postgresql, sqlite, oracle, mongodb).
     * @return eZDBSchemaInterface|null
     */
    public static function handler( $engine, array $schema, $db = null )
    {
        $classes = array( 'mysql' => 'eZMysqlSchema', 'postgresql' => 'eZPgsqlSchema', 'sqlite' => 'eZSQLiteSchema',
                          'oracle' => 'eZOracleSchema', 'mongodb' => 'expMongoSchema' );
        if ( !isset( $classes[$engine] ) )
            return null;
        $class = $classes[$engine];
        if ( !class_exists( $class ) && $engine === 'oracle' )
        {
            $file = eZExtension::baseDirectory() . '/ezoracle/ezdb/dbms-schema/ezoracleschema.php';
            if ( is_file( $file ) )
                require_once $file;
        }
        if ( !class_exists( $class ) )
            return null;
        return new $class( array( 'schema' => $schema, 'instance' => $db, 'type' => $engine ) );
    }

    /** @return string[] SQL statements of the engine for the given tables (all when empty) */
    public static function sqlStatements( $engine, array $tables = array() )
    {
        $schema = self::definition();
        $handler = self::handler( $engine, $schema );
        if ( !$handler )
            return array();
        $local = $schema;
        $local['_info'] = array( 'format' => 'generic' );
        $handler->transformSchema( $local, true );
        unset( $local['_info'] );
        $out = array();
        foreach ( $local as $table => $def )
        {
            if ( $tables && !in_array( $table, $tables, true ) )
                continue;
            foreach ( $handler->generateTableSQLList( $table, $def, array( 'format' => 'local', 'table_type' => $engine === 'mysql' ? 'innodb' : '' ), false ) as $statement )
            {
                $statement = trim( $statement );
                // PL/SQL blocks (Oracle sequences and triggers) end in "END;", which belongs to them
                if ( !preg_match( '/^(BEGIN\b|CREATE OR REPLACE TRIGGER\b)/i', $statement ) )
                    $statement = rtrim( $statement, ';' );
                if ( $statement !== '' )
                    $out[] = $statement;
            }
        }
        return $out;
    }

    /** @return string engine name of a database connection */
    public static function engineOf( eZDBInterface $db )
    {
        $name = strtolower( (string) $db->databaseName() );
        if ( $name === 'postgresql' || $name === 'pgsql' )
            return 'postgresql';
        if ( strpos( $name, 'sqlite' ) === 0 )
            return 'sqlite';
        if ( strpos( $name, 'mongo' ) === 0 )
            return 'mongodb';
        return in_array( $name, array( 'mysql', 'oracle' ), true ) ? $name : $name;
    }

    /** @return string[] the expai tables missing in the database */
    public static function missingTables( $db = null )
    {
        $db = $db ?: eZDB::instance();
        return array_values( array_diff( self::tables(), self::existingTables( $db ) ) );
    }

    /**
     * The expai tables that exist. eZTableList() of the drivers lists only tables named ez*, so each engine is
     * asked directly.
     * @return string[]
     */
    public static function existingTables( $db = null )
    {
        $db = $db ?: eZDB::instance();
        $engine = self::engineOf( $db );
        if ( $engine === 'mongodb' && method_exists( $db, 'listCollectionNames' ) )
            $names = $db->listCollectionNames();
        else
        {
            switch ( $engine )
            {
                case 'sqlite':
                    $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'expai%'";
                    break;
                case 'mysql':
                    $sql = "SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'expai%'";
                    break;
                case 'postgresql':
                    $sql = "SELECT relname AS name FROM pg_class WHERE relkind = 'r' AND relname LIKE 'expai%'";
                    break;
                case 'oracle':
                    $sql = "SELECT LOWER(table_name) AS name FROM user_tables WHERE table_name LIKE 'EXPAI%'";
                    break;
                default:
                    return array_map( 'strtolower', array_keys( $db->eZTableList() ) );
            }
            $names = array();
            foreach ( (array) $db->arrayQuery( $sql ) as $row )
            {
                $row = array_change_key_case( $row, CASE_LOWER );
                $names[] = isset( $row['name'] ) ? $row['name'] : reset( $row );
            }
        }
        return array_values( array_intersect( self::tables(), array_map( 'strtolower', (array) $names ) ) );
    }

    /**
     * Creates the missing expai tables in the database (idempotent).
     * @return array( string[] created, string[] errors )
     */
    public static function ensure( $db = null )
    {
        $db = $db ?: eZDB::instance();
        $missing = self::missingTables( $db );
        if ( !$missing )
            return array( array(), array() );
        $engine = self::engineOf( $db );
        $created = array();
        $errors = array();
        if ( $engine === 'mongodb' )
        {
            $schema = array_intersect_key( self::definition(), array_flip( $missing ) );
            $handler = self::handler( 'mongodb', $schema, $db );
            if ( $handler && method_exists( $handler, 'insertSchema' ) && $handler->insertSchema( array( 'schema' => true, 'data' => false ) ) )
                $created = $missing;
            else
                $errors[] = 'mongodb: the schema handler could not create ' . implode( ', ', $missing );
            return array( $created, $errors );
        }
        $statements = self::sqlStatements( $engine, $missing );
        if ( !$statements )
            return array( array(), array( "no schema handler for the engine $engine" ) );
        foreach ( $statements as $statement )
        {
            if ( $db->query( $statement ) === false )
                $errors[] = $engine . ': ' . substr( $statement, 0, 80 );
        }
        $remaining = self::missingTables( $db );
        return array( array_values( array_diff( $missing, $remaining ) ), $errors );
    }
}
