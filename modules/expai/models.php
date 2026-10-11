<?php
/**
 * expai/models (policy expai/model_manage): the providers and models of the registry (region, retention, key
 * present - never the key -, tool mode, write gate, the latest probe), warnings about misplaced keys, the MCP
 * settings, and per local server (Managed=ollama) its state with Start / Stop / Pull / Remove (written as requests
 * for the root-owned helper, never run by the web process) and a test prompt that measures tokens per second.
 * The POST forms carry the form token (ezformtoken).
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$config = expAiConfig::instance();
$registry = $config->registry();
$notice = '';
$error = '';
$test = null;

if ( $Module->isCurrentAction( 'LocalAction' ) )
{
    $action = (string) $Module->actionParameter( 'Action' );
    $model = (string) $Module->actionParameter( 'Model' );
    $managed = expAiLocalModel::managedProviders( $config );
    try
    {
        if ( !$managed )
            throw new expAiException( 'No local model server is configured.' );
        $id = ( new expAiLocalModel( $config, $managed[0] ) )->request( $action, $model );
        $notice = ezpI18n::tr( 'extension/expai/models', 'The request %id was handed to the local model helper.', null, array( '%id' => substr( $id, 0, 8 ) ) );
    }
    catch ( expAiException $e )
    {
        $error = $e->translatedMessage();
    }
}
if ( $Module->isCurrentAction( 'TestPrompt' ) )
{
    $alias = $registry->resolveAlias( (string) $Module->actionParameter( 'Model' ) );
    try
    {
        if ( $alias === '' )
            throw new expAiException( 'This model is not available.' );
        $recorder = new expAiUsageRecorder( $registry->providerFor( $alias ), array( 'action' => 'test' ) );
        $start = microtime( true );
        $answer = $recorder->send( array( 'system' => 'You are a test of the connection. Answer briefly.',
                                          'messages' => array( array( 'role' => 'user', 'content' => 'Write one short sentence about the sea.' ) ), 'max_tokens' => 120 ) );
        $seconds = microtime( true ) - $start;
        $text = '';
        foreach ( $answer['content'] as $block )
            if ( isset( $block['type'] ) && $block['type'] === 'text' )
                $text .= $block['text'];
        $output = isset( $answer['tokens']['output'] ) ? (int) $answer['tokens']['output'] : 0;
        $test = array( 'alias' => $alias, 'text' => expAiText::cut( $text, 300 ), 'seconds' => round( $seconds, 1 ), 'output' => $output,
                       'tps' => $seconds > 0 ? round( $output / $seconds, 1 ) : 0, 'model' => (string) $answer['model'] );
    }
    catch ( expAiException $e )
    {
        $error = $e->translatedMessage();
    }
}

$providers = array();
foreach ( array_keys( $registry->providers() ) as $id )
{
    $p = $registry->describeProvider( $id );
    foreach ( $p['models'] as $i => $m )
    {
        $live = expAiProbe::latest( $m['alias'], 'live' );
        $mock = expAiProbe::latest( $m['alias'], 'mock' );
        $p['models'][$i]['probe_live'] = $live ? array( 'pass_rate' => (int) $live->attribute( 'pass_rate' ), 'passed' => (int) $live->attribute( 'passed' ),
                                                        'tasks' => (int) $live->attribute( 'tasks' ), 'created' => (int) $live->attribute( 'created' ) ) : false;
        $p['models'][$i]['probe_mock'] = $mock ? array( 'pass_rate' => (int) $mock->attribute( 'pass_rate' ), 'created' => (int) $mock->attribute( 'created' ) ) : false;
        $p['models'][$i]['writes'] = expAiProbe::writeAllowed( $m['alias'], $config );
        $p['models'][$i]['write_refusal'] = expAiProbe::writeRefusal( $m['alias'], $config );
        $p['models'][$i]['cost'] = expAiCost::costClass( $m['alias'], $config );
    }
    $providers[] = $p;
}
$local = array();
foreach ( expAiLocalModel::managedProviders( $config ) as $id )
{
    $model = new expAiLocalModel( $config, $id );
    $local[] = $model->status() + array( 'models' => $model->allowedModels() );
}
$mcpServers = array();
foreach ( $config->arr( 'McpSettings', 'Servers' ) as $id )
    $mcpServers[] = array( 'id' => $id, 'label' => (string) $config->get( 'McpServer_' . $id, 'Label', $id ),
                           'host' => (string) parse_url( (string) $config->get( 'McpServer_' . $id, 'URL', '' ), PHP_URL_HOST ),
                           'tools' => $config->arr( 'McpServer_' . $id, 'AllowedTools' ),
                           'region' => expAiProviderRegistry::cleanRegion( $config->get( 'McpServer_' . $id, 'Region', '' ) ),
                           'token' => trim( (string) $config->get( 'McpServer_' . $id, 'Token', '' ) ) !== '' );

$tpl = eZTemplate::factory();
$tpl->setVariable( 'providers', $providers );
$tpl->setVariable( 'default_alias', $registry->defaultAlias() );
$tpl->setVariable( 'key_problems', $registry->keyPlacementProblems() );
$tpl->setVariable( 'local', $local );
$tpl->setVariable( 'threshold', expAiProbe::threshold( $config ) );
$tpl->setVariable( 'bridge', array( 'enabled' => $config->flag( 'McpBridge', 'Enabled', false ), 'tools' => $config->arr( 'McpBridge', 'Tools' ),
                                    'available' => class_exists( 'expMcpContentTool' ) ) );
$tpl->setVariable( 'mcp_servers', $mcpServers );
$tpl->setVariable( 'policy_active', ( new expAiDataPolicy( $config, array() ) )->active() );
$tpl->setVariable( 'notice', $notice );
$tpl->setVariable( 'error', $error );
$tpl->setVariable( 'test', $test );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:expai/models.tpl' );
$Result['path'] = array( array( 'url' => 'expai/dashboard', 'text' => ezpI18n::tr( 'extension/expai/models', 'Exponential Intelligence' ) ),
                         array( 'url' => false, 'text' => ezpI18n::tr( 'extension/expai/models', 'Models' ) ) );
