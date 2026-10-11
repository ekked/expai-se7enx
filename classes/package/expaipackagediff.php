<?php
/**
 * File containing the expAiPackageDiff class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * Compares a package with the published content of this installation, attribute by attribute (the dry run of an
 * import, and the check after one). Nothing is written.
 *
 * Values are compared in the package's own form (expAiPackageDatatypes::comparable()): the live object is exported
 * the way the exporter would (files hashed, not copied) and both sides are normalised (rich text canonical XML, files
 * as their SHA-256, dates as timestamps). Rich text given as html in the package is compared as text (the conversion
 * to ezxml happens on import). With a remap, the package's remote ids are mapped first, so a package imported as
 * copies compares equal to its copies.
 */
class expAiPackageDiff
{
    /** @var expAiPackage */
    protected $package;

    /** @var expAiPackageImporter (for the remote id mapping) */
    protected $importer;

    /** @var array */
    protected $options;

    public function __construct( expAiPackage $package, array $options = array() )
    {
        $this->package = $package;
        $this->options = $options + array( 'remap' => '', 'languages' => array(), 'max_value' => 300 );
        $this->importer = new expAiPackageImporter( $package, array( 'remap' => $this->options['remap'], 'dry_run' => true ) );
    }

    /**
     * @return array objects (remote_id, target, status: new | same | different | error, languages: language =>
     *               array( status: new | same | different, differences: list of attribute, package, live )),
     *               totals (objects, new, same, different, error, differences)
     */
    public function diff()
    {
        $out = array();
        $totals = array( 'objects' => 0, 'new' => 0, 'same' => 0, 'different' => 0, 'error' => 0, 'differences' => 0 );
        foreach ( $this->package->objects() as $item )
        {
            $row = $this->object( $item );
            $out[] = $row;
            $totals['objects']++;
            $totals[$row['status']]++;
            foreach ( $row['languages'] as $language )
                $totals['differences'] += count( $language['differences'] );
        }
        return array( 'objects' => $out, 'totals' => $totals );
    }

    /** @return array the comparison of one object */
    public function object( array $item )
    {
        $rid = isset( $item['remote_id'] ) ? (string) $item['remote_id'] : '';
        $target = $this->importer->target( $rid );
        $row = array( 'remote_id' => $rid, 'target' => $target, 'class' => isset( $item['class_identifier'] ) ? (string) $item['class_identifier'] : '',
                      'status' => 'same', 'object_id' => 0, 'languages' => array(), 'message' => '' );
        $translations = isset( $item['translations'] ) && is_array( $item['translations'] ) ? $item['translations'] : array();
        if ( $this->options['languages'] )
            $translations = array_intersect_key( $translations, array_flip( (array) $this->options['languages'] ) );
        $object = eZContentObject::fetchByRemoteID( $target );
        if ( !$object || (int) $object->attribute( 'status' ) !== eZContentObject::STATUS_PUBLISHED )
        {
            $row['status'] = 'new';
            $row['message'] = $object ? 'only a draft exists' : 'not in this installation';
            foreach ( $translations as $language => $t )
                $row['languages'][$language] = array( 'status' => 'new', 'differences' => array() );
            return $row;
        }
        $row['object_id'] = (int) $object->attribute( 'id' );
        if ( (string) $object->attribute( 'class_identifier' ) !== $row['class'] )
        {
            $row['status'] = 'error';
            $row['message'] = 'the object here is of the class ' . $object->attribute( 'class_identifier' );
            return $row;
        }
        $exporter = new expAiPackageExporter( array( 'hash_only' => true, 'languages' => array_keys( $translations ) ) );
        $live = $exporter->object( $object );
        foreach ( $translations as $language => $translation )
        {
            $differences = array();
            if ( !isset( $live['translations'][$language] ) )
            {
                $row['languages'][$language] = array( 'status' => 'new', 'differences' => array() );
                $row['status'] = 'different';
                continue;
            }
            $liveAttributes = $live['translations'][$language]['attributes'];
            foreach ( isset( $translation['attributes'] ) ? (array) $translation['attributes'] : array() as $identifier => $value )
            {
                $value = $this->mapped( (array) $value );
                $have = isset( $liveAttributes[$identifier] ) ? (array) $liveAttributes[$identifier] : null;
                if ( $have === null )
                {
                    // not translatable: the main language's value applies
                    if ( $language !== (string) $object->attribute( 'initial_language_code' ) )
                        continue;
                    $differences[] = array( 'attribute' => $identifier, 'package' => self::show( $value ), 'live' => '(none)' );
                    continue;
                }
                if ( isset( $value['datatype'], $value['format'] ) && $value['datatype'] === 'ezxmltext' && $value['format'] === 'html' )
                {
                    $a = self::text( isset( $value['value'] ) ? (string) $value['value'] : '' );
                    $b = self::text( isset( $have['value'] ) ? (string) $have['value'] : '' );
                    if ( $a !== $b )
                        $differences[] = array( 'attribute' => $identifier, 'package' => expAiText::cut( $a, $this->options['max_value'] ), 'live' => expAiText::cut( $b, $this->options['max_value'] ) );
                    continue;
                }
                if ( expAiPackageDatatypes::comparable( $value ) !== expAiPackageDatatypes::comparable( $have ) )
                    $differences[] = array( 'attribute' => $identifier, 'package' => self::show( $value ), 'live' => self::show( $have ) );
            }
            $row['languages'][$language] = array( 'status' => $differences ? 'different' : 'same', 'differences' => $differences );
            if ( $differences )
                $row['status'] = 'different';
        }
        return $row;
    }

    /** @return array a package value with the remote ids of the package's objects mapped (remap) */
    protected function mapped( array $value )
    {
        if ( $this->options['remap'] === '' )
            return $value;
        if ( isset( $value['remote_id'] ) && is_string( $value['remote_id'] ) )
            $value['remote_id'] = $this->importer->target( $value['remote_id'] );
        if ( isset( $value['remote_ids'] ) && is_array( $value['remote_ids'] ) )
            $value['remote_ids'] = array_map( array( $this->importer, 'target' ), $value['remote_ids'] );
        if ( isset( $value['datatype'], $value['value'] ) && $value['datatype'] === 'ezxmltext' && ( !isset( $value['format'] ) || $value['format'] === 'ezxml' ) && $value['value'] !== '' )
        {
            $importer = $this->importer;
            $value['value'] = preg_replace_callback( '/\bobject_remote_id="([^"]+)"/', function ( $m ) use ( $importer )
            {
                return 'object_remote_id="' . htmlspecialchars( $importer->target( html_entity_decode( $m[1] ) ), ENT_QUOTES ) . '"';
            }, (string) $value['value'] );
        }
        return $value;
    }

    /** @return string rich text as normalised text */
    protected static function text( $markup )
    {
        $text = html_entity_decode( strip_tags( preg_replace( '~<(/?)(p|h\d|li|td|th|br|header|paragraph)\b[^>]*>~i', ' ', (string) $markup ) ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
        return trim( preg_replace( '/\s+/u', ' ', $text ) );
    }

    /** @return string a value for the report */
    protected static function show( array $value )
    {
        unset( $value['datatype'] );
        $json = json_encode( count( $value ) === 1 ? reset( $value ) : $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return expAiText::cut( (string) $json, 300 );
    }
}
