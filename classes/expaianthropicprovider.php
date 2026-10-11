<?php
/**
 * File containing the expAiAnthropicProvider class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The Anthropic Messages API (POST {Endpoint}, default https://api.anthropic.com/v1/messages) with tool use,
 * adaptive thinking at the configured effort, optional structured output, prompt caching and the server-side
 * refusal fallback. Earlier assistant turns are sent back exactly as they were received (content_json), as the
 * API requires for thinking blocks; the conversation is only ever appended to.
 */
class expAiAnthropicProvider implements expAiProvider
{
    const DEFAULT_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    const DEFAULT_VERSION = '2023-06-01';
    const FALLBACK_BETA = 'server-side-fallback-2026-07-01';
    const BINDING_BETA = 'thinking-binding-controls-2026-08-01';

    /** @var array */
    protected $settings;

    /** @var expAiHttpClient */
    protected $http;

    /**
     * @param array $settings [AISettings] values (expAiConfig::providerSettings())
     * @param expAiHttpClient|null $http
     */
    public function __construct( array $settings, $http = null )
    {
        $this->settings = $settings;
        $this->http = $http ?: new expAiHttpClient(
            $this->setting( 'Timeout', 115 ), $this->setting( 'ConnectTimeout', 10 ), $this->setting( 'MaxResponseBytes', 4194304 ) );
    }

    protected function setting( $name, $default = '' )
    {
        return isset( $this->settings[$name] ) && $this->settings[$name] !== '' && $this->settings[$name] !== null
             ? $this->settings[$name] : $default;
    }

    protected function enabled( $name, $default )
    {
        $value = strtolower( trim( (string) $this->setting( $name, $default ) ) );
        return in_array( $value, array( 'enabled', 'true', '1', 'yes', 'on' ), true );
    }

    public function providerId()
    {
        return 'anthropic';
    }

    public function modelName()
    {
        return trim( (string) $this->setting( 'Model', '' ) );
    }

    /** @return array the request body (exposed for tests and doc examples; contains no key) */
    public function buildPayload( array $request )
    {
        $payload = array(
            'model' => $this->modelName(),
            'max_tokens' => (int) ( isset( $request['max_tokens'] ) ? $request['max_tokens'] : $this->setting( 'MaxTokens', 8000 ) ),
            'system' => (string) $request['system'],
            'messages' => array(),
        );
        foreach ( $request['messages'] as $message )
        {
            if ( isset( $message['content_json'] ) )
                $payload['messages'][] = array( 'role' => $message['role'], 'content' => json_decode( $message['content_json'] ) );
            else
                $payload['messages'][] = array( 'role' => $message['role'], 'content' => $message['content'] );
        }
        if ( !empty( $request['tools'] ) )
        {
            $payload['tools'] = $request['tools'];
            if ( isset( $request['tool_choice'] ) )
                $payload['tool_choice'] = $request['tool_choice'];
        }
        $outputConfig = array();
        $effort = strtolower( trim( (string) $this->setting( 'Effort', '' ) ) );
        if ( in_array( $effort, array( 'low', 'medium', 'high', 'xhigh', 'max' ), true ) )
            $outputConfig['effort'] = $effort;
        if ( !empty( $request['output_schema'] ) )
            $outputConfig['format'] = array( 'type' => 'json_schema', 'schema' => $request['output_schema'] );
        if ( $outputConfig )
            $payload['output_config'] = $outputConfig;
        if ( $this->enabled( 'PromptCaching', 'enabled' ) )
            $payload['cache_control'] = array( 'type' => 'ephemeral' );
        if ( $this->enabled( 'RefusalFallback', 'enabled' ) )
            $payload['fallbacks'] = 'default';
        return $payload;
    }

