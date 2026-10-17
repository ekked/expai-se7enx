<?php
/**
 * File containing the expAiTemplateOperators class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * {$amount|expai_money( $approx )}  "$0.06", "< $0.01", with "≈" for an approximate cost (expAiCost::money())
 * {$tokens|expai_tokens}            "950", "21.6k", "1.2M" (expAiCost::tokens())
 * {$value|expai_number( 2 )}        the locale's separators (expAiCost::number())
 * {$retention|expai_retention}     "unknown" translated, every other retention as written
 * {'javascript/expai_x.js'|expai_version}  the modification time of a file of the admin design of expai, for ?v= on a script the
 *                                   templates load directly (a browser keeps an old copy until the address changes)
 * {$data|expai_json}                JSON for a <script type="application/json"> block: <, >, &, ' and " are escaped
 *                                   invalid UTF-8 becomes U+FFFD instead of failing
 */
class expAiTemplateOperators
{
    public function operatorList()
    {
        return array( 'expai_money', 'expai_tokens', 'expai_number', 'expai_json', 'expai_retention', 'expai_version' );
    }

    public function namedParameterPerOperator()
    {
        return true;
    }

    public function namedParameterList()
    {
        return array(
            'expai_money' => array( 'approx' => array( 'type' => 'boolean', 'required' => false, 'default' => false ) ),
            'expai_tokens' => array(),
            'expai_number' => array( 'decimals' => array( 'type' => 'integer', 'required' => false, 'default' => 0 ) ),
            'expai_json' => array(),
            'expai_retention' => array(),
            'expai_version' => array(),
        );
    }

    public function modify( $tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, &$operatorValue, $namedParameters )
    {
        switch ( $operatorName )
        {
            case 'expai_money':
                $operatorValue = expAiCost::money( $operatorValue, !empty( $namedParameters['approx'] ) );
                break;
            case 'expai_tokens':
                $operatorValue = expAiCost::tokens( $operatorValue );
                break;
            case 'expai_number':
                $operatorValue = expAiCost::number( $operatorValue, (int) $namedParameters['decimals'] );
                break;
            case 'expai_json':
                $operatorValue = self::json( $operatorValue );
                break;
            case 'expai_version':
                $operatorValue = self::assetVersion( $operatorValue );
                break;
            case 'expai_retention':
                $operatorValue = self::retention( $operatorValue );
                break;
        }
    }

    /**
     * A retention as the INI writes it (none, zdr, 30d, unknown): "unknown" is the only word and is translated, the
     * others are values the provider's terms use and stay as they are.
     *
     * @param mixed $value
     * @return string
     */
    public static function retention( $value )
    {
        $value = (string) $value;
        return strtolower( $value ) === 'unknown' ? ezpI18n::tr( 'extension/expai/models', 'unknown' ) : $value;
    }

    /**
     * The version of a file of this extension's admin design (path relative to design/admin/, e.g.
     * javascript/expai_presets.js): its modification time, else the version of the extension.
     *
     * @param mixed $path
     * @return string
     */
    public static function assetVersion( $path )
    {
        $path = ltrim( (string) $path, '/' );
        $root = dirname( __DIR__ );
        if ( $path !== '' && strpos( $path, '..' ) === false )
        {
            $time = @filemtime( $root . '/design/admin/' . $path );
            if ( $time )
                return (string) $time;
        }
        if ( preg_match( '#<version>([^<]+)</version>#', (string) @file_get_contents( $root . '/extension.xml' ), $m ) )
            return $m[1];
        return '0';
    }

    /**
     * JSON that is safe inside a <script> element, whatever the strings contain: a "</script>" or "<!--" in a
     * value cannot end or change the element, and a broken UTF-8 sequence does not turn the whole block into the
     * empty string (json_encode() would return false). An unencodable value gives "null" and a warning in the debug log, and the page script reads
     * it as "no configuration".
     *
     * @param mixed $value
     * @return string
     */
    public static function json( $value )
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;
        $json = json_encode( $value, $flags );
        if ( $json === false )
        {
            if ( class_exists( 'eZDebug', false ) )
                eZDebug::writeWarning( 'expai_json: the value cannot be encoded (' . json_last_error_msg() . '); the block is null', __METHOD__ );
            return 'null';
        }
        return $json;
    }
}
