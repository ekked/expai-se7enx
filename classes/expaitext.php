<?php
/**
 * File containing the expAiText class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * Text helpers: shortening, the HTML subset the assistant writes rich text in, and a plain HTML view of stored
 * ezxmltext for the assistant to read.
 */
class expAiText
{
    /** Tags the assistant may use in rich text (ezxmltext), with the attributes kept */
    public static $allowedTags = array( 'p' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(), 'strong' => array(),
                                        'b' => array(), 'em' => array(), 'i' => array(), 'ul' => array(), 'ol' => array(),
                                        'li' => array(), 'a' => array( 'href' ), 'br' => array() );

    /** @return string at most $max characters (multibyte safe), with an ellipsis when cut */
    public static function cut( $text, $max )
    {
        $text = (string) $text;
        if ( $max <= 0 || mb_strlen( $text, 'UTF-8' ) <= $max )
            return $text;
        return rtrim( mb_substr( $text, 0, max( 1, $max - 1 ), 'UTF-8' ) ) . "\u{2026}";
    }

    /** @return string one line, no control characters */
    public static function oneLine( $text )
    {
        return trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
    }

    /**
     * The HTML subset of the assistant, cleaned: only the allowed tags, only http(s) and mailto links, no
     * attributes otherwise, no comments, scripts or styles.
     * @return string
     */
    public static function sanitizeHtml( $html )
    {
        $html = (string) $html;
        if ( trim( $html ) === '' )
            return '';
        // Markdown habits of the model: **bold**, *em*, lines starting with "- "
        if ( !preg_match( '/<\s*(p|h[1-6]|ul|ol|li|strong|em|b|i|a|br)\b/i', $html ) )
            $html = self::textToHtml( $html );
        $document = new DOMDocument( '1.0', 'UTF-8' );
        $previous = libxml_use_internal_errors( true );
        $document->loadHTML( '<?xml encoding="UTF-8"?><div id="exp-ai-root">' . $html . '</div>', LIBXML_NONET );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        $root = $document->getElementById( 'exp-ai-root' );
        if ( !$root )
            return '';
        $out = '';
        foreach ( iterator_to_array( $root->childNodes ) as $child )
            $out .= self::cleanNode( $child );
        // stray text at the top level goes into paragraphs
        return trim( $out );
    }

