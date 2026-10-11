<?php
/**
 * File containing the expAiPackageDatatypes class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The value of one attribute in a package, per datatype (doc/package-format.md):
 *
 *   ezstring, eztext, ezemail and [PackageSettings] StringDatatypes[]   { value: "text" }  (toString / fromString)
 *   ezinteger, ezfloat     { value: number | null }
 *   ezboolean              { value: true | false }
 *   ezdate, ezdatetime     { value: "2026-10-11T12:00:00+02:00" | null }
 *   ezkeyword              { value: [ "a", "b" ] }
 *   ezurl                  { url, text }
 *   ezxmltext              { format: ezxml | html, value }   ezxml: lossless, ids written as remote ids
 *                          (link url_id -> href, object_id -> object_remote_id, node_id -> node_remote_id; the
 *                          same for embed and embed-inline); html: the subset the assistant writes, converted by
 *                          the kernel's input parser on import
 *   ezimage                { file: "files/<sha256>.<ext>" | null, alt, filename }
 *   ezbinaryfile, ezmedia  { file, filename }
 *   ezobjectrelation       { remote_id | null }
 *   ezobjectrelationlist   { remote_ids: [ ... ] }
 *
 * Any other datatype is not exported (reported in the package's warnings) and refused on import: never skipped
 * silently.
 */
class expAiPackageDatatypes
{
    const BUILT_IN = array( 'ezstring', 'eztext', 'ezemail', 'ezinteger', 'ezfloat', 'ezboolean', 'ezdate', 'ezdatetime', 'ezkeyword', 'ezurl',
                            'ezxmltext', 'ezimage', 'ezbinaryfile', 'ezmedia', 'ezobjectrelation', 'ezobjectrelationlist' );

    /** @return string[] the datatypes a package can carry */
    public static function supported()
    {
        $extra = expAiConfig::instance()->arr( 'PackageSettings', 'StringDatatypes' );
        return array_values( array_unique( array_merge( self::BUILT_IN, $extra ) ) );
    }

    /** @return bool */
    public static function isSupported( $datatype )
    {
        return in_array( (string) $datatype, self::supported(), true );
    }

    // ---- export ----------------------------------------------------------------------------------------------------

    /**
     * The package value of an attribute.
     * @param expAiPackageExportContext $ctx files, remote ids
     * @return array|null null when the datatype is not supported
     */
    public static function export( eZContentObjectAttribute $attribute, $ctx )
    {
        $datatype = (string) $attribute->attribute( 'data_type_string' );
        $out = array( 'datatype' => $datatype );
        switch ( $datatype )
        {
            case 'ezinteger':
                $s = trim( (string) $attribute->toString() );
                $out['value'] = $s === '' || !$attribute->hasContent() ? null : (int) $s;
                return $out;
            case 'ezfloat':
                $s = trim( (string) $attribute->toString() );
                $out['value'] = $s === '' || !$attribute->hasContent() ? null : (float) $s;
                return $out;
            case 'ezboolean':
                $out['value'] = (bool) $attribute->attribute( 'data_int' );
                return $out;
            case 'ezdate':
            case 'ezdatetime':
                $t = (int) $attribute->attribute( 'data_int' );
                $out['value'] = $t > 0 ? date( 'c', $t ) : null;
                return $out;
            case 'ezkeyword':
                $out['value'] = array_values( array_filter( array_map( 'trim', explode( ',', (string) $attribute->toString() ) ), 'strlen' ) );
                return $out;
            case 'ezurl':
                $parts = explode( '|', (string) $attribute->toString(), 2 );
                $out['url'] = (string) $parts[0];
                $out['text'] = isset( $parts[1] ) ? (string) $parts[1] : '';
                return $out;
            case 'ezxmltext':
                $out['format'] = 'ezxml';
                $out['value'] = self::xmlToRemote( (string) $attribute->attribute( 'data_text' ), $ctx );
                return $out;
            case 'ezimage':
                return $out + self::exportImage( $attribute, $ctx );
            case 'ezbinaryfile':
            case 'ezmedia':
                return $out + self::exportBinary( $attribute, $ctx );
            case 'ezobjectrelation':
                $id = (int) $attribute->attribute( 'data_int' );
                $out['remote_id'] = $id ? $ctx->objectRemoteId( $id ) : null;
                return $out;
            case 'ezobjectrelationlist':
                $ids = array();
                foreach ( array_filter( explode( '-', (string) $attribute->toString() ) ) as $id )
                {
                    $rid = $ctx->objectRemoteId( (int) $id );
                    if ( $rid !== null )
                        $ids[] = $rid;
                }
                $out['remote_ids'] = $ids;
                return $out;
        }
        if ( in_array( $datatype, array( 'ezstring', 'eztext', 'ezemail' ), true ) || in_array( $datatype, expAiConfig::instance()->arr( 'PackageSettings', 'StringDatatypes' ), true ) )
        {
            $out['value'] = (string) $attribute->toString();
            return $out;
        }
        return null;
    }

