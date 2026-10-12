<?php
/**
 * Unit test of Melious (Type=openai_chat, Flavor=melious) against answers recorded from the real API on 2026-10-12
 * (tests/fixtures/melious, ids scrubbed, no key): the shipped [Provider_melious] and [Model_melious.*] groups, the
 * prices in EUR converted with [Pricing] ExchangeRates[], the request (max_tokens, reasoning_effort, tools,
 * tool_choice, response_format, no streaming), the answer (text, a tool call with reasoning_content, usage with
 * reasoning and cached tokens, the cost Melious reports in billing_cost.credits), the assistant message sent back
 * on the next turn without the proxy's extra fields, the error shapes (unknown model, bad key, rate limit, no credit)
 * and the data policy (eu, zero retention). No database writes, no network.
 *
 * Run from the installation root:
 *   php bin/php/ezexec.php extension/expai/tests/expaimelious_test.php
 * It prints one line per check and ends with PASS or FAIL (exit code 0 or 1).
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

$failures = 0;
$checks = 0;
$check = function ( $label, $actual, $expected ) use ( &$failures, &$checks )
{
    ++$checks;
    $ok = is_float( $expected ) ? abs( (float) $actual - $expected ) < 1e-9 : $actual === $expected;
    if ( !$ok )
        ++$failures;
    echo ( $ok ? 'ok    ' : 'FAIL  ' ) . $label . ( $ok ? '' : ' => ' . var_export( $actual, true ) . ' (expected ' . var_export( $expected, true ) . ')' ) . "\n";
};
$fixture = function ( $name )
{
    $data = json_decode( (string) file_get_contents( 'extension/expai/tests/fixtures/melious/' . $name ), true );
    return isset( $data['response'] ) ? $data['response'] : $data;
};
$thrown = function ( $callback )
{
    try
    {
        $callback();
    }
    catch ( expAiException $e )
    {
        return $e;
    }
    return null;
};

// ---------------------------------------------------------------- the shipped settings with melious switched on
$ini = eZINI::fetchFromFile( 'extension/expai/settings/expai.ini.append.php' );
$check( 'shipped: melious is not active by default', in_array( 'melious', (array) $ini->variable( 'ProviderSettings', 'Providers' ), true ), false );
$check( 'shipped: [Provider_melious] has no key', (string) $ini->variable( 'Provider_melious', 'ApiKey' ), '' );
$ini->setVariable( 'ProviderSettings', 'Providers', array( 'anthropic', 'melious' ) );
$config = new expAiConfig( $ini );
$registry = $config->registry();
$aliases = array( 'melious.glm-5.3-flash', 'melious.glm-5.3', 'melious.deepseek-v4-flash-0731', 'melious.deepseek-v4.1-flash' );
$check( 'registry: the four partner models', array_values( array_filter( array_keys( $registry->models() ), function ( $a ) { return strpos( $a, 'melious.' ) === 0; } ) ), $aliases );
$melious = $registry->getProvider( 'melious' );
$check( 'provider: region eu', $melious['Region'], 'eu' );
$check( 'provider: retention zdr', $melious['Retention'], 'zdr' );
$check( 'provider: terms checked', $melious['TermsCheckedAt'], '2026-10-12' );
$check( 'provider: not ready without a key', $registry->providerReady( 'melious' ), false );
$described = $registry->describeProvider( 'melious' );
$check( 'provider: host', $described['host'], 'api.melious.ai' );
$check( 'provider: description has no key', array_key_exists( 'ApiKey', $described ), false );
foreach ( $aliases as $alias )
{
    $model = $registry->getModel( $alias );
    $check( "$alias: id is the alias without the provider", $model['id'], substr( $alias, 8 ) );
    $check( "$alias: native tools, writes only after the probe", $model['tool_use'] . '/' . ( $model['write_tools'] ? 'write' : 'read' ) . '/' . $model['write_gate'], 'native/write/probe' );
    $check( "$alias: context of one million tokens", $model['context_tokens'], 1000000 );
    $check( "$alias: prices in EUR", $model['price_currency'], 'EUR' );
}
// EUR prices x [Pricing] ExchangeRates[EUR]
$rate = (float) $ini->variable( 'Pricing', 'ExchangeRates' )['EUR'];
$check( 'rate: EUR from [Pricing] ExchangeRates', expAiCost::rate( 'EUR', $config ), $rate );
$check( 'rate: the site currency is 1', expAiCost::rate( 'usd', $config ), 1.0 );
$check( 'rate: no currency is 1', expAiCost::rate( '', $config ), 1.0 );
$check( 'rate: unknown currency', expAiCost::rate( 'GBP', $config ), null );
$flash = $registry->getModel( 'melious.glm-5.3-flash' );
$check( 'price: glm-5.3-flash input', $flash['prices']['input'], 0.10 * $rate );
$check( 'price: glm-5.3-flash output', $flash['prices']['output'], 0.40 * $rate );
$check( 'price: glm-5.3-flash cache read', $flash['prices']['cache_read'], 0.02 * $rate );
$check( 'price: glm-5.3 output', $registry->getModel( 'melious.glm-5.3' )['prices']['output'], 3.0 * $rate );
$check( 'price: deepseek-v4-flash-0731 output', $registry->getModel( 'melious.deepseek-v4-flash-0731' )['prices']['output'], 0.25 * $rate );
$check( 'price: deepseek-v4.1-flash input', $registry->getModel( 'melious.deepseek-v4.1-flash' )['prices']['input'], 0.20 * $rate );
// without a rate the prices are unknown, never taken as dollars
$noRate = eZINI::fetchFromFile( 'extension/expai/settings/expai.ini.append.php' );
$noRate->setVariable( 'ProviderSettings', 'Providers', array( 'anthropic', 'melious' ) );
$noRate->setVariable( 'Pricing', 'ExchangeRates', array() );
$noRateModel = ( new expAiConfig( $noRate ) )->registry()->getModel( 'melious.glm-5.3' );
$check( 'price: no rate, no price', $noRateModel['prices']['input'] === null && $noRateModel['prices']['output'] === null, true );
// a site that counts in EUR takes the prices as they are
$euro = eZINI::fetchFromFile( 'extension/expai/settings/expai.ini.append.php' );
$euro->setVariable( 'ProviderSettings', 'Providers', array( 'anthropic', 'melious' ) );
$euro->setVariable( 'Pricing', 'Currency', 'EUR' );
$check( 'price: a EUR site uses the EUR price', ( new expAiConfig( $euro ) )->registry()->getModel( 'melious.glm-5.3' )['prices']['output'], 3.0 );

// ---------------------------------------------------------------- the request
$settings = $registry->settingsFor( 'melious.deepseek-v4-flash-0731' );
$settings['ApiKey'] = 'test-key';
$provider = new expAiOpenAiChatProvider( $settings );
$check( 'adapter: flavor melious', $provider->flavor(), 'melious' );
$check( 'adapter: endpoint', $provider->endpoint(), 'https://api.melious.ai/v1/chat/completions' );
$check( 'adapter: tool_choice supported', $provider->supportsToolChoice(), true );
$tools = array( array( 'name' => 'get_node', 'description' => 'Read a content node by its id.',
                       'input_schema' => array( 'type' => 'object', 'properties' => array( 'node_id' => array( 'type' => 'integer' ) ), 'required' => array( 'node_id' ) ) ) );
$user = array( array( 'role' => 'user', 'content' => 'Read node 2 with the tool.' ) );
$payload = $provider->buildPayload( array( 'system' => 'Be brief.', 'messages' => $user, 'tools' => $tools, 'tool_choice' => array( 'type' => 'any' ) ) );
$check( 'request: model id', $payload['model'], 'deepseek-v4-flash-0731' );
$check( 'request: max_tokens from MaxOutput', isset( $payload['max_tokens'] ) && !isset( $payload['max_completion_tokens'] ) ? $payload['max_tokens'] : null, 8000 );
$check( 'request: reasoning_effort low', isset( $payload['reasoning_effort'] ) ? $payload['reasoning_effort'] : null, 'low' );
$check( 'request: tools as functions', $payload['tools'][0]['type'] . '/' . $payload['tools'][0]['function']['name'], 'function/get_node' );
$check( 'request: any is required', $payload['tool_choice'], 'required' );
$check( 'request: no streaming', $payload['stream'], false );
$check( 'request: system first', $payload['messages'][0]['role'], 'system' );
$schema = array( 'type' => 'object', 'properties' => array( 'title' => array( 'type' => 'string' ) ) );
$payload = $provider->buildPayload( array( 'messages' => $user, 'output_schema' => $schema ) );
$check( 'request: deepseek-v4-flash-0731 json_schema', $payload['response_format']['type'], 'json_schema' );
$glmSettings = $registry->settingsFor( 'melious.glm-5.3' );
$glmSettings['ApiKey'] = 'test-key';
$glm = new expAiOpenAiChatProvider( $glmSettings );
$payload = $glm->buildPayload( array( 'messages' => $user, 'output_schema' => $schema ) );
$check( 'request: glm-5.3 json_object', $payload['response_format'], array( 'type' => 'json_object' ) );
$headers = new ReflectionMethod( 'expAiOpenAiChatProvider', 'keyHeaders' );
$headers->setAccessible( true );
$check( 'request: Bearer key header', $headers->invoke( $provider, 'authorization' ), array( 'Authorization: Bearer test-key' ) );

// ---------------------------------------------------------------- the answers (recorded)
$text = $glm->parseAnswer( $fixture( 'chat-text.json' ) );
$check( 'text: the answer', $text['content'], array( array( 'type' => 'text', 'text' => 'Paris' ) ) );
$check( 'text: stop end_turn', $text['stop_reason'], 'end_turn' );
$check( 'text: input tokens', $text['tokens']['input'], 22 );
$check( 'text: output tokens', $text['tokens']['output'], 3 );
$check( 'text: reasoning tokens', $text['tokens']['reasoning'], 1 );
$check( 'text: reported cost from billing_cost.credits', $text['tokens']['reported_cost'], 0.0000034 );
$check( 'text: reported in EUR', $text['tokens']['reported_currency'], 'EUR' );
$check( 'text: model from the answer', $text['model'], 'glm-5.3-flash' );
$check( 'text: provider', $text['provider'], 'melious' );

$tool = $provider->parseAnswer( $fixture( 'chat-tool-call.json' ) );
$uses = array_values( array_filter( $tool['content'], function ( $b ) { return $b['type'] === 'tool_use'; } ) );
$check( 'tool: one call', count( $uses ), 1 );
$check( 'tool: the call', array( $uses[0]['id'], $uses[0]['name'], $uses[0]['input'] ), array( 'call_t385fixture0', 'get_node', array( 'node_id' => 2 ) ) );
$check( 'tool: empty content is no text block', count( $tool['content'] ), 1 );
$check( 'tool: stop tool_use', $tool['stop_reason'], 'tool_use' );
$check( 'tool: tokens', array( $tool['tokens']['input'], $tool['tokens']['output'], $tool['tokens']['reasoning'] ), array( 367, 64, 19 ) );
$check( 'tool: reported cost', $tool['tokens']['reported_cost'], 0.0000527 );
// the reported cost equals the list price: 367 x 0.10 + 64 x 0.25 EUR per million
$check( 'tool: the reported cost is the list price', ( 367 * 0.10 + 64 * 0.25 ) / 1000000, 0.0000527 );
$check( 'tool: no invalid input', $tool['invalid_tool_input'], array() );

// cached tokens are cache reads, not input
$cached = $fixture( 'chat-text.json' );
$cached['usage']['prompt_tokens'] = 393;
$cached['usage']['prompt_tokens_details']['cached_tokens'] = 256;
$cached['usage']['cached_tokens'] = 256;
$parsed = $glm->parseAnswer( $cached );
$check( 'usage: cached tokens are cache reads', array( $parsed['tokens']['input'], $parsed['tokens']['cache_read'] ), array( 137, 256 ) );
$noCost = $fixture( 'chat-text.json' );
unset( $noCost['billing_cost'] );
$check( 'usage: no billing_cost, no reported cost', $glm->parseAnswer( $noCost )['tokens']['reported_cost'], null );

// ---------------------------------------------------------------- the next turn: the answer sent back
$history = array_merge( $user, array(
    array( 'role' => 'assistant', 'content_json' => $tool['content_json'], 'native_json' => $tool['native_json'], 'native_for' => $tool['native_for'], 'native_ids' => $tool['native_ids'] ),
    array( 'role' => 'user', 'content' => array( array( 'type' => 'tool_result', 'tool_use_id' => 'call_t385fixture0', 'content' => '{"name":"Home"}' ) ) ),
) );
$payload = $provider->buildPayload( array( 'messages' => $history, 'tools' => $tools ) );
$resent = $payload['messages'][1];
$check( 'resend: the native message goes back', $resent['tool_calls'][0]['id'], 'call_t385fixture0' );
$check( 'resend: reasoning_content kept (DeepSeek wants it on a tool turn)', isset( $resent['reasoning_content'] ), true );
$check( 'resend: no provider_specific_fields', array_key_exists( 'provider_specific_fields', $resent ), false );
$check( 'resend: no null function_call', array_key_exists( 'function_call', $resent ), false );
$check( 'resend: the tool result', $payload['messages'][2], array( 'role' => 'tool', 'tool_call_id' => 'call_t385fixture0', 'content' => '{"name":"Home"}' ) );
$textBack = $glm->buildPayload( array( 'messages' => array_merge( $user, array(
    array( 'role' => 'assistant', 'content_json' => $text['content_json'], 'native_json' => $text['native_json'], 'native_for' => $text['native_for'] ),
    array( 'role' => 'user', 'content' => 'Thanks' ) ) ) ) );
$check( 'resend: a text answer has no null tool_calls', array_key_exists( 'tool_calls', $textBack['messages'][1] ), false );
$check( 'resend: a text answer keeps its words', $textBack['messages'][1]['content'], 'Paris' );
// another model of the same provider gets the canonical blocks, not the native message
$other = $glm->buildPayload( array( 'messages' => $history, 'tools' => $tools ) );
$check( 'resend: another model gets canonical blocks', isset( $other['messages'][1]['reasoning_content'] ), false );

// ---------------------------------------------------------------- errors (recorded, and the shapes of the reference)
$error = new ReflectionMethod( 'expAiOpenAiChatProvider', 'httpError' );
$error->setAccessible( true );
$e = $error->invoke( $provider, 404, $fixture( 'error-unknown-model.json' ), array() );
$check( 'error: unknown model text', strtr( $e->getMessage(), $e->arguments() ), 'Melious does not know the configured model. Please tell the administrator.' );
$check( 'error: unknown model detail', strpos( $e->detail(), 'HTTP 404 invalid_request_error: Model not found' ) !== false, true );
$e = $error->invoke( $provider, 401, $fixture( 'error-bad-key.json' ), array() );
$check( 'error: bad key text', strtr( $e->getMessage(), $e->arguments() ), 'Melious refused the request (access). Please tell the administrator.' );
$check( 'error: bad key detail', strpos( $e->detail(), 'authentication_error: Invalid API key format' ) !== false, true );
$e = $error->invoke( $provider, 429, array( 'error' => array( 'code' => 'AUTH_1028', 'message' => 'Too many requests' ) ), array( 'retry-after' => '2', 'x-ratelimit-remaining' => '0' ) );
$check( 'error: rate limit text', strtr( $e->getMessage(), $e->arguments() ), 'Melious is busy or over its limit. Please try again later.' );
$check( 'error: rate limit code', strpos( $e->detail(), 'AUTH_1028' ) !== false, true );
$check( 'error: rate limit headers kept', $e->rateLimits, array( 'retry-after' => '2', 'x-ratelimit-remaining' => '0' ) );
$e = $error->invoke( $provider, 402, array( 'error' => array( 'code' => 'BILLING_2001', 'message' => 'Out of energy' ) ), array() );
$check( 'error: no credit text', strtr( $e->getMessage(), $e->arguments() ), 'Melious refused the request (no credit left). Please tell the administrator.' );
$e = $error->invoke( $provider, 400, array( 'error' => array( 'code' => 'INFERENCE_3207', 'message' => 'Context window exceeded for sk-mel-abcdefgh1234' ) ), array() );
$check( 'error: Melious code in the detail', strpos( $e->detail(), 'INFERENCE_3207' ) !== false, true );
$check( 'error: key-like text redacted', strpos( $e->detail(), 'sk-mel-abcdefgh1234' ), false );
$e = $thrown( function () use ( $provider ) { $provider->parseAnswer( array( 'error' => array( 'code' => 'INFERENCE_3208', 'message' => 'Content filter' ) ) ); } );
$check( 'error: an error object without choices is an error', $e ? $e->getCode() : null, 502 );
$rate = $provider->rateLimitHeaders();
$check( 'rate limit headers: x-ratelimit-* kept', in_array( 'x-ratelimit-*', $rate, true ), true );

// ---------------------------------------------------------------- streaming (recorded): expai asks for whole answers
$sse = (string) file_get_contents( 'extension/expai/tests/fixtures/melious/stream-with-usage.sse' );
$chunks = array();
foreach ( preg_split( '/\n\n/', trim( $sse ) ) as $event )
    if ( strpos( $event, 'data: ' ) === 0 && trim( substr( $event, 6 ) ) !== '[DONE]' )
        $chunks[] = json_decode( substr( $event, 6 ), true );
$last = end( $chunks );
$check( 'stream: Melious streams chunks', count( $chunks ) > 2 && $chunks[0]['object'] === 'chat.completion.chunk', true );
$check( 'stream: usage arrives in the last chunk (include_usage)', isset( $last['usage']['prompt_tokens'] ), true );
$check( 'stream: ends with [DONE]', substr( trim( $sse ), -12 ), 'data: [DONE]' );

// ---------------------------------------------------------------- the data policy
$policy = new expAiDataPolicy( $config, $melious );
$check( 'policy: an eu requirement is met', $policy->meets( array( 'regions' => array( 'eu' ), 'zero_retention' => false ) ), true );
$check( 'policy: zero retention is met', $policy->meets( array( 'regions' => null, 'zero_retention' => true ) ), true );
$check( 'policy: a local-only requirement is not met', $policy->meets( array( 'regions' => array( 'local' ), 'zero_retention' => false ) ), false );

// ---------------------------------------------------------------- a chain run whose insert failed (found with the Melious chain)
// SQLite answered "database is locked" to the insert of a run; the run kept the id 0 and the next store inserted the row 0
$check( 'chain run: fetch(0) is no run', expAiChainRun::fetch( 0 ), null );
$broken = new expAiChainRun( array( 'id' => 0, 'uuid' => 't385-not-stored', 'chain' => 'melious', 'created' => time() ) );
$broken->store();
$check( 'chain run: a run with the id 0 is not stored', expAiChainRun::fetch( 't385-not-stored' ), null );

// the fixtures carry no key or account data
$all = '';
foreach ( glob( 'extension/expai/tests/fixtures/melious/*' ) as $file )
    $all .= file_get_contents( $file );
$check( 'fixtures: no key, no Bearer value, no request id', (bool) preg_match( '/sk-mel-|Bearer\s|x-request-id/i', $all ), false );

echo ( $failures ? 'FAIL' : 'PASS' ) . " $checks checks, $failures failure(s)\n";
$script = eZScript::instance();
$script->shutdown( $failures ? 1 : 0 );