    protected static function cleanNode( DOMNode $node )
    {
        if ( $node instanceof DOMText )
            return htmlspecialchars( $node->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        if ( !$node instanceof DOMElement )
            return '';
        $name = strtolower( $node->nodeName );
        if ( in_array( $name, array( 'script', 'style', 'iframe', 'object', 'embed', 'img', 'svg', 'math', 'form', 'input', 'textarea', 'select', 'button' ), true ) )
            return '';
        if ( $name === 'h1' )
            $name = 'h2';
        elseif ( in_array( $name, array( 'h5', 'h6' ), true ) )
            $name = 'h4';
        $inner = '';
        foreach ( iterator_to_array( $node->childNodes ) as $child )
            $inner .= self::cleanNode( $child );
        if ( !isset( self::$allowedTags[$name] ) )
            return $inner;
        if ( $name === 'br' )
            return '<br />';
        if ( $name === 'b' )
            $name = 'strong';
        if ( $name === 'i' )
            $name = 'em';
        if ( $name === 'a' )
        {
            $href = trim( (string) $node->getAttribute( 'href' ) );
            if ( !preg_match( '~^(https?://[^\s"<>]+|mailto:[^\s"<>]+)$~i', $href ) )
                return $inner;
            return '<a href="' . htmlspecialchars( $href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '">' . $inner . '</a>';
        }
        if ( trim( strip_tags( $inner ) ) === '' && $name !== 'li' )
            return '';
        return "<$name>$inner</$name>";
    }

    /** @return string plain text (with simple Markdown) as HTML paragraphs and lists */
    public static function textToHtml( $text )
    {
        $esc = function ( $s )
        {
            $s = htmlspecialchars( $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
            $s = preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s );
            return preg_replace( '/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '<em>$1</em>', $s );
        };
        $html = '';
        foreach ( preg_split( "/\n\s*\n/", str_replace( "\r", '', trim( (string) $text ) ) ) as $block )
        {
            $lines = explode( "\n", trim( $block ) );
            if ( preg_match( '/^#{1,3}\s+(.*)$/', $lines[0], $m ) && count( $lines ) === 1 )
                $html .= '<h2>' . $esc( $m[1] ) . '</h2>';
            elseif ( count( array_filter( $lines, function ( $l ) { return preg_match( '/^\s*[-*]\s+/', $l ); } ) ) === count( $lines ) )
                $html .= '<ul>' . implode( '', array_map( function ( $l ) use ( $esc ) { return '<li>' . $esc( preg_replace( '/^\s*[-*]\s+/', '', $l ) ) . '</li>'; }, $lines ) ) . '</ul>';
            else
                $html .= '<p>' . implode( '<br />', array_map( $esc, $lines ) ) . '</p>';
        }
        return $html;
    }

    /** @return string HTML as plain text (paragraphs separated by blank lines) */
    public static function htmlToText( $html )
    {
        $html = preg_replace( '~<\s*br\s*/?\s*>~i', "\n", (string) $html );
        $html = preg_replace( '~</\s*(p|h[1-6]|li|ul|ol|div)\s*>~i', "\n\n", $html );
        $text = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        return trim( preg_replace( "/\n{3,}/", "\n\n", preg_replace( '/[ \t]+/', ' ', $text ) ) );
    }

    /**
     * Stored ezxmltext as a small HTML (paragraphs, headings, emphasis, lists, links) for the assistant to read.
     * @return string
     */
    public static function xmlTextToHtml( $xml )
    {
        $xml = (string) $xml;
        if ( trim( $xml ) === '' )
            return '';
        $document = new DOMDocument( '1.0', 'UTF-8' );
        $previous = libxml_use_internal_errors( true );
        $ok = $document->loadXML( $xml, LIBXML_NONET );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( !$ok || !$document->documentElement )
            return '';
        return trim( self::xmlNodeToHtml( $document->documentElement ) );
    }

    protected static function xmlNodeToHtml( DOMNode $node )
    {
        if ( $node instanceof DOMText )
            return htmlspecialchars( $node->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        if ( !$node instanceof DOMElement )
            return '';
        $inner = '';
        foreach ( $node->childNodes as $child )
            $inner .= self::xmlNodeToHtml( $child );
        switch ( $node->localName )
        {
            case 'paragraph': return "<p>$inner</p>";
            case 'header': return '<h' . min( 4, max( 2, (int) $node->getAttribute( 'level' ) + 1 ) ) . ">$inner</h" . min( 4, max( 2, (int) $node->getAttribute( 'level' ) + 1 ) ) . '>';
            case 'strong': return "<strong>$inner</strong>";
            case 'emphasize': return "<em>$inner</em>";
            case 'ul': case 'ol': case 'li': return '<' . $node->localName . ">$inner</" . $node->localName . '>';
            case 'link':
                $url = '';
                if ( $node->hasAttribute( 'url_id' ) )
                {
                    $urlObject = eZURL::fetch( (int) $node->getAttribute( 'url_id' ) );
                    $url = $urlObject ? $urlObject->attribute( 'url' ) : '';
                }
                return $url !== '' ? '<a href="' . htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) . "\">$inner</a>" : $inner;
            case 'embed': case 'embed-inline': return '[embedded object ' . htmlspecialchars( $node->getAttribute( 'object_id' ) ?: $node->getAttribute( 'node_id' ), ENT_QUOTES, 'UTF-8' ) . ']';
            case 'line': return $inner . '<br />';
            default: return $inner;
        }
    }
}