    /** @return array file, alt, filename of an image */
    protected static function exportImage( eZContentObjectAttribute $attribute, $ctx )
    {
        if ( !$attribute->hasContent() )
            return array( 'file' => null, 'alt' => '', 'filename' => '' );
        $handler = $attribute->content();
        $original = $handler ? $handler->attribute( 'original' ) : null;
        $alt = $handler ? (string) $handler->attribute( 'alternative_text' ) : '';
        if ( !is_array( $original ) || empty( $original['url'] ) )
            return array( 'file' => null, 'alt' => $alt, 'filename' => '' );
        $name = isset( $original['original_filename'] ) && $original['original_filename'] !== '' ? (string) $original['original_filename'] : basename( $original['url'] );
        $file = $ctx->addFile( (string) $original['url'], isset( $original['mime_type'] ) ? (string) $original['mime_type'] : '', $name );
        return array( 'file' => $file, 'alt' => $alt, 'filename' => $name );
    }

    /** @return array file, filename of a binary file or media */
    protected static function exportBinary( eZContentObjectAttribute $attribute, $ctx )
    {
        $content = $attribute->hasContent() ? $attribute->content() : null;
        if ( !$content || !method_exists( $content, 'filePath' ) )
            return array( 'file' => null, 'filename' => '' );
        $name = (string) $content->attribute( 'original_filename' );
        return array( 'file' => $ctx->addFile( (string) $content->filePath(), (string) $content->attribute( 'mime_type' ), $name ), 'filename' => $name );
    }

    /**
     * ezxml with the installation's ids replaced by remote ids.
     * @return string
     */
    public static function xmlToRemote( $xml, $ctx )
    {
        if ( trim( (string) $xml ) === '' )
            return '';
        $dom = self::loadXml( $xml );
        if ( !$dom )
            return (string) $xml;
        foreach ( array( 'link', 'embed', 'embed-inline' ) as $tag )
            foreach ( iterator_to_array( $dom->getElementsByTagName( $tag ) ) as $element )
            {
                if ( $element->hasAttribute( 'url_id' ) )
                {
                    $url = eZURL::url( (int) $element->getAttribute( 'url_id' ) );
                    $element->removeAttribute( 'url_id' );
                    $element->setAttribute( 'href', (string) $url );
                }
                if ( $element->hasAttribute( 'object_id' ) )
                {
                    $rid = $ctx->objectRemoteId( (int) $element->getAttribute( 'object_id' ) );
                    $element->removeAttribute( 'object_id' );
                    if ( $rid !== null )
                        $element->setAttribute( 'object_remote_id', $rid );
                }
                if ( $element->hasAttribute( 'node_id' ) )
                {
                    $rid = $ctx->nodeRemoteId( (int) $element->getAttribute( 'node_id' ) );
                    $element->removeAttribute( 'node_id' );
                    if ( $rid !== null )
                        $element->setAttribute( 'node_remote_id', $rid );
                }
            }
        return $dom->saveXML();
    }

    /** @return DOMDocument|null ezxml read without network, entities or a DOCTYPE */
    public static function loadXml( $xml )
    {
        $xml = (string) $xml;
        if ( stripos( $xml, '<!DOCTYPE' ) !== false || stripos( $xml, '<!ENTITY' ) !== false )
            return null;
        $dom = new DOMDocument( '1.0', 'utf-8' );
        $dom->preserveWhiteSpace = false;
        $previous = libxml_use_internal_errors( true );
        $ok = $dom->loadXML( $xml, LIBXML_NONET );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        return $ok ? $dom : null;
    }

    /** @return string ezxml in a canonical form for comparisons */
    public static function canonicalXml( $xml )
    {
        $dom = self::loadXml( $xml );
        return $dom ? (string) $dom->documentElement->C14N() : trim( (string) $xml );
    }

    // ---- import ----------------------------------------------------------------------------------------------------