    public function send( array $request )
    {
        $endpoint = trim( (string) $this->setting( 'Endpoint', self::DEFAULT_ENDPOINT ) );
        $key = (string) $this->setting( 'ApiKey', '' );
        if ( $key === '' )
            throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'ApiKey is not set' );
        if ( $this->modelName() === '' )
            throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'Model is not set' );

        $headers = array( 'x-api-key: ' . $key, 'anthropic-version: ' . $this->setting( 'AnthropicVersion', self::DEFAULT_VERSION ) );
        $workspace = trim( (string) $this->setting( 'AnthropicWorkspaceId', '' ) );
        if ( $workspace !== '' )
        {
            if ( !preg_match( '/^[A-Za-z0-9_-]{1,100}$/', $workspace ) )
                throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'AnthropicWorkspaceId is not a workspace id' );
            $headers[] = 'anthropic-workspace-id: ' . $workspace;
        }
        $payload = $this->buildPayload( $request );
        if ( isset( $payload['fallbacks'] ) )
            $headers[] = 'anthropic-beta: ' . self::FALLBACK_BETA;

        $body = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
        if ( $body === false )
            throw new expAiException( 'The request could not be sent to Exponential Intelligence.', 'json_encode failed' );

        list( $status, $received, $rateHeaders ) = array_pad( $this->http->postJson( $endpoint, $headers, $body ), 3, array() );
        $data = json_decode( $received, true );
        $retries = 0;
        // Thinking blocks are bound to the exact start of the conversation. When that start changed (a conversation
        // stored before its instructions were kept with it), the API refuses the old blocks: retry once and let it
        // drop them (the conversation goes on without the earlier reasoning).
        if ( $status === 400 && is_array( $data ) && isset( $data['error']['message'] ) && is_string( $data['error']['message'] )
             && strpos( $data['error']['message'], 'block_binding' ) !== false && empty( $request['_drop_block'] ) )
        {
            eZDebug::writeNotice( 'expai: thinking blocks bound to another conversation start, retrying with drop_block', __METHOD__ );
            $payload['thinking'] = array( 'type' => 'adaptive', 'block_binding' => array( 'prefix_mismatch_behavior' => 'drop_block' ) );
            $betas = array( self::BINDING_BETA );
            if ( isset( $payload['fallbacks'] ) )
                $betas[] = self::FALLBACK_BETA;
            $retryHeaders = array_values( array_filter( $headers, function ( $h ) { return stripos( $h, 'anthropic-beta:' ) !== 0; } ) );
            $retryHeaders[] = 'anthropic-beta: ' . implode( ',', $betas );
            $body = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
            list( $status, $received, $rateHeaders ) = array_pad( $this->http->postJson( $endpoint, $retryHeaders, $body ), 3, array() );
            $data = json_decode( $received, true );
            $retries = 1;
        }
        if ( $status < 200 || $status >= 300 )
        {
            $type = is_array( $data ) && isset( $data['error']['type'] ) && is_string( $data['error']['type'] )
                  ? preg_replace( '/[^\w.-]/', '', substr( $data['error']['type'], 0, 60 ) ) : '';
            $apiMessage = is_array( $data ) && isset( $data['error']['message'] ) && is_string( $data['error']['message'] )
                        ? substr( preg_replace( '/[\x00-\x1f]/', ' ', $data['error']['message'] ), 0, 300 ) : '';
            $message = 'Exponential Intelligence reported an error.';
            if ( $status === 401 || $status === 403 )
                $message = 'Exponential Intelligence refused the request (access). Please tell the administrator.';
            elseif ( $status === 429 || $status === 529 )
                $message = 'Exponential Intelligence is busy or over its limit. Please try again later.';
            elseif ( $status === 413 )
                $message = 'The request is too long for Exponential Intelligence.';
            $error = new expAiException( $message, 'HTTP ' . $status . ( $type !== '' ? ' ' . $type : '' ) . ( $apiMessage !== '' ? ': ' . $apiMessage : '' ), $status );
            $error->rateLimits = $rateHeaders;
            throw $error;
        }
        $object = json_decode( $received );
        if ( !is_array( $data ) || !isset( $data['content'] ) || !is_array( $data['content'] ) || !is_object( $object ) )
            throw new expAiException( 'The answer of Exponential Intelligence could not be read.', 'HTTP ' . $status . ', no content[]', $status );
        return array(
            'content' => $data['content'],
            'content_json' => json_encode( $object->content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'stop_reason' => isset( $data['stop_reason'] ) ? (string) $data['stop_reason'] : '',
            'model' => isset( $data['model'] ) ? (string) $data['model'] : $this->modelName(),
            'usage' => isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array(),
            // the normalised token counts the usage log, prices and limits use (other providers map theirs to these)
            'tokens' => array( 'input' => isset( $data['usage']['input_tokens'] ) ? (int) $data['usage']['input_tokens'] : 0,
                               'output' => isset( $data['usage']['output_tokens'] ) ? (int) $data['usage']['output_tokens'] : 0,
                               'cache_write' => isset( $data['usage']['cache_creation_input_tokens'] ) ? (int) $data['usage']['cache_creation_input_tokens'] : 0,
                               'cache_read' => isset( $data['usage']['cache_read_input_tokens'] ) ? (int) $data['usage']['cache_read_input_tokens'] : 0 ),
            'http_status' => $status,
            'retries' => $retries,
            'rate_limits' => $rateHeaders,
        );
    }

    /**
     * The provider of the settings.
     * @return expAiProvider
     * @throws expAiException
     */
    public static function fromConfig( expAiConfig $config )
    {
        $provider = (string) $config->get( 'AISettings', 'Provider', 'anthropic' );
        if ( $provider === 'custom' )
        {
            $class = trim( (string) $config->get( 'AISettings', 'ProviderClass', '' ) );
            if ( $class === '' || !class_exists( $class ) || !in_array( 'expAiProvider', class_implements( $class ), true ) )
                throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'ProviderClass does not implement expAiProvider' );
            return new $class( $config->providerSettings() );
        }
        if ( $provider !== 'anthropic' )
            throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'unknown Provider' );
        return new self( $config->providerSettings() );
    }
}
