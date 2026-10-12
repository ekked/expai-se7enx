<?php
/**
 * The write gate (expAiProbe::writeRefusal() with the stored probe results), the per-task details of a probe result,
 * and the reachability of the providers (expAiProviderHealth) for a managed local server and a loopback server. The
 * probe rows are written in a transaction that is rolled back.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

require_once __DIR__ . '/expait386ehelpers.php';

class expAiProbeGateTest extends expAiT386ETestCase
{
    private function models( $localUrl = 'http://127.0.0.1:1' )
    {
        return $this->config( array(
            'ProviderSettings' => array( 'Providers' => array( 't386ep', 't386epl', 't386epr' ) ),
            'Provider_t386ep' => array( 'Type' => 'openai_chat', 'BaseURL' => 'https://probe.example/v1', 'ApiKey' => 'k' ),
            'Provider_t386epl' => array( 'Type' => 'openai_chat', 'Flavor' => 'ollama', 'Managed' => 'ollama', 'BaseURL' => $localUrl . '/v1', 'KeyOptional' => 'true' ),
            'Provider_t386epr' => array( 'Type' => 'openai_chat', 'BaseURL' => 'http://example.invalid/v1', 'Managed' => 'ollama' ),
            'Model_t386ep.gated' => array( 'Provider' => 't386ep', 'Id' => 'gated-1', 'ToolUse' => 'native', 'WriteTools' => 'enabled', 'WriteGate' => 'probe' ),
            'Model_t386ep.trusted' => array( 'Provider' => 't386ep', 'Id' => 'trusted-1', 'ToolUse' => 'native', 'WriteTools' => 'enabled', 'WriteGate' => 'trusted' ),
            'Model_t386ep.prompted' => array( 'Provider' => 't386ep', 'Id' => 'p-1', 'ToolUse' => 'prompted', 'WriteTools' => 'enabled' ),
            'Model_t386ep.reader' => array( 'Provider' => 't386ep', 'Id' => 'r-1', 'ToolUse' => 'native', 'WriteTools' => 'disabled' ),
            'ProbeSettings' => array( 'WritePassRate' => 90 ),
        ) );
    }

    private static function probeRow( $alias, $model, $rate, $mode = 'live' )
    {
        $row = new expAiProbeResult( array( 'model_alias' => $alias, 'provider' => 't386ep', 'model' => $model, 'tool_mode' => 'native', 'probe_mode' => $mode,
                                            'tasks' => 10, 'passed' => (int) ( $rate / 10 ), 'pass_rate' => $rate, 'details' => json_encode( array( array( 'id' => 'read', 'ok' => true ) ) ),
                                            'user_id' => 14, 'created' => time() ) );
        $row->store();
        return $row;
    }

    public function testTheWriteGateNamesWhyAModelMayNotWrite(): void
    {
        $this->requireDatabase();
        $this->inTransaction();
        $config = $this->models();
        $this->assertSame( 'unknown model', expAiProbe::writeRefusal( 'nothing', $config ) );
        $this->assertSame( 'the model has no native tool calling (ToolUse=prompted)', expAiProbe::writeRefusal( 't386ep.prompted', $config ) );
        $this->assertSame( 'WriteTools is not enabled for the model', expAiProbe::writeRefusal( 't386ep.reader', $config ) );
        $this->assertSame( '', expAiProbe::writeRefusal( 't386ep.trusted', $config ) );
        $this->assertStringStartsWith( 'the model has not passed the probe (bin/php/expaiprobe.php --model=t386ep.gated --live)', expAiProbe::writeRefusal( 't386ep.gated', $config ) );
        $this->assertFalse( expAiProbe::writeAllowed( 't386ep.gated', $config ) );

        self::probeRow( 't386ep.gated', 'gated-0', 100 );
        $this->assertSame( 'the probe was run with another model id', expAiProbe::writeRefusal( 't386ep.gated', $config ) );
        self::probeRow( 't386ep.gated', 'gated-1', 80 );
        $this->assertSame( 'the probe pass rate 80% is below 90%', expAiProbe::writeRefusal( 't386ep.gated', $config ) );
        self::probeRow( 't386ep.gated', 'gated-1', 100, 'dry' );
        $this->assertSame( 'the probe pass rate 80% is below 90%', expAiProbe::writeRefusal( 't386ep.gated', $config ), 'only a live probe counts' );
        $passed = self::probeRow( 't386ep.gated', 'gated-1', 95 );
        $this->assertSame( '', expAiProbe::writeRefusal( 't386ep.gated', $config ) );
        $this->assertTrue( expAiProbe::writeAllowed( 't386ep.gated', $config ) );
        $this->assertSame( (int) $passed->attribute( 'id' ), (int) expAiProbe::latest( 't386ep.gated' )->attribute( 'id' ) );
        $this->assertSame( array( array( 'id' => 'read', 'ok' => true ) ), $passed->results() );
        $passed->setAttribute( 'details', 'not json' );
        $this->assertSame( array(), $passed->results() );
    }

    public function testTheHealthOfAManagedLocalServer(): void
    {
        expAiProviderHealth::reset();
        $this->cleanup( static function () { expAiProviderHealth::reset(); } );
        $www = expAiTestMockServer::tmp( 't386e-health' );
        $this->cleanup( static function () use ( $www ) { expAiTestMockServer::removeTree( $www ); } );
        mkdir( $www . '/up/api', 0775, true );
        file_put_contents( $www . '/up/api/version', '{"version":"1"}' );
        $server = expAiTestMockServer::start( '', $www );

        $this->assertSame( expAiProviderHealth::OK, expAiProviderHealth::state( $this->models( $server['url'] . '/up' ), 't386epl' ) );
        $this->assertSame( expAiProviderHealth::STOPPED, expAiProviderHealth::state( $this->models( $server['url'] . '/down' ), 't386epl' ), 'a 404: no model server there' );
        $probe = stream_socket_server( 'tcp://127.0.0.1:0' );
        $port = (int) substr( strrchr( stream_socket_get_name( $probe, false ), ':' ), 1 );
        fclose( $probe );
        $closed = $this->models( 'http://127.0.0.1:' . $port );
        $this->assertSame( expAiProviderHealth::STOPPED, expAiProviderHealth::state( $closed, 't386epl' ) );
        $this->assertSame( expAiProviderHealth::OK, expAiProviderHealth::state( $closed, 't386epr' ), 'a managed server that is not on this machine is not checked' );
        $this->assertSame( expAiProviderHealth::OK, expAiProviderHealth::state( $closed, 'nothing' ) );

        expAiProviderHealth::remember( $closed, 't386epl', expAiProviderHealth::OK );
        $this->assertSame( expAiProviderHealth::OK, expAiProviderHealth::state( $this->models( 'http://127.0.0.1:' . $port ), 't386epl' ), 'remembered for its time' );
        $this->assertSame( expAiProviderHealth::STOPPED, expAiProviderHealth::downState( array( 'Managed' => 'Ollama ' ) ) );
        $this->assertSame( expAiProviderHealth::UNREACHABLE, expAiProviderHealth::downState( array() ) );
    }
}