    /**
     * Writes a package value into an attribute of a draft (stored).
     * @param expAiPackageImportContext $ctx
     * @return true|string true or what was wrong
     */
    public static function apply( eZContentObject $object, eZContentObjectAttribute $attribute, array $value, $ctx )
    {
        $datatype = (string) $attribute->attribute( 'data_type_string' );
        if ( isset( $value['datatype'] ) && $value['datatype'] !== $datatype )
            return "the package names the datatype {$value['datatype']}, the class has $datatype";
        if ( !self::isSupported( $datatype ) )
            return "the datatype $datatype cannot be imported from a package";
        $v = array_key_exists( 'value', $value ) ? $value['value'] : null;
        switch ( $datatype )
        {
            case 'ezinteger':
                if ( $v !== null && filter_var( $v, FILTER_VALIDATE_INT ) === false )
                    return 'not an integer';
                $ok = $attribute->fromString( $v === null ? '' : (string) (int) $v );
                break;
            case 'ezfloat':
                if ( $v !== null && !is_numeric( $v ) )
                    return 'not a number';
                $ok = $attribute->fromString( $v === null ? '' : (string) $v );
                break;
            case 'ezboolean':
                $ok = $attribute->fromString( $v ? '1' : '0' );
                break;
            case 'ezdate':
            case 'ezdatetime':
                $t = $v === null || $v === '' ? 0 : ( is_numeric( $v ) ? (int) $v : strtotime( (string) $v ) );
                if ( $t === false )
                    return 'not a date';
                $attribute->setAttribute( 'data_int', (int) $t );
                $ok = true;
                break;
            case 'ezkeyword':
                $ok = $attribute->fromString( implode( ', ', array_map( 'strval', is_array( $v ) ? $v : array() ) ) );
                break;
            case 'ezurl':
                $url = isset( $value['url'] ) ? trim( (string) $value['url'] ) : '';
                if ( $url !== '' && !preg_match( '~^(https?://|mailto:|/)~i', $url ) )
                    return 'not an http(s), mailto or site URL';
                $ok = $attribute->fromString( $url . ( isset( $value['text'] ) && $value['text'] !== '' ? '|' . $value['text'] : '' ) );
                break;
            case 'ezxmltext':
                return self::applyXml( $object, $attribute, $value, $ctx );
            case 'ezimage':
            case 'ezbinaryfile':
            case 'ezmedia':
                return self::applyFile( $attribute, $datatype, $value, $ctx );
            case 'ezobjectrelation':
                $rid = isset( $value['remote_id'] ) ? $value['remote_id'] : null;
                $id = $rid ? $ctx->objectId( (string) $rid ) : 0;
                if ( $rid && !$id )
                    $ctx->warn( 'the related object ' . $rid . ' does not exist: the relation is left empty' );
                $attribute->setAttribute( 'data_int', $id ?: null );
                $ok = true;
                break;
            case 'ezobjectrelationlist':
                $ids = array();
                foreach ( isset( $value['remote_ids'] ) ? (array) $value['remote_ids'] : array() as $rid )
                {
                    $id = $ctx->objectId( (string) $rid );
                    if ( $id )
                        $ids[] = $id;
                    else
                        $ctx->warn( 'the related object ' . $rid . ' does not exist: it is left out of the list' );
                }
                $ok = $attribute->fromString( implode( '-', $ids ) );
                break;
            default:
                $ok = $attribute->fromString( $v === null ? '' : (string) $v );
        }
        if ( $ok === false )
            return 'the value was refused by the datatype';
        $attribute->store();
        return true;
    }

    /** @return true|string */
    protected static function applyXml( eZContentObject $object, eZContentObjectAttribute $attribute, array $value, $ctx )
    {
        $format = isset( $value['format'] ) ? (string) $value['format'] : 'ezxml';
        $text = isset( $value['value'] ) ? (string) $value['value'] : '';
        $urlIDs = array();
        if ( $text === '' )
            $xml = '';
        elseif ( $format === 'html' )
        {
            $writer = new expAiContentWriter();
            $messages = array();
            $xml = $writer->richTextToXml( $object, $text, $urlIDs, $messages );
            if ( $xml === false )
                return 'the rich text could not be read: ' . implode( ' ', $messages );
        }
        elseif ( $format === 'ezxml' )
        {
            $xml = self::xmlFromRemote( $text, $ctx, $urlIDs );
            if ( $xml === null )
                return 'the rich text is not valid ezxml';
        }
        else
            return "unknown rich text format $format";
        $attribute->setAttribute( 'data_text', $xml );
        $attribute->store();
        if ( $urlIDs )
            eZSimplifiedXMLInput::updateUrlObjectLinks( $attribute, $urlIDs );
        return true;
    }

