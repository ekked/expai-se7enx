<?php
/**
 * expAiSummary (the "EI: Summary" page: providers, who may use them, data policy, limits, retention, tools) and the
 * smaller pieces of the core: expAiUsageLog, expAiTool, expAiToolRegistry, expAiToolContext, expAiPrompts,
 * expAiConversation. Roles and probe rows of the test exist only in a transaction that is rolled back.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

require_once __DIR__ . '/expaicoretestcase.php';

class expAiSummaryTest extends expAiT386DCoreTestCase
{
    public function testTheSummaryOfASite(): void
    {
        $this->asUser( 'admin' );
        $this->inTransaction();
        $config = $this->useSettings( array(
            'AISettings' => array( 'Enabled' => 'true' ),
            'ProviderSettings' => array( 'Providers' => array( 'anthropic', 'huggingface', 'local' ), 'ProvidersWithoutPolicy' => array( 'local' ) ),
            'Provider_huggingface' => array( 'ApiKey' => 'hf_not_a_key_t386d' ),
            'Limits' => array( 'ProviderMaxTokensPerMonth' => array( 'huggingface' => '1000' ), 'MaxTokensPerSitePerMonth' => '999999999999',
                               'MaxCostPerUserPerDay' => '3' ),
            'DataPolicy' => array( 'DefaultRegions' => array( 'eu' ), 'SectionRegions' => array( '1' => 'eu;us', '987654' => 'eu', '2' => '' ),
                                   'ClassRegions' => array( 'article' => 'eu' ), 'ZeroRetentionSections' => array( '1', '987654' ),
                                   'ZeroRetentionClasses' => array( 'article' ) ),
            'ToolSettings' => array( 'Tools' => array( 'get_node', 'web_fetch', 'web_search', 'project_history' ) ),
            'WebSettings' => array( 'AllowedDomains' => array( 'example.invalid' ) ),
            'SearchSettings' => array( 'Provider' => 't386d' ),
            'SearchProvider_t386d' => array( 'Label' => 'T386D search', 'Region' => 'EU', 'Retention' => 'none', 'ApiKey' => 'not-a-key' ),
            'SummarySettings' => array( 'Contact' => ' ai@example.invalid ', 'Note' => 'Test note' ),
        ) );
        // a role that may use the models of one provider, and one that may write
        $role = eZRole::create( 'expai t386d summary role ' . mt_rand() );
        $role->store();
        $role->appendPolicy( 'expai', 'model_use', array( 'Provider' => array( 'huggingface' ) ) );
        $role->appendPolicy( 'expai', 'write' );
        $role->appendPolicy( 'content', 'read' );
        $probe = new expAiProbeResult( array( 'model_alias' => 'huggingface.gpt-oss-120b', 'probe_mode' => 'live', 'provider' => 'huggingface', 'model' => 'gpt-oss',
                                              'pass_rate' => 87, 'passed' => 7, 'tasks' => 8, 'created' => time(), 'details' => '[]', 'tool_mode' => 'native' ) );
        $probe->store();

        $facts = ( new expAiSummary( $config ) )->collect();
        $this->assertSame( 'ai@example.invalid', $facts['contact'] );
        $this->assertSame( 'Test note', $facts['note'] );
        $this->assertTrue( $facts['switches']['master'] );
        $this->assertSame( 'USD', $facts['currency'] );
        $this->assertStringNotContainsString( 'hf_not_a_key_t386d', json_encode( $facts ), 'never a key' );
        $this->assertStringNotContainsString( 'not-a-key', json_encode( $facts ) );

        $providers = array_column( $facts['providers'], null, 'id' );
        $this->assertSame( array( 'anthropic', 'huggingface', 'local' ), array_keys( $providers ) );
        $hf = $providers['huggingface'];
        $this->assertTrue( $hf['key']['set'] );
        $this->assertTrue( $hf['key']['needed'] );
        $this->assertSame( 'Provider_huggingface', $hf['key']['group'] );
        $this->assertFalse( $hf['everyone'] );
        $this->assertContains( $role->attribute( 'name' ), $hf['roles'], 'the role limited to the provider' );
        $this->assertSame( array( array( 'unit' => 'tokens', 'max' => 1000.0 ) ), $hf['limits'] );
        $models = array_column( $hf['models'], null, 'alias' );
        $this->assertSame( 87, $models['huggingface.gpt-oss-120b']['probe']['pass_rate'] );
        $this->assertSame( 'US', $hf['region_label'] );
        $local = $providers['local'];
        $this->assertTrue( $local['everyone'] );
        $this->assertFalse( $local['key']['needed'], 'a local server needs no key' );
        $this->assertContains( $role->attribute( 'name' ), $local['roles'], 'expai/write is enough for a provider without a policy' );
        $this->assertSame( 'this server', $local['region_label'] );
        $this->assertNotContains( $role->attribute( 'name' ), $providers['anthropic']['roles'], 'limited to another provider' );
        $this->assertNotEmpty( array_filter( $providers['anthropic']['lanes'], function ( $l ) { return $l['name'] === 'standard'; } ) );

        $policy = $facts['policy'];
        $this->assertTrue( $policy['active'] );
        $this->assertSame( array( 'eu' ), $policy['default_regions'] );
        $sections = array_column( $policy['sections'], 'regions', 'what' );
        $this->assertSame( 'eu, us', reset( $sections ) );
        $this->assertContains( '#987654', array_keys( $sections ), 'a section that does not exist is shown by its number' );
        $this->assertCount( 2, $sections, 'an empty rule is no rule' );
        $this->assertSame( array( array( 'what' => 'article', 'regions' => 'eu' ) ), $policy['classes'] );
        $this->assertContains( '#987654', $policy['zero_retention_sections'] );
        $this->assertSame( array( 'article' ), $policy['zero_retention_classes'] );

        $limits = array_column( $facts['limits']['rows'], null, 'key' );
        $this->assertArrayHasKey( 'site_month_tokens', $limits );
        $this->assertNotNull( $limits['site_month_tokens']['percent'], 'site limits show their usage' );
        $this->assertNull( $limits['day_cost']['percent'], 'a user limit shows none' );
        $this->assertSame( '', $limits['day_cost']['used'] );
        $this->assertArrayHasKey( 'provider_month_tokens:huggingface', $limits );
        $this->assertSame( 'huggingface', $limits['provider_month_tokens:huggingface']['provider'] );

        $this->assertSame( 365, $facts['retention']['usage_days'] );
        $tools = $facts['tools'];
        $this->assertTrue( $tools['web_fetch']['listed'] );
        $this->assertSame( array( 'example.invalid' ), $tools['web_fetch']['domains'] );
        $this->assertSame( 'T386D search', $tools['web_search']['provider'] );
        $this->assertSame( 'eu', $tools['web_search']['region'] );
        $this->assertSame( 'EU', $tools['web_search']['region_label'] );
        $this->assertTrue( $tools['web_search']['key']['set'] );

        $roles = array_column( $facts['roles'], 'policies', 'name' );
        $this->assertSame( array( 'model_use', 'write' ), array_column( $roles[$role->attribute( 'name' )], 'function' ), 'only the policies of expai' );

        $this->assertSame( 'unknown', expAiSummary::regionLabel( 'mars' ) );
        $this->assertSame( array( 'set' => false, 'file' => '', 'placement' => '' ), ( new expAiSummary( $config ) )->secretSource( 'Provider_openai', 'ApiKey' ) );
        $this->assertStringContainsString( '2 requests', expAiSummary::costText( array( 'cost' => 1.5, 'cost_approx' => true, 'unpriced' => 2 ) ) );
        $this->assertStringNotContainsString( '(', expAiSummary::costText( array( 'cost' => 1.5 ) ) );
    }

    public function testUsageLogRowsAndTools(): void
    {
        $this->useSettings();
        $log = new expAiUsageLog( array( 'input_tokens' => 1, 'output_tokens' => 2, 'cache_write_tokens' => 3, 'cache_read_tokens' => 4,
                                         'tool_calls' => json_encode( array( array( 'name' => 'get_node', 'ms' => 5, 'ok' => true ) ) ),
                                         'model' => 'claude-sonnet-5-5', 'model_alias' => '', 'reported_cost_micros' => 2500000, 'price_factor' => 100 ) );
        $this->assertSame( 10, $log->totalTokens() );
        $this->assertSame( array( array( 'name' => 'get_node', 'ms' => 5, 'ok' => true ) ), $log->tools() );
        $this->assertSame( 2.5, $log->cost() );
        $this->assertSame( 'reported', $log->costSource() );
        $this->assertSame( array(), ( new expAiUsageLog( array( 'tool_calls' => 'not json' ) ) )->tools() );
        $this->useSettings( array( 'Pricing' => array( 'Enabled' => 'false' ) ) );
        $this->assertSame( '', $log->costSource(), 'no cost source while costs are off' );
    }

    public function testToolsRegistryAndContext(): void
    {
        $config = $this->useSettings( array( 'ToolSettings' => array( 'Tools' => array( 'get_node', 't386d_broken' ) ), 'Tool_t386d_broken' => array( 'Class' => 'expAiT386DNoSuchTool' ),
                                             'AssistantSettings' => array( 'MaxDraftsPerConversation' => '1' ), 'McpSettings' => array( 'Servers' => array() ),
                                             'McpBridge' => array( 'Enabled' => 'disabled' ) ) );
        $registry = new expAiToolRegistry( $config, false );
        $this->assertArrayHasKey( 'get_node', $registry->tools() );
        $this->assertArrayNotHasKey( 't386d_broken', $registry->tools(), 'a tool without a usable class is left out' );
        $this->assertNull( $registry->get( 't386d_broken' ) );
        $plain = new expAiT386DPlainTool( 't386d_plain' );
        $registry->add( $plain );
        $this->assertSame( $plain, $registry->get( 't386d_plain' ) );
        $this->assertSame( 'Used the tool %tool', $plain->stepText( array() ) );
        $this->assertSame( array( '%tool' => 't386d_plain' ), $plain->stepArguments( array(), null ) );
        $this->assertFalse( $plain->writes() );
        $this->assertSame( array( 'name' => 't386d_plain', 'description' => 'A tool of the test', 'input_schema' => array( 'type' => 'object' ) ), $plain->definition() );

        $context = new expAiToolContext( $config, 2 );
        $context->checkDraftLimit();
        $context->addWritten( new expAiDraftLog( array() ) );
        $this->assertSame( 1, $context->draftCount() );
        $e = $this->expectFailure( function () use ( $context ) { $context->checkDraftLimit(); } );
        $this->assertSame( array( '%max' => 1 ), $e->arguments() );
    }

    public function testConversationDocument(): void
    {
        $this->requireDatabase();
        $this->inTransaction();
        $this->useSettings();
        $conversation = expAiConversation::start( self::TEST_USER, 987650099 );
        $this->assertSame( array(), $conversation->messages() );
        $conversation->setScopeNote( 'Only the news folder' );
        $conversation->store();
        $fresh = expAiConversation::fetchCurrent( self::TEST_USER, 987650099 );
        $this->assertSame( 'Only the news folder', $fresh->scopeNote() );
        $this->assertSame( array(), ( new expAiConversation( array( 'messages' => 'not json' ) ) )->messages() );
        $this->assertSame( array(), ( new expAiConversation( array( 'messages' => 'not json' ) ) )->scopeNote() === '' ? array() : array( 'x' ) );
        $this->assertNull( expAiConversation::fetchCurrent( self::TEST_USER, 987650098 ) );
        // an expired conversation is not the current one
        $fresh->setAttribute( 'modified', time() - 30 * 86400 );
        $fresh->store();
        $this->assertNull( expAiConversation::fetchCurrent( self::TEST_USER, 987650099 ) );
    }
}

/** A tool with the defaults of expAiTool */
class expAiT386DPlainTool extends expAiTool
{
    public function description()
    {
        return 'A tool of the test';
    }

    public function inputSchema()
    {
        return array( 'type' => 'object' );
    }

    public function execute( array $input, expAiToolContext $context )
    {
        return array();
    }
}
