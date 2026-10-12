<?php
/**
 * expAiQueueKick: how many workers a Vector run starts, the record of recent starts, and the start itself. No worker
 * is started: the "PHP binary" is /bin/true (the command line is built and run, and does nothing) or a path that does
 * not exist. The start record of the site is put back after each test.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

require_once __DIR__ . '/expait386ehelpers.php';

class expAiQueueKickTest extends expAiT386ETestCase
{
    /** @var string */
    private $record;

    protected function setUp(): void
    {
        parent::setUp();
        $dir = eZSys::cacheDirectory() . '/expai';
        $saved = array();
        foreach ( array( "$dir/worker-kick.json", "$dir/worker-kick-" . posix_geteuid() . '.json' ) as $path )
            $saved[$path] = is_file( $path ) ? file_get_contents( $path ) : null;
        $this->record = "$dir/worker-kick.json";
        $this->cleanup( static function () use ( $saved )
        {
            foreach ( $saved as $path => $content )
                if ( $content !== null )
                    file_put_contents( $path, $content );
                elseif ( is_file( $path ) )
                    unlink( $path );
        } );
    }

    public function testToStartFillsUpToTheLimitAndCountsIdleWorkers(): void
    {
        $this->assertSame( 2, expAiQueueKick::toStart( 2, 0, 0, 0, 4 ) );
        $this->assertSame( 3, expAiQueueKick::toStart( 1, 0, 0, 3, 4 ), 'ready jobs count too' );
        $this->assertSame( 1, expAiQueueKick::toStart( 3, 3, 0, 0, 4 ), 'never above the limit' );
        $this->assertSame( 0, expAiQueueKick::toStart( 2, 2, 2, 0, 4 ), 'idle workers take the jobs' );
        $this->assertSame( 0, expAiQueueKick::toStart( 1, 6, 0, 0, 4 ) );
        $this->assertSame( 4, expAiQueueKick::maxWorkers( $this->config( array( 'VectorSettings' => array( 'MaxKickedWorkers' => '' ) ) ) ) );
        $this->assertSame( 16, expAiQueueKick::maxWorkers( $this->config( array( 'VectorSettings' => array( 'MaxKickedWorkers' => 99 ) ) ) ) );
        $this->assertSame( 1, expAiQueueKick::maxWorkers( $this->config( array( 'VectorSettings' => array( 'MaxKickedWorkers' => 0 ) ) ) ) );
    }

    public function testWorkersStartedButNotYetBeatingCountAsLiveAndIdle(): void
    {
        $this->requireDatabase();
        list( $live, $idle ) = expAiQueueKick::workers( array() );
        list( $live2, $idle2 ) = expAiQueueKick::workers( array( time() + 3600, time() + 3600 ) );
        $this->assertSame( array( $live + 2, $idle + 2 ), array( $live2, $idle2 ), 'two starts in the future: no worker of them beats yet' );
        $this->assertSame( 2, self::invoke( 'expAiQueueKick', 'startedSince', array( array( array( 'started' => 100 ), array( 'started' => 99 ), array( 'started' => 50 ) ), 100 ) ) );
        $this->assertGreaterThanOrEqual( 0, expAiQueueKick::readyJobs( 'interactive' ) );
        $this->assertSame( 0, expAiQueueKick::readyJobs( 't386e-no-such-class' ) );
    }

    public function testKickDoesNothingWhenSwitchedOff(): void
    {
        $this->site( array( 'VectorSettings' => array( 'KickWorker' => 'false' ) ) );
        $before = is_file( $this->record ) ? file_get_contents( $this->record ) : null;
        $this->assertSame( 0, expAiQueueKick::kick( 3 ) );
        $this->assertSame( $before, is_file( $this->record ) ? file_get_contents( $this->record ) : null, 'the record is not touched' );
    }

    public function testKickStartsTheMissingWorkersAndRecordsTheStarts(): void
    {
        $this->requireDatabase();
        $this->site( array( 'VectorSettings' => array( 'KickWorker' => 'true', 'PhpCli' => '/bin/true', 'MaxKickedWorkers' => 16 ) ) );
        @mkdir( dirname( $this->record ), 0775, true );
        file_put_contents( $this->record, json_encode( array( 'starts' => array( time() - 3600 ) ) ) );
        list( $live, $idle ) = expAiQueueKick::workers( array() );
        $wanted = $idle + 2;
        $started = expAiQueueKick::kick( $wanted, 5, 'interactive' );
        $this->assertGreaterThanOrEqual( min( 2, 16 - $live ), $started );
        $record = json_decode( file_get_contents( $this->record ), true );
        $this->assertSame( $started, $record['started'] );
        $this->assertSame( $wanted, $record['wanted'] );
        $this->assertCount( $started, $record['starts'], 'the old start is forgotten, the new ones are kept' );
        $this->assertSame( 'cli', $record['sapi'] );

        // just started: they count as idle workers, so a second kick for as many starts none
        $this->assertSame( 0, expAiQueueKick::kick( $started, 5, 'interactive' ) );
    }

    public function testAMissingBinaryIsRecordedAsTheError(): void
    {
        $this->requireDatabase();
        $this->site( array( 'VectorSettings' => array( 'KickWorker' => 'true', 'PhpCli' => '/nonexistent/t386e/php', 'MaxKickedWorkers' => 16 ) ) );
        @mkdir( dirname( $this->record ), 0775, true );
        file_put_contents( $this->record, '{}' );
        $this->assertSame( 0, expAiQueueKick::kick( 16 ) );
        $record = json_decode( file_get_contents( $this->record ), true );
        $this->assertStringStartsWith( 'no PHP binary or worker script (/nonexistent/t386e/php', $record['error'] );
    }

    public function testLaunchRunsTheCommandInTheBackground(): void
    {
        $this->assertTrue( self::invoke( 'expAiQueueKick', 'launch', array( 'true' ) ) );
        $config = $this->config( array( 'VectorSettings' => array( 'PhpCli' => '/bin/true' ) ) );
        $this->assertSame( 2, self::invoke( 'expAiQueueKick', 'start', array( 2, 9999, 'bulk', $config ) ) );
    }
}