    /**
     * ezxml of a package with remote ids replaced by this installation's ids. Links and embeds to content that does
     * not exist are removed (a link keeps its text) and reported.
     * @return string|null
     */
    public static function xmlFromRemote( $xml, $ctx, array &$urlIDs = array() )
    {
        $dom = self::loadXml( $xml );
        if ( !$dom || !$dom->documentElement || $dom->documentElement->nodeName !== 'section' )
            return null;
        // nothing but the ezxml vocabulary: no script, no foreign markup
        foreach ( iterator_to_array( $dom->getElementsByTagName( '*' ) ) as $element )
            if ( in_array( strtolower( $element->nodeName ), array( 'script', 'style', 'iframe', 'object' ), true ) )
                return null;
        foreach ( array( 'link', 'embed', 'embed-inline' ) as $tag )
            foreach ( iterator_to_array( $dom->getElementsByTagName( $tag ) ) as $element )
            {
                $missing = false;
                if ( $element->hasAttribute( 'href' ) )
                {
                    $href = trim( $element->getAttribute( 'href' ) );
                    $element->removeAttribute( 'href' );
                    if ( preg_match( '~^\s*(javascript|data|vbscript):~i', $href ) )
                        $missing = true;
                    else
                    {
                        $id = $ctx->urlId( $href );
                        $element->setAttribute( 'url_id', (string) $id );
                        $urlIDs[] = (int) $id;
                    }
                }
                if ( $element->hasAttribute( 'object_remote_id' ) )
                {
                    $rid = $element->getAttribute( 'object_remote_id' );
                    $element->removeAttribute( 'object_remote_id' );
                    $id = $ctx->objectId( $rid );
                    if ( $id )
                        $element->setAttribute( 'object_id', (string) $id );
                    else
                    {
                        $missing = true;
                        $ctx->warn( "the $tag target object $rid does not exist: removed" );
                    }
                }
                if ( $element->hasAttribute( 'node_remote_id' ) )
                {
                    $rid = $element->getAttribute( 'node_remote_id' );
                    $element->removeAttribute( 'node_remote_id' );
                    $id = $ctx->nodeId( $rid );
                    if ( $id )
                        $element->setAttribute( 'node_id', (string) $id );
                    else
                    {
                        $missing = true;
                        $ctx->warn( "the $tag target node $rid does not exist: removed" );
                    }
                }
                if ( $missing )
                {
                    if ( $tag === 'link' )
                        while ( $element->firstChild )
                            $element->parentNode->insertBefore( $element->firstChild, $element );
                    $element->parentNode->removeChild( $element );
                }
                foreach ( iterator_to_array( $element->attributes ?: array() ) as $attr )
                    if ( stripos( $attr->nodeName, 'on' ) === 0 )
                        $element->removeAttribute( $attr->nodeName );
            }
        return $dom->saveXML();
    }

    /** @return true|string */
    protected static function applyFile( eZContentObjectAttribute $attribute, $datatype, array $value, $ctx )
    {
        $rel = isset( $value['file'] ) ? $value['file'] : null;
        if ( $rel === null || $rel === '' )
        {
            if ( $datatype === 'ezimage' && isset( $value['alt'] ) && $attribute->hasContent() )
            {
                $handler = $attribute->content();
                $handler->setAttribute( 'alternative_text', (string) $value['alt'] );
                $handler->store( $attribute );
            }
            return true;
        }
        $source = $ctx->filePath( (string) $rel );
        if ( !$source )
            return 'the file ' . $rel . ' is not in the package';
        $name = isset( $value['filename'] ) && $value['filename'] !== '' ? (string) $value['filename'] : basename( (string) $rel );
        $name = preg_replace( '/[^A-Za-z0-9._-]+/', '-', basename( $name ) );
        $name = trim( $name, '.-' ) !== '' ? $name : basename( (string) $rel );
        $copy = $ctx->tempCopy( $source, $name );
        if ( $datatype === 'ezimage' )
            $ok = $attribute->fromString( $copy . ( isset( $value['alt'] ) ? '|' . str_replace( '|', ' ', (string) $value['alt'] ) : '' ) );
        else
            $ok = $attribute->fromString( $copy );
        if ( $ok === false )
            return 'the file was refused by the datatype';
        $attribute->store();
        return true;
    }

    // ---- comparison ------------------------------------------------------------------------------------------------

    /**
     * A value in the form two values are compared in: file paths as their hashes, rich text canonical, html rich
     * text converted.
     * @return string
     */
    public static function comparable( array $value, $files = array() )
    {
        unset( $value['filename'] );
        if ( isset( $value['file'] ) && is_string( $value['file'] ) && preg_match( '~^files/([0-9a-f]{64})\.~', $value['file'], $m ) )
            $value['file'] = $m[1];
        if ( isset( $value['datatype'] ) && $value['datatype'] === 'ezxmltext' && isset( $value['value'] ) && (string) $value['value'] !== '' )
            $value['value'] = self::canonicalXml( (string) $value['value'] );
        if ( isset( $value['datatype'] ) && in_array( $value['datatype'], array( 'ezdate', 'ezdatetime' ), true ) && isset( $value['value'] ) && $value['value'] !== null )
            $value['value'] = (int) ( is_numeric( $value['value'] ) ? $value['value'] : strtotime( (string) $value['value'] ) );
        if ( isset( $value['datatype'] ) && $value['datatype'] === 'ezfloat' && isset( $value['value'] ) && $value['value'] !== null )
            $value['value'] = (float) $value['value'];
        ksort( $value );
        return json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }
}
