<?php
/**
 * File containing the expAiProviderBase class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * What every built-in provider shares: its settings (the [Provider_<id>] values merged with the [Model_<alias>]
 * values, see expAiProviderRegistry::settingsFor()), the HTTP client with the provider's timeouts and rate limit
 * header allowlist, the API key header, one retry for "busy" and server errors, and the mapping of HTTP errors to
 * texts for the editor that name the provider. The key is never part of a message, a detail or a log line.
 */
abstract class expAiProviderBase implements expAiProvider
{
    /** @var array */
    protected $settings;

    /** @var expAiHttpClient */
    protected $http;

    /** @var string[] glob patterns of the response headers kept for the usage log */
    protected $defaultRateLimitHeaders = array( 'retry-after' );

    /**
     * @param array $settings provider and model settings (contains the key: never log or output it)
     * @param expAiHttpClient|null $http
     */
    public function __construct( array $settings, $http = null )
    {
        $this->settings = $settings;
        $this->http = $http ?: new expAiHttpClient(
            $this->setting( 'Timeout', 115 ), $this->setting( 'ConnectTimeout', 10 ), $this->setting( 'MaxResponseBytes', 4194304 ),
            $this->rateLimitHeaders() );
    }

    /** @return mixed */
    public function setting( $name, $default = '' )
    {
        return isset( $this->settings[$name] ) && $this->settings[$name] !== '' && $this->settings[$name] !== null && $this->settings[$name] !== array()
             ? $this->settings[$name] : $default;
    }

    /** @return bool */
    protected function enabled( $name, $default )
    {
        $value = strtolower( trim( (string) $this->setting( $name, $default ) ) );
        return in_array( $value, array( 'enabled', 'true', '1', 'yes', 'on' ), true );
    }

    /** @return string[] */
    protected function listSetting( $name )
    {
        $value = $this->setting( $name, array() );
        $value = is_array( $value ) ? $value : array( $value );
        return array_values( array_filter( array_map( 'trim', array_map( 'strval', $value ) ), 'strlen' ) );
    }

    /** @return string[] the response headers kept for the usage log ([Provider_<id>] RateLimitHeaders[]) */
    public function rateLimitHeaders()
    {
        $list = $this->listSetting( 'RateLimitHeaders' );
        return $list ?: $this->defaultRateLimitHeaders;
    }

    public function providerId()
    {
        return (string) $this->setting( 'ProviderId', $this->defaultProviderId() );
    }

    /** @return string */
    abstract protected function defaultProviderId();

    /** @return string the name the editor reads in error texts ([Provider_<id>] Label) */
    public function providerLabel()
    {
        return (string) $this->setting( 'Label', $this->providerId() );
    }

    /** @return string the alias of the model in the registry ('' for a provider built without it) */
    public function modelAlias()
    {
        return (string) $this->setting( 'ModelAlias', '' );
    }

    public function modelName()
    {
        return trim( (string) $this->setting( 'Model', '' ) );
    }

    /** @return string native | prompted | none */
    public function toolUse()
    {
        $mode = strtolower( (string) $this->setting( 'ToolUse', 'native' ) );
        return in_array( $mode, array( 'native', 'prompted', 'none' ), true ) ? $mode : 'native';
    }

    /** @return string[] the capabilities of the model ([Model_<alias>] Capabilities[]) */
    public function capabilities()
    {
        return array_map( 'strtolower', $this->listSetting( 'Capabilities' ) );
    }

    /** @return bool */
    public function hasCapability( $name )
    {
        return in_array( strtolower( $name ), $this->capabilities(), true );
    }

