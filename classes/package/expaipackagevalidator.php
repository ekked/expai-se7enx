<?php
/**
 * File containing the expAiPackageValidator class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * Checks a package against the format (share/package/schema/v1.json) and against this installation: the manifest
 * checksum and every file's sha256 and size, file types (finfo, [PackageSettings] AllowedMimeTypes[]; never SVG),
 * classes, attributes and their datatypes, languages, rich text (ezxml parsed without network or entities; html
 * through the kernel's input parser on import), relations and parents that can be resolved, and the limits
 * (MaxObjects, MaxFiles, MaxFileBytes).
 *
 * Errors make a package unusable; warnings are imported anyway (a relation to an object that does not exist is left
 * out, a parent that cannot be found must be named with --parent ...).
 */
class expAiPackageValidator
{
    /** @var expAiPackage */
    protected $package;

    /** @var array */
    protected $errors = array();

    /** @var array */
    protected $warnings = array();

    public function __construct( expAiPackage $package )
    {
        $this->package = $package;
    }

    /** @return array valid (bool), errors, warnings, objects, files, checksum (stated, computed) */
    public function validate()
    {
        $this->errors = array();
        $this->warnings = array();
        $data = $this->package->data();
        if ( !isset( $data['format'] ) || $data['format'] !== expAiPackage::FORMAT )
            $this->error( '', 'format must be "expai-package"' );
        if ( !isset( $data['format_version'] ) || !is_string( $data['format_version'] ) || !preg_match( '/^1\.\d+$/', $data['format_version'] ) )
            $this->error( '', 'format_version must be 1.x' );
        if ( !isset( $data['package'] ) || !is_array( $data['package'] ) )
            $this->error( 'package', 'is required' );
        else
        {
            if ( !isset( $data['package']['uuid'] ) || !preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) $data['package']['uuid'] ) )
                $this->error( 'package.uuid', 'must be a lower-case UUID' );
            if ( !isset( $data['package']['name'] ) || !is_string( $data['package']['name'] ) || $data['package']['name'] === '' )
                $this->error( 'package.name', 'is required' );
        }
        $allowed = array( 'format', 'format_version', 'package', 'provenance', 'objects', 'files', 'warnings', 'checksum' );
        foreach ( array_diff( array_keys( $data ), $allowed ) as $unknown )
            $this->error( (string) $unknown, 'is not a member of the format' );

        // checksum
        $stated = $this->package->statedChecksum();
        $computed = $this->package->computeChecksum();
        if ( $stated === '' )
        {
            if ( expAiConfig::instance()->flag( 'PackageSettings', 'RequireChecksum', true ) )
                $this->error( 'checksum', 'is missing (./console ext:expai:package checksum <package> --write adds it)' );
            else
                $this->warning( 'checksum', 'is missing' );
        }
        elseif ( !hash_equals( $computed, $stated ) )
            $this->error( 'checksum', 'does not match the content (the package was changed after the checksum was written)' );

        $files = $this->files( isset( $data['files'] ) && is_array( $data['files'] ) ? $data['files'] : array() );
        $objects = isset( $data['objects'] ) && is_array( $data['objects'] ) ? $data['objects'] : array();
        if ( !$objects )
            $this->error( 'objects', 'must contain at least one object' );
        $max = expAiPackage::limit( 'MaxObjects', 2000 );
        if ( count( $objects ) > $max )
            $this->error( 'objects', 'more than ' . $max . ' objects ([PackageSettings] MaxObjects)' );
        $seen = array();
        $remoteIDs = array();
        foreach ( $objects as $i => $object )
            if ( is_array( $object ) && isset( $object['remote_id'] ) && is_string( $object['remote_id'] ) )
                $remoteIDs[$object['remote_id']] = true;
        $used = array();
        foreach ( array_values( $objects ) as $i => $object )
            $this->object( "objects[$i]", $object, $seen, $remoteIDs, $files, $used );
        foreach ( array_diff( array_keys( $files ), array_keys( $used ) ) as $unused )
            $this->warning( 'files', $unused . ' is not used by any attribute' );
        return array( 'valid' => !$this->errors, 'errors' => $this->errors, 'warnings' => $this->warnings, 'objects' => count( $objects ),
                      'files' => count( $files ), 'checksum' => array( 'stated' => $stated, 'computed' => $computed ) );
    }

    protected function error( $path, $text )
    {
        $this->errors[] = ( $path !== '' ? $path . ': ' : '' ) . $text;
    }

    protected function warning( $path, $text )
    {
        $this->warnings[] = ( $path !== '' ? $path . ': ' : '' ) . $text;
    }

    /** @return array path => entry of the valid files */
    protected function files( array $list )
    {
        $config = expAiConfig::instance();
        $mimes = $config->arr( 'PackageSettings', 'AllowedMimeTypes' );
        $maxFile = expAiPackage::limit( 'MaxFileBytes', 52428800 );
        if ( count( $list ) > expAiPackage::limit( 'MaxFiles', 5000 ) )
            $this->error( 'files', 'more than ' . expAiPackage::limit( 'MaxFiles', 5000 ) . ' files' );
        $finfo = function_exists( 'finfo_open' ) ? finfo_open( FILEINFO_MIME_TYPE ) : null;
        $out = array();
        foreach ( $list as $i => $file )
        {
            $path = "files[$i]";
            if ( !is_array( $file ) || !isset( $file['path'] ) || !expAiPackage::safeFileName( $file['path'] ) )
            {
                $this->error( $path, 'path must be files/<sha256>.<ext>' );
                continue;
            }
            $rel = $file['path'];
            $sha = isset( $file['sha256'] ) ? strtolower( (string) $file['sha256'] ) : '';
            if ( substr( $rel, 6, 64 ) !== $sha )
                $this->error( $path, 'sha256 must be the name of the file' );
            $full = $this->package->filePath( $rel );
            if ( !$full )
            {
                $this->error( $path, $rel . ' is not in the package' );
                continue;
            }
            if ( filesize( $full ) > $maxFile )
                $this->error( $path, $rel . ' is larger than ' . $maxFile . ' bytes' );
            if ( !isset( $file['size'] ) || (int) $file['size'] !== (int) filesize( $full ) )
                $this->error( $path, 'size does not match the file' );
            if ( !hash_equals( hash_file( 'sha256', $full ), $sha ) )
                $this->error( $path, 'sha256 does not match the file' );
            $mime = $finfo ? (string) finfo_file( $finfo, $full ) : ( isset( $file['mime'] ) ? (string) $file['mime'] : '' );
            if ( stripos( $mime, 'svg' ) !== false || preg_match( '/\.svgz?$/', $rel ) )
                $this->error( $path, 'SVG files are not allowed (they can carry script)' );
            elseif ( $mimes && !in_array( $mime, $mimes, true ) )
                $this->error( $path, 'the file type ' . $mime . ' is not allowed ([PackageSettings] AllowedMimeTypes[])' );
            $out[$rel] = $file + array( 'detected_mime' => $mime );
        }
        return $out;
    }

    /** Checks one object. */
    protected function object( $path, $object, array &$seen, array $remoteIDs, array $files, array &$used )
    {
        if ( !is_array( $object ) )
        {
            $this->error( $path, 'must be an object' );
            return;
        }
        foreach ( array_diff( array_keys( $object ), array( 'remote_id', 'class_identifier', 'section_identifier', 'always_available', 'initial_language',
                                                            'locations', 'translations', 'expected' ) ) as $unknown )
            $this->error( "$path.$unknown", 'is not a member of an object' );
        $rid = isset( $object['remote_id'] ) ? $object['remote_id'] : null;
        if ( !is_string( $rid ) || !preg_match( '/^[A-Za-z0-9_.:-]{1,100}$/', $rid ) )
            $this->error( "$path.remote_id", 'must be 1 to 100 characters of A-Z a-z 0-9 _ . : -' );
        elseif ( isset( $seen[$rid] ) )
            $this->error( "$path.remote_id", $rid . ' appears twice' );
        else
            $seen[$rid] = true;
        $classID = isset( $object['class_identifier'] ) ? (string) $object['class_identifier'] : '';
        $class = $classID !== '' ? eZContentClass::fetchByIdentifier( $classID ) : null;
        if ( !$class )
        {
            $this->error( "$path.class_identifier", 'unknown class "' . $classID . '"' );
            return;
        }
        $classAttributes = array();
        foreach ( $class->fetchAttributes() as $ca )
            $classAttributes[(string) $ca->attribute( 'identifier' )] = $ca;
        if ( is_string( $rid ) && ( $existing = eZContentObject::fetchByRemoteID( $rid ) ) && $existing->attribute( 'class_identifier' ) !== $classID )
            $this->warning( "$path.remote_id", $rid . ' belongs to an object of the class ' . $existing->attribute( 'class_identifier' ) . ' here: it can only be imported with a remap' );
        if ( isset( $object['section_identifier'] ) && $object['section_identifier'] !== '' && !eZSection::fetchByIdentifier( (string) $object['section_identifier'], false ) )
            $this->warning( "$path.section_identifier", 'unknown section "' . $object['section_identifier'] . '": the parent\'s section is used' );
        $translations = isset( $object['translations'] ) && is_array( $object['translations'] ) ? $object['translations'] : array();
        $initial = isset( $object['initial_language'] ) ? (string) $object['initial_language'] : '';
        if ( !$translations )
            $this->error( "$path.translations", 'must contain at least one translation' );
        elseif ( !isset( $translations[$initial] ) )
            $this->error( "$path.initial_language", 'must be one of the translations' );
        $locales = eZContentLanguage::fetchLocaleList();
        foreach ( $translations as $language => $translation )
        {
            $tpath = "$path.translations.$language";
            if ( !in_array( (string) $language, $locales, true ) )
                $this->error( $tpath, 'the language is not used in this installation (languages: ' . implode( ', ', $locales ) . ')' );
            if ( !is_array( $translation ) || !isset( $translation['attributes'] ) || !is_array( $translation['attributes'] ) )
            {
                $this->error( $tpath, 'attributes are required' );
                continue;
            }
            foreach ( $translation['attributes'] as $identifier => $value )
                $this->attribute( "$tpath.attributes.$identifier", (string) $identifier, $value, $classAttributes, $files, $remoteIDs, $used, $language === $initial );
        }
        $locations = isset( $object['locations'] ) && is_array( $object['locations'] ) ? $object['locations'] : array();
        if ( !$locations )
            $this->error( "$path.locations", 'must contain at least one location' );
        $mains = 0;
        foreach ( $locations as $j => $location )
        {
            $parent = is_array( $location ) && isset( $location['parent'] ) && is_array( $location['parent'] ) ? $location['parent'] : array();
            $mains += !empty( $location['main'] ) ? 1 : 0;
            if ( !$parent )
            {
                $this->error( "$path.locations[$j].parent", 'is required (object_remote_id, node_remote_id or path)' );
                continue;
            }
            if ( isset( $parent['object_remote_id'] ) && isset( $remoteIDs[(string) $parent['object_remote_id']] ) )
                continue;
            $found = ( isset( $parent['object_remote_id'] ) && ( $o = eZContentObject::fetchByRemoteID( (string) $parent['object_remote_id'] ) ) && $o->mainNode() )
                  || ( !empty( $parent['node_remote_id'] ) && eZContentObjectTreeNode::fetchByRemoteID( (string) $parent['node_remote_id'] ) )
                  || ( !empty( $parent['path'] ) && expAiPackageImporter::resolveNode( (string) $parent['path'] ) );
            if ( !$found )
                $this->warning( "$path.locations[$j].parent", 'cannot be found here: name a parent when importing (--parent)' );
        }
        if ( $mains > 1 )
            $this->error( "$path.locations", 'more than one main location' );
        if ( isset( $object['expected'] ) && !is_array( $object['expected'] ) )
            $this->error( "$path.expected", 'must be an object' );
    }

    /** Checks one attribute value. */
    protected function attribute( $path, $identifier, $value, array $classAttributes, array $files, array $remoteIDs, array &$used, $initial )
    {
        if ( !isset( $classAttributes[$identifier] ) )
        {
            $this->error( $path, 'the class has no attribute ' . $identifier );
            return;
        }
        $datatype = (string) $classAttributes[$identifier]->attribute( 'data_type_string' );
        if ( !is_array( $value ) || !isset( $value['datatype'] ) )
        {
            $this->error( $path, 'must be an object with a datatype' );
            return;
        }
        if ( $value['datatype'] !== $datatype )
        {
            $this->error( $path, 'the datatype is ' . $datatype . ' here, not ' . $value['datatype'] );
            return;
        }
        if ( !expAiPackageDatatypes::isSupported( $datatype ) )
        {
            $this->error( $path, 'the datatype ' . $datatype . ' cannot be imported from a package' );
            return;
        }
        if ( !$initial && !$classAttributes[$identifier]->attribute( 'can_translate' ) )
            $this->warning( $path, 'is not translatable: the value of the main language is kept' );
        $v = array_key_exists( 'value', $value ) ? $value['value'] : null;
        switch ( $datatype )
        {
            case 'ezinteger':
                if ( $v !== null && !is_int( $v ) )
                    $this->error( $path, 'value must be an integer or null' );
                break;
            case 'ezfloat':
                if ( $v !== null && !is_int( $v ) && !is_float( $v ) )
                    $this->error( $path, 'value must be a number or null' );
                break;
            case 'ezboolean':
                if ( !is_bool( $v ) )
                    $this->error( $path, 'value must be true or false' );
                break;
            case 'ezdate':
            case 'ezdatetime':
                if ( $v !== null && ( !is_string( $v ) || strtotime( $v ) === false ) )
                    $this->error( $path, 'value must be an ISO 8601 date or null' );
                break;
            case 'ezkeyword':
                if ( !is_array( $v ) )
                    $this->error( $path, 'value must be a list of keywords' );
                break;
            case 'ezurl':
                if ( !isset( $value['url'] ) || !is_string( $value['url'] ) )
                    $this->error( $path, 'url is required' );
                elseif ( $value['url'] !== '' && !preg_match( '~^(https?://|mailto:|/)~i', $value['url'] ) )
                    $this->error( $path, 'url must be http(s), mailto or a site path' );
                break;
            case 'ezxmltext':
                $format = isset( $value['format'] ) ? $value['format'] : '';
                if ( !is_string( $v ) )
                    $this->error( $path, 'value must be a string' );
                elseif ( $format === 'ezxml' )
                {
                    if ( $v !== '' && expAiPackageDatatypes::xmlFromRemote( $v, new expAiPackageNullContext() ) === null )
                        $this->error( $path, 'value is not valid ezxml (a <section> document without DOCTYPE, entities or script)' );
                }
                elseif ( $format === 'html' )
                {
                    if ( preg_match( '/<\s*script\b|\bon[a-z]+\s*=|javascript:/i', $v ) )
                        $this->warning( $path, 'contains script or event handlers: they are removed on import' );
                }
                else
                    $this->error( $path, 'format must be ezxml or html' );
                break;
            case 'ezimage':
            case 'ezbinaryfile':
            case 'ezmedia':
                $file = isset( $value['file'] ) ? $value['file'] : null;
                if ( $file === null )
                    break;
                if ( !is_string( $file ) || !isset( $files[$file] ) )
                {
                    $this->error( $path, 'file must name an entry of files[]' );
                    break;
                }
                $used[$file] = true;
                if ( $datatype === 'ezimage' && strpos( (string) $files[$file]['detected_mime'], 'image/' ) !== 0 )
                    $this->error( $path, 'the file is not an image (' . $files[$file]['detected_mime'] . ')' );
                break;
            case 'ezobjectrelation':
            case 'ezobjectrelationlist':
                $list = $datatype === 'ezobjectrelation' ? ( isset( $value['remote_id'] ) && $value['remote_id'] !== null ? array( $value['remote_id'] ) : array() )
                                                          : ( isset( $value['remote_ids'] ) && is_array( $value['remote_ids'] ) ? $value['remote_ids'] : null );
                if ( $list === null )
                {
                    $this->error( $path, 'remote_ids must be a list' );
                    break;
                }
                foreach ( $list as $rid )
                    if ( !isset( $remoteIDs[(string) $rid] ) && !eZContentObject::fetchByRemoteID( (string) $rid ) )
                        $this->warning( $path, 'the related object ' . $rid . ' is neither in the package nor here: it is left out' );
                break;
            default:
                if ( !is_string( $v ) )
                    $this->error( $path, 'value must be a string' );
        }
    }
}
