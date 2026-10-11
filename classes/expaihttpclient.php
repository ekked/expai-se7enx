<?php
/**
 * File containing the expAiHttpClient class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * JSON POST to an AI provider with curl: http(s) only, no redirects, a total and a connect timeout and a limit on
 * the size of the answer. Errors are expAiException whose texts never contain the request, the answer or the
 * headers (the API key travels in a header).
 */
class expAiHttpClient
{
    /** @var int */
    protected $timeout;

    /** @var int */
    protected $connectTimeout;

    /** @var int */
    protected $maxResponseBytes;

    public function __construct( $timeout = 115, $connectTimeout = 10, $maxResponseBytes = 4194304 )
    {
        $this->timeout = max( 1, min( 600, (int) $timeout ) );
        $this->connectTimeout = max( 1, min( $this->timeout, (int) $connectTimeout ) );
        $this->maxResponseBytes = max( 1024, (int) $maxResponseBytes );
    }

    /**
     * @param string $url
     * @param array $headers list of "Name: value"
     * @param string $body JSON
     * @return array( int status, string body, array rate limit headers: lower-case name => value )
     * @throws expAiException
     */
    public function postJson( $url, array $headers, $body )
    {
        if ( !is_string( $url ) || !preg_match( '~^https?://[^\s/?#@]+(?::\d+)?(?:[/?#]\S*)?$~i', $url ) )
            throw new expAiException( 'Exponential Intelligence is not configured correctly.', 'invalid endpoint (http or https without credentials)' );
        if ( !function_exists( 'curl_init' ) )
            throw new expAiException( 'Exponential Intelligence is not available on this server.', 'the PHP curl extension is missing' );

        $received = '';
        $tooLarge = false;
        $responseHeaders = array();
        $limit = $this->maxResponseBytes;
        $ch = curl_init( $url );
        curl_setopt_array( $ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array_merge( array( 'Content-Type: application/json', 'Accept: application/json' ), $headers ),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Exponential expai',
            // the rate limit headers (anthropic-ratelimit-*, retry-after) for the usage log; nothing else is kept
            CURLOPT_HEADERFUNCTION => static function ( $handle, $line ) use ( &$responseHeaders )
            {
                if ( preg_match( '/^(anthropic-ratelimit-[a-z-]+|retry-after):\s*(.*?)\s*$/i', $line, $m ) )
                    $responseHeaders[strtolower( $m[1] )] = $m[2];
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
