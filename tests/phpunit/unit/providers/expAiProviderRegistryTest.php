<?php
/**
 * expAiProviderRegistry: providers and models of the settings, aliases, the provider objects, readiness, the admin
 * description and the key placement warnings. Settings in memory only.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

require_once __DIR__ . '/expaiprovidertesthelpers.php';

class expAiProviderTestCustomProvider extends expAiOpenAiChatProvider
{
}

class expAiProviderRegistryTest extends expAiProviderTestCase
{
    private function registry( array $settings )
    {
        return new expAiProviderRegistry( $this->config( $settings ) );
    }

    public function testInstanceUsesTheGivenOrTheSiteConfig(): void
    {
        $config = $this->config( array( 'ProviderSettings' => array( 'Providers' => array( 'anthropic' ) ) ) );
        $this->assertSame( array( 'anthropic' ), expAiProviderRegistry::instance( $config )->providerIds() );
        $this->assertInstanceOf( 'expAiProviderRegistry', expAiProviderRegistry::instance() );
    }

    public function testACustomProviderClassOfTheOldSettings(): void
    {
        $registry = $this->registry( array( 'ProviderSettings' => array( 'Providers' => array( 'anthropic' ), 'DefaultModel' => '' ),
                                            'AISettings' => array( 'Provider' => 'custom', 'ProviderClass' => 'expAiProviderTestCustomProvider', 'Model' => 'claude-x', 'Region' => '', 'Retention' => '' ) ) );
        $provider = $registry->getProvider( 'anthropic' );
        $this->assertSame( array( 'custom', 'expAiProviderTestCustomProvider', 'us', '30d' ), array( $provider['Type'], $provider['Class'], $provider['Region'], $provider['Retention'] ) );
        $this->assertTrue( $registry->providerReady( 'anthropic' ) );
        $this->assertInstanceOf( 'expAiProviderTestCustomProvider', $registry->providerFor( 'anthropic.claude-x' ) );
    }

    public function testAliasesResolveByAliasShortNameAndModelId(): void
    {
        $registry = $this->registry( array( 'ProviderSettings' => array( 'Providers' => array( 'xtea', 'xteb' ), 'DefaultModel' => 'missing' ),
                                            'AISettings' => array( 'Model' => 'nothing-either' ),
                                            'Provider_xtea' => array( 'Type' => 'openai_chat', 'BaseURL' => 'http://127.0.0.1:9/v1', 'ApiKey' => 'k' ),
                                            'Provider_xteb' => array( 'Type' => 'unknown_type', 'BaseURL' => 'http://127.0.0.1:9/v1' ),
                                            'Model_xtea.one' => array( 'Provider' => 'xtea', 'Id' => 'vendor/model-one', 'MaxOutput' => 0 ),
                                            'Model_xteb.two' => array( 'Provider' => 'xteb', 'Id' => 'model-two' ) ) );
        $this->assertSame( 'xtea.one', $registry->resolveAlias( 'vendor/model-one' ) );
        $this->assertSame( '', $registry->resolveAlias( 'unknown' ) );
        $this->assertSame( 'xtea.one', $registry->defaultAlias(), 'neither default resolves: the first model' );
        $this->assertSame( array(), $registry->settingsFor( 'unknown' ) );
        $this->assertSame( 'vendor/model-one', $registry->providerFor( 'unknown' )->modelName(), 'an unknown alias gets the default' );
        $this->assertFalse( $registry->providerReady( 'xteb' ), 'no key' );
        $this->assertFalse( $registry->providerReady( 'nothing' ) );
        $this->assertSame( array(), $registry->describeProvider( 'nothing' ) );
        try
        {
            $registry->providerFor( 'xteb.two' );
            $this->fail( 'no exception' );
        }
        catch ( expAiException $e )
        {
            $this->assertSame( 'unknown provider Type unknown_type', $e->detail() );
        }
    }

    public function testNoModelAtAllAndABadCustomClassAreConfigurationErrors(): void
    {
        $registry = $this->registry( array( 'ProviderSettings' => array( 'Providers' => array( 'xtec' ), 'DefaultModel' => '' ), 'AISettings' => array( 'Model' => '' ),
                                            'Provider_xtec' => array( 'Type' => 'custom', 'Class' => 'expAiConfig' ),
                                            'Model_xtec.m' => array( 'Provider' => 'nowhere', 'Id' => 'm' ) ) );
        $this->assertSame( '', $registry->defaultAlias() );
        try
        {
            $registry->providerFor( 'x' );
            $this->fail( 'no exception' );
        }
        catch ( expAiException $e )
        {
            $this->assertSame( 'no model x in the provider registry', $e->detail() );
        }
        $registry = $this->registry( array( 'ProviderSettings' => array( 'Providers' => array( 'xtec' ), 'DefaultModel' => 'xtec.m' ),
                                            'Provider_xtec' => array( 'Type' => 'custom', 'Class' => 'expAiConfig' ),
                                            'Model_xtec.m' => array( 'Provider' => 'xtec', 'Id' => 'm' ) ) );
        try
        {
            $registry->providerFor( 'xtec.m' );
            $this->fail( 'no exception' );
        }
        catch ( expAiException $e )
        {
            $this->assertSame( 'ProviderClass does not implement expAiProvider', $e->detail() );
        }
    }

    public function testDescribeProviderNeverShowsTheKey(): void
    {
        $registry = $this->registry( array( 'ProviderSettings' => array( 'Providers' => array( 'anthropic' ), 'DefaultModel' => '' ),
                                            'AISettings' => array( 'ApiKey' => 'test-secret-not-a-key', 'Endpoint' => '', 'Model' => 'claude-x' ) ) );
        $described = $registry->describeProvider( 'anthropic' );
        $this->assertSame( 'api.anthropic.com', $described['host'] );
        $this->assertSame( expAiAnthropicProvider::DEFAULT_ENDPOINT, $described['url'] );
        $this->assertTrue( $described['key_present'] );
        $this->assertStringNotContainsString( 'test-secret-not-a-key', json_encode( $described ) );
    }

    public function testKeysOutsideTheOverrideAreReported(): void
    {
        $dir = expAiTestMockServer::tmp( 'xte-ini' );
        $this->cleanup( static function () use ( $dir ) { expAiTestMockServer::removeTree( $dir ); } );
        file_put_contents( $dir . '/expai.ini', "[Provider_xtek]\nApiKey=test-not-a-key\nToken=\n[McpServer_x]\nToken=tok-not-a-token\n[Other]\nApiKey=x\n" );
        $ini = eZINI::fetchFromFile( substr( $dir, strlen( EXPAI_TEST_ROOT ) + 1 ) . '/expai.ini' );
        $problems = ( new expAiProviderRegistry( new expAiConfig( $ini ) ) )->keyPlacementProblems();
        $this->assertCount( 2, $problems );
        foreach ( $problems as $problem )
        {
            $this->assertStringContainsString( 'keys belong in settings/override only', $problem );
            $this->assertStringNotContainsString( 'not-a-key', $problem );
        }
        $this->assertStringStartsWith( '[Provider_xtek] ApiKey is set in ', $problems[0] );
        $this->assertStringStartsWith( '[McpServer_x] Token is set in ', $problems[1] );
    }
}
