<?php
/**
 * File containing the expAiHttpClient class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * JSON POST to an AI provider (or an MCP server) with curl: http(s) only, no redirects, a total and a connect timeout
 * and a limit on the size of the answer. Of the response headers only the ones of the allowlist are kept (rate limit
 * headers for the usage log, glob patterns such as anthropic-ratelimit-* or x-ratelimit-*). Errors are expAiException
 * whose texts never contain the request, the answer or the headers (the API key travels in a header).
 */
class expAiHttpClient
{
    /** @var int */
    protected $timeout;

    /** @var int */
    protected $connectTimeout;

    /** @var int */
    protected $maxResponseBytes;

    /** @var string[] lower-case glob patterns of the response headers to keep */
    protected $keepHeaders;

    /**
     * @param string[]|null $keepHeaders glob patterns of the response headers to keep (default: the Anthropic rate
     *                                   limit headers and retry-after)
     */
    public function __construct( $timeout = 115, $connectTimeout = 10, $maxResponseBytes = 4194304, $keepHeaders = null )
    {
        $this->timeout = max( 1, min( 600, (int) $timeout ) );
        $this->connectTimeout = max( 1, min( $this->timeout, (int) $connectTimeout ) );
        $this->maxResponseBytes = max( 1024, (int) $maxResponseBytes );
        $this->keepHeaders = array_map( 'strtolower', is_array( $keepHeaders ) && $keepHeaders ? array_values( $keepHeaders )
                                                      : array( 'anthropic-ratelimit-*', 'retry-after' ) );
    }

    /** @return int seconds */
    public function timeout()
    {
        return $this->timeout;
    }

    /** @return bool the header is on the allowlist */
    public function keeps( $name )
    {
        $name = strtolower( $name );
        foreach ( $this->keepHeaders as $pattern )
            if ( fnmatch( $pattern, $name ) )
                return true;
        return false;
    }

    /**
     * @param string $url
     * @param array $headers list of "Name: value"
     * @param string $body JSON
     * @return array( int status, string body, array kept headers: lower-case name => value )
     * @throws expAiException
     */
    public function postJson( $url, array $headers, $body )
    {
        return $this->request( $url, $headers, $body );
    }

    /**
     * @param array $options accept (Accept header, default application/json), resolve (curl CURLOPT_RESOLVE list,
     *                       used to pin a checked address), method (GET, POST, DELETE; default POST),
     *                       content_type (of the body, default application/json)
     * @return array( int status, string body, array kept headers )
     * @throws expAiException
     */
    public function request( $url, array $headers, $body, array $options = array() )
    {
        if ( !is_string( $url ) || !preg_match( '~^https?://[^\s/?#@]+(?::\d+)?(?:[/?#]\S*)?$~i', $url ) )
            throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'invalid endpoint (http or https without credentials)' );
        if ( !function_exists( 'curl_init' ) )
            throw new expAiException( 'Exponential Intelligence is not available on this server.', 'the PHP curl extension is missing' );

        $received = '';
        $tooLarge = false;
        $responseHeaders = array();
        $limit = $this->maxResponseBytes;
        $client = $this;
        $accept = isset( $options['accept'] ) ? (string) $options['accept'] : 'application/json';
        $ch = curl_init( $url );
        $method = isset( $options['method'] ) ? strtoupper( (string) $options['method'] ) : 'POST';
        if ( !in_array( $method, array( 'GET', 'POST', 'DELETE' ), true ) )
            throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'unsupported HTTP method' );
        $get = $method === 'GET';
        // DELETE without a body sends no content type (batch APIs); content_type: e.g. multipart/form-data; boundary=...
        $noBody = $get || ( $method === 'DELETE' && (string) $body === '' );
        $contentType = isset( $options['content_type'] ) && !preg_match( '/[\r\n]/', (string) $options['content_type'] ) ? (string) $options['content_type'] : 'application/json';
        curl_setopt_array( $ch, array(
            CURLOPT_POST => $method === 'POST',
            CURLOPT_HTTPGET => $get,
            CURLOPT_HTTPHEADER => array_merge( $noBody ? array( 'Accept: ' . $accept ) : array( 'Content-Type: ' . $contentType, 'Accept: ' . $accept ), $headers ),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Exponential expai',
            // only the headers of the allowlist (rate limits, retry-after, content-type, mcp-session-id ...) are kept
            CURLOPT_HEADERFUNCTION => static function ( $handle, $line ) use ( &$responseHeaders, $client )
            {
                if ( preg_match( '/^([A-Za-z0-9-]+):\s*(.*?)\s*$/', $line, $m ) && $client->keeps( $m[1] ) )
                    $responseHeaders[strtolower( $m[1] )] = substr( $m[2], 0, 200 );
                return strlen( $line );
            },
            CURLOPT_WRITEFUNCTION => static function ( $handle, $chunk ) use ( &$received, &$tooLarge, $limit )
            {
                if ( strlen( $received ) + strlen( $chunk ) > $limit )
                {
                    $tooLarge = true;
                    return 0;
                }
                $received .= $chunk;
                return strlen( $chunk );
            },
        ) );
        if ( $method === 'DELETE' )
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'DELETE' );
        if ( !$noBody )
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
        if ( !empty( $options['resolve'] ) )
            curl_setopt( $ch, CURLOPT_RESOLVE, (array) $options['resolve'] );
        if ( defined( 'CURLOPT_PROTOCOLS_STR' ) )
            curl_setopt( $ch, CURLOPT_PROTOCOLS_STR, 'http,https' );
        elseif ( defined( 'CURLOPT_PROTOCOLS' ) )
            curl_setopt( $ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS );

        $ok = curl_exec( $ch );
        $errno = curl_errno( $ch );
        $status = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
        if ( PHP_VERSION_ID < 80000 )
            curl_close( $ch );
        unset( $ch );

        if ( $tooLarge )
            throw new expAiException( 'The answer of Exponential Intelligence was too long.', 'response larger than ' . $limit . ' bytes', $status );
        if ( $ok === false || $errno )
        {
            if ( $errno === 28 )
                throw new expAiException( 'Exponential Intelligence did not answer in time. Please try again with a smaller request.', 'timeout after ' . $this->timeout . ' s' );
            throw new expAiException( 'Exponential Intelligence could not be reached.', 'curl error ' . $errno );
        }
        return array( $status, $received, $responseHeaders );
    }
}
