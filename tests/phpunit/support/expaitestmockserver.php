<?php
/**
 * File containing the expAiTestMockServer class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The mock servers of tests/mock (server.php, batch_server.php, chain_server.php) or a directory of files, each on
 * PHP's built-in web server on a free loopback port, started once per process and stopped when the process ends.
 * Their state directory is below var/tmp/expai-tests of the installation (EXPAI_TEST_TMP names another directory)
 * and is removed with the server.
 */
class expAiTestMockServer
{
    /** @var array name => array( 'process', 'url', 'state' ) */
    private static $servers = array();

    /** @var bool */
    private static $shutdownRegistered = false;

    /**
     * @param string $router a file of tests/mock (server.php ...), or '' to serve the files of $docroot
     * @param string|null $docroot
     * @return array array( 'url' => 'http://127.0.0.1:<port>', 'state' => <directory> )
     */
    public static function start( $router, $docroot = null )
    {
        $name = $router . '|' . (string) $docroot;
        if ( isset( self::$servers[$name] ) )
            return self::$servers[$name];

        $state = self::tmp( 'mock-' . preg_replace( '/[^a-z0-9]+/i', '-', basename( $router ?: (string) $docroot ) ) );
        @mkdir( $state . '/batches', 0775, true );
        @mkdir( $state . '/files', 0775, true );
        file_put_contents( $state . '/requests.jsonl', '' );

        $probe = stream_socket_server( 'tcp://127.0.0.1:0', $errno, $error );
        if ( !$probe )
            throw new RuntimeException( "no free port: $error" );
        $port = (int) substr( strrchr( stream_socket_get_name( $probe, false ), ':' ), 1 );
        fclose( $probe );

        $command = array( PHP_BINARY, '-S', '127.0.0.1:' . $port );
        if ( $docroot !== null )
        {
            $command[] = '-t';
            $command[] = $docroot;
        }
        if ( $router !== '' )
            $command[] = EXPAI_TEST_CHECKOUT . '/tests/mock/' . $router;
        $env = array( 'EXPAI_MOCK_STATE' => $state, 'PATH' => (string) getenv( 'PATH' ) );
        $process = proc_open( $command, array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'file', $state . '/server.log', 'a' ),
                                               2 => array( 'file', $state . '/server.log', 'a' ) ), $pipes, EXPAI_TEST_ROOT, $env );
        if ( !is_resource( $process ) )
            throw new RuntimeException( 'the mock server did not start' );

        $deadline = microtime( true ) + 10;
        while ( microtime( true ) < $deadline )
        {
            $socket = @fsockopen( '127.0.0.1', $port, $errno, $error, 0.2 );
            if ( $socket )
            {
                fclose( $socket );
                break;
            }
            usleep( 50000 );
        }

        if ( !self::$shutdownRegistered )
        {
            self::$shutdownRegistered = true;
            register_shutdown_function( array( __CLASS__, 'stopAll' ) );
        }
        self::$servers[$name] = array( 'process' => $process, 'url' => 'http://127.0.0.1:' . $port, 'state' => $state );
        return self::$servers[$name];
    }

    /** Stops every server of this process and removes their state directories. */
    public static function stopAll()
    {
        foreach ( self::$servers as $server )
        {
            $status = proc_get_status( $server['process'] );
            if ( $status['running'] )
                proc_terminate( $server['process'] );
            proc_close( $server['process'] );
            self::removeTree( $server['state'] );
        }
        self::$servers = array();
    }

    /**
     * @param string $name
     * @return string a new directory below the suite's temporary directory, for this process
     */
    public static function tmp( $name )
    {
        $base = getenv( 'EXPAI_TEST_TMP' ) ?: EXPAI_TEST_ROOT . '/var/tmp/expai-tests';
        $dir = rtrim( $base, '/' ) . '/' . getmypid() . '-' . $name . '-' . bin2hex( random_bytes( 3 ) );
        @mkdir( $dir, 0775, true );
        return $dir;
    }

    /**
     * Removes a directory the suite made (only below its temporary directory).
     *
     * @param string $dir
     */
    public static function removeTree( $dir )
    {
        $base = realpath( getenv( 'EXPAI_TEST_TMP' ) ?: EXPAI_TEST_ROOT . '/var/tmp/expai-tests' );
        $real = realpath( $dir );
        if ( $base === false || $real === false || strpos( $real, $base . '/' ) !== 0 )
            return;
        $items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $real, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $items as $item )
            $item->isDir() && !$item->isLink() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
        @rmdir( $real );
        @rmdir( $base );
    }
}