    /**
     * The API key header: ApiKeyHeader x-api-key (default of anthropic), authorization (Bearer, default of the
     * others), api-key (Azure), x-goog-api-key, or none (a local server without a key).
     * @return string[] header lines, empty without a key
     * @throws expAiException when a key is required and missing
     */
    protected function keyHeaders( $defaultHeader )
    {
        $header = strtolower( trim( (string) $this->setting( 'ApiKeyHeader', $defaultHeader ) ) );
        if ( $header === 'none' )
            return array();
        $key = trim( (string) $this->setting( 'ApiKey', '' ) );
        if ( $key === '' )
        {
            if ( $this->enabled( 'KeyOptional', 'false' ) )
                return array();
            throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'ApiKey of provider ' . $this->providerId() . ' is not set' );
        }
        if ( preg_match( '/[\r\n]/', $key ) )
            throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'ApiKey of provider ' . $this->providerId() . ' contains a line break' );
        if ( $header === 'authorization' )
            return array( 'Authorization: Bearer ' . $key );
        if ( !preg_match( '/^[a-z0-9-]{1,60}$/', $header ) )
            throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'ApiKeyHeader of provider ' . $this->providerId() . ' is not a header name' );
        return array( $header . ': ' . $key );
    }

    /**
     * [Provider_<id>] Headers[] ("Name: value" lines), checked: no line breaks, no key headers.
     * @return string[]
     */
    protected function extraHeaders()
    {
        $out = array();
        foreach ( $this->listSetting( 'Headers' ) as $line )
        {
            if ( preg_match( '/[\r\n]/', $line ) || !preg_match( '/^([A-Za-z0-9-]{1,60}):\s*(.*)$/', $line, $m ) )
                continue;
            if ( in_array( strtolower( $m[1] ), array( 'authorization', 'x-api-key', 'api-key', 'x-goog-api-key', 'host', 'content-length', 'content-type' ), true ) )
                continue;
            $out[] = $m[1] . ': ' . $m[2];
        }
        return $out;
    }

    /**
     * POST with one retry: after a 429/503/529 that names a retry-after within [Provider_<id>] RetryAfterMax seconds
     * (default 5), and after a 500/502/504 or a connection failure ([Provider_<id>] RetryOnServerError, default
     * enabled), after one second.
     * @return array( int status, string body, array rate limit headers, int retries )
     * @throws expAiException
     */
    protected function post( $url, array $headers, $body )
    {
        $retries = 0;
        try
        {
            list( $status, $received, $rate ) = array_pad( $this->http->postJson( $url, $headers, $body ), 3, array() );
        }
        catch ( expAiException $e )
        {
            // a timeout is not retried: the time is spent
            if ( strpos( $e->detail(), 'curl error' ) !== 0 || !$this->enabled( 'RetryOnServerError', 'enabled' ) )
                throw $this->named( $e );
            usleep( 1000000 );
            $retries = 1;
            try
            {
                list( $status, $received, $rate ) = array_pad( $this->http->postJson( $url, $headers, $body ), 3, array() );
            }
            catch ( expAiException $again )
            {
                throw $this->named( $again );
            }
            return array( $status, $received, $rate, $retries );
        }
        $wait = null;
        if ( in_array( $status, array( 429, 503, 529 ), true ) && isset( $rate['retry-after'] ) && is_numeric( $rate['retry-after'] ) )
        {
            $seconds = (float) $rate['retry-after'];
            $max = (float) $this->setting( 'RetryAfterMax', 5 );
            if ( $seconds >= 0 && $seconds <= $max )
                $wait = $seconds;
        }
        elseif ( in_array( $status, array( 500, 502, 504 ), true ) && $this->enabled( 'RetryOnServerError', 'enabled' ) )
            $wait = 1;
        if ( $wait !== null )
        {
            usleep( (int) ( $wait * 1000000 ) );
            $retries = 1;
            try
            {
                list( $status, $received, $rate ) = array_pad( $this->http->postJson( $url, $headers, $body ), 3, array() );
            }
            catch ( expAiException $e )
            {
                throw $this->named( $e );
            }
        }
        return array( $status, $received, $rate, $retries );
    }

    /** @return expAiException the same failure, its detail naming the provider */
    protected function named( expAiException $e )
    {
        $copy = new expAiException( $e->getMessage(), 'provider ' . $this->providerId() . ': ' . $e->detail(), $e->getCode(), $e->arguments() );
        $copy->rateLimits = $e->rateLimits;
        return $copy;
    }

    /**
     * The exception of a failed HTTP request: a text for the editor by status, and for the log the status, the
     * provider's error type and message (shortened, control characters removed). Both the
     * { error: { type, message, code } } shape and a plain { error: "text" } (Hugging Face) are read.
     * @return expAiException
     */
    protected function httpError( $status, $data, array $rateHeaders, $retries = 0 )
    {
        $type = '';
        $apiMessage = '';
        if ( is_array( $data ) && isset( $data['error'] ) )
        {
            $error = $data['error'];
            if ( is_string( $error ) )
                $apiMessage = $error;
            elseif ( is_array( $error ) )
            {
                foreach ( array( 'type', 'code' ) as $field )
                    if ( $type === '' && isset( $error[$field] ) && is_scalar( $error[$field] ) && (string) $error[$field] !== (string) $status )
                        $type = (string) $error[$field];
                if ( isset( $error['message'] ) && is_string( $error['message'] ) )
                    $apiMessage = $error['message'];
            }
        }
        elseif ( is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) )
            $apiMessage = $data['message'];
        $type = preg_replace( '/[^\w.-]/', '', substr( $type, 0, 60 ) );
        $apiMessage = substr( preg_replace( '/[\x00-\x1f]/', ' ', $apiMessage ), 0, 300 );
        // never echo something that looks like a key
        $apiMessage = preg_replace( '/\b(sk-[A-Za-z0-9_-]{6,}|hf_[A-Za-z0-9]{6,}|Bearer\s+\S+)/', '[redacted]', $apiMessage );
        $detail = 'provider ' . $this->providerId() . ': HTTP ' . $status . ( $type !== '' ? ' ' . $type : '' )
                . ( $apiMessage !== '' ? ': ' . $apiMessage : '' ) . ( $retries ? ' (after ' . $retries . ' retry)' : '' );
        $arguments = array( '%provider' => $this->providerLabel() );
        // one literal per text, so that the translation files list each of them
        if ( $status === 401 || $status === 403 )
            $error = new expAiException( '%provider refused the request (access). Please tell the administrator.', $detail, $status, $arguments );
        elseif ( $status === 402 )
            $error = new expAiException( '%provider refused the request (no credit left). Please tell the administrator.', $detail, $status, $arguments );
        elseif ( $status === 429 || $status === 529 || $status === 503 )
            $error = new expAiException( '%provider is busy or over its limit. Please try again later.', $detail, $status, $arguments );
        elseif ( $status === 413 )
            $error = new expAiException( 'The request is too long for %provider.', $detail, $status, $arguments );
        elseif ( $status === 404 )
            $error = new expAiException( '%provider does not know the configured model. Please tell the administrator.', $detail, $status, $arguments );
        elseif ( $status === 408 || $status === 504 )
            $error = new expAiException( '%provider did not answer in time. Please try again with a smaller request.', $detail, $status, $arguments );
        else
            $error = new expAiException( '%provider reported an error.', $detail, $status, $arguments );
        $error->rateLimits = $rateHeaders;
        return $error;
    }

    /** @return string JSON of a payload */
    protected function encode( array $payload )
    {
        $body = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
        if ( $body === false )
            throw new expAiException( 'The request could not be sent to Exponential Intelligence.', 'json_encode failed' );
        return $body;
    }
}
