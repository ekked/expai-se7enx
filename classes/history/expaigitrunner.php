<?php
/**
 * File containing the expAiGitRunner class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * Runs read-only git commands for project_history without a shell: proc_open() with an argument list (no
 * interpolation, no globbing), the binary of [HistorySettings] GitBinary (absolute), a clean environment (no system
 * or global git configuration, no prompts, no pager), literal pathspecs, a time limit and an output limit. Only the
 * subcommands of ALLOWED run. The repository is given with -C and trusted for this one call (safe.directory).
 */
class expAiGitRunner
{
    /** @var string[] the subcommands that may run */
    const ALLOWED = array( 'log', 'ls-files', 'rev-parse' );

    /** @var string */
    protected $binary;

    /** @var int */
    protected $timeout;

    /** @var int */
    protected $maxBytes;

    public function __construct( $binary = '/usr/bin/git', $timeout = 10, $maxBytes = 1048576 )
    {
        $this->binary = (string) $binary;
        $this->timeout = max( 1, min( 60, (int) $timeout ) );
        $this->maxBytes = max( 4096, (int) $maxBytes );
    }

    /**
     * @param string $repository an absolute, checked directory
     * @param string[] $arguments the subcommand first; every value a separate argument
     * @return string standard output
     * @throws expAiException
     */
    public function run( $repository, array $arguments )
    {
        if ( !function_exists( 'proc_open' ) )
            throw new expAiException( 'The project history is not available on this server.', 'history: proc_open is disabled' );
        if ( $this->binary === '' || $this->binary[0] !== '/' || !is_file( $this->binary ) || !is_executable( $this->binary ) )
            throw new expAiException( 'The project history is not available on this server.', 'history: GitBinary is not an executable absolute path' );
        if ( !$arguments || !in_array( $arguments[0], self::ALLOWED, true ) )
            throw new expAiException( 'The project history is not available on this server.', 'history: git subcommand not allowed' );
        foreach ( $arguments as $argument )
            if ( !is_string( $argument ) && !is_int( $argument ) || strpos( (string) $argument, "\0" ) !== false )
                throw new expAiException( 'The project history could not be read.', 'history: invalid argument' );
        $command = array_merge( array( $this->binary, '--no-pager', '--literal-pathspecs', '-c', 'safe.directory=' . $repository, '-c', 'core.fsmonitor=false',
                                       '-c', 'log.showSignature=false', '-c', 'core.quotePath=false', '-C', $repository ),
                                array_map( 'strval', $arguments ) );
        $env = array( 'PATH' => '/usr/bin:/bin', 'LC_ALL' => 'C.UTF-8', 'GIT_CONFIG_NOSYSTEM' => '1', 'GIT_CONFIG_GLOBAL' => '/dev/null',
                      'GIT_TERMINAL_PROMPT' => '0', 'GIT_OPTIONAL_LOCKS' => '0', 'HOME' => sys_get_temp_dir() );
        $pipes = array();
        $process = @proc_open( $command, array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, $repository, $env );
        if ( !is_resource( $process ) )
            throw new expAiException( 'The project history could not be read.', 'history: git did not start' );
        stream_set_blocking( $pipes[1], false );
        stream_set_blocking( $pipes[2], false );
        $out = '';
        $err = '';
        $deadline = microtime( true ) + $this->timeout;
        $killed = false;
        while ( true )
        {
            $read = array( $pipes[1], $pipes[2] );
            $write = $except = null;
            $left = $deadline - microtime( true );
            if ( $left <= 0 || strlen( $out ) > $this->maxBytes )
            {
                proc_terminate( $process, 9 );
                $killed = true;
                break;
            }
            if ( @stream_select( $read, $write, $except, 0, (int) min( 200000, $left * 1000000 ) ) === false )
                break;
            foreach ( $read as $pipe )
            {
                $chunk = fread( $pipe, 65536 );
                if ( $pipe === $pipes[1] )
                    $out .= (string) $chunk;
                elseif ( strlen( $err ) < 4096 )
                    $err .= (string) $chunk;
            }
            if ( feof( $pipes[1] ) && feof( $pipes[2] ) )
                break;
        }
        fclose( $pipes[1] );
        fclose( $pipes[2] );
        $code = proc_close( $process );
        if ( $killed && strlen( $out ) <= $this->maxBytes )
            throw new expAiException( 'The project history took too long to read.', 'history: git timed out after ' . $this->timeout . ' s' );
        if ( !$killed && $code !== 0 )
            throw new expAiException( 'The project history could not be read.', 'history: git exit ' . $code . ': ' . expAiText::cut( expAiText::oneLine( $err ), 200 ) );
        return substr( $out, 0, $this->maxBytes );
    }
}
