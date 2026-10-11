<?php
/**
 * File containing the expAiPackage class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * A content package (format expai-package 1.0, schema share/package/schema/v1.json): package.json and the files it
 * names (files/<sha256>.<ext>), as a directory or a zip file (.exppkg / .zip). Packages are untrusted input: a zip
 * may contain only package.json and files/<64 hex>.<ext>, within size, count and ratio limits; package.json is read
 * with a size and depth limit.
 *
 * The manifest checksum is the SHA-256 of the canonical JSON of the package without its "checksum" member: object
 * members sorted by name at every level (lists keep their order), no whitespace, UTF-8 characters and "/" written as
 * they are (see doc/package-format.md). Every file has its own sha256 and size in files[].
 *
 * Packages the server keeps (uploads, imports) live in var/<vardir>/expai/packages/<uuid>/ (never below the web root
 * of an extension), owned by the site user.
 */
class expAiPackage
{
    const FORMAT = 'expai-package';
    const VERSION = '1.0';

    /** @var array the decoded package.json */
    protected $data;

    /** @var string the directory with package.json and files/ ('' for a package only in memory) */
    protected $dir;

    /** @var string the raw package.json */
    protected $raw;

    protected function __construct( array $data, $dir, $raw )
    {
        $this->data = $data;
        $this->dir = (string) $dir;
        $this->raw = (string) $raw;
    }

    /** @return array */
    public function data()
    {
        return $this->data;
    }

    /** @return string */
    public function dir()
    {
        return $this->dir;
    }

    /** @return string the package uuid ('' when the package names none) */
    public function uuid()
    {
        return isset( $this->data['package']['uuid'] ) && is_string( $this->data['package']['uuid'] ) ? $this->data['package']['uuid'] : '';
    }

    /** @return string */
    public function name()
    {
        if ( isset( $this->data['package']['name'] ) && is_string( $this->data['package']['name'] ) && $this->data['package']['name'] !== '' )
            return $this->data['package']['name'];
        return $this->uuid() !== '' ? $this->uuid() : 'package';
    }

    /** @return array[] the objects */
    public function objects()
    {
        return isset( $this->data['objects'] ) && is_array( $this->data['objects'] ) ? array_values( $this->data['objects'] ) : array();
    }

    /** @return array[] path => file entry */
    public function files()
    {
        $out = array();
        foreach ( isset( $this->data['files'] ) && is_array( $this->data['files'] ) ? $this->data['files'] : array() as $file )
            if ( is_array( $file ) && isset( $file['path'] ) && is_string( $file['path'] ) )
                $out[$file['path']] = $file;
        return $out;
    }

    /** @return string|null the absolute path of a package file (checked: inside files/, a safe name) */
    public function filePath( $path )
    {
        if ( $this->dir === '' || !self::safeFileName( $path ) )
            return null;
        $full = $this->dir . '/' . $path;
        return is_file( $full ) ? $full : null;
    }

    /** @return bool a name allowed for a package file: files/<64 hex>.<ext> */
    public static function safeFileName( $path )
    {
        return is_string( $path ) && preg_match( '~^files/[0-9a-f]{64}\.[a-z0-9]{1,8}$~', $path ) === 1;
    }

    // ---- settings ----------------------------------------------------------------------------------------------

    /** @return int */
    public static function limit( $name, $default )
    {
        return max( 1, (int) expAiConfig::instance()->get( 'PackageSettings', $name, $default ) );
    }

    /** @return string the directory of the packages the server keeps */
    public static function storageRoot()
    {
        $dir = eZSys::varDirectory() . '/expai/packages';
        if ( !is_dir( $dir ) )
        {
            eZDir::mkdir( $dir, 0770, true );
            self::fixOwner( dirname( $dir ) );
            self::fixOwner( $dir );
            // never served: var/ is not the web root of an extension, and the deny-all file says so to Apache too
            @file_put_contents( $dir . '/.htaccess', "Require all denied\n" );
            self::fixOwner( $dir . '/.htaccess' );
        }
        return $dir;
    }

    /** Gives a path the site user as owner when the process runs as root (the queue's workers run as that user). */
    public static function fixOwner( $path )
    {
        if ( class_exists( 'expContentJobStore' ) && method_exists( 'expContentJobStore', 'fixOwner' ) )
            expContentJobStore::fixOwner( $path );
    }

    // ---- reading ---------------------------------------------------------------------------------------------------

    /**
     * Reads a package from a directory, a zip file or a package.json file.
     * @return expAiPackage
     * @throws expAiPackageException
     */
    public static function open( $path, $siblingFiles = true )
    {
        $path = (string) $path;
        if ( is_dir( $path ) )
            return self::fromDirectory( $path );
        if ( !is_file( $path ) )
            throw new expAiPackageException( 'The package %path does not exist.', array( '%path' => basename( $path ) ) );
        $head = (string) file_get_contents( $path, false, null, 0, 4 );
        if ( $head === "PK\x03\x04" )
        {
            $dir = self::storageRoot() . '/tmp-' . bin2hex( random_bytes( 8 ) );
            self::extractZip( $path, $dir );
            return self::fromDirectory( $dir );
        }
        // a package.json on its own: its files are next to it (files/), except for an upload, which has none
        return self::fromJson( (string) file_get_contents( $path, false, null, 0, self::limit( 'MaxJsonBytes', 52428800 ) + 1 ), $siblingFiles ? dirname( $path ) : '' );
    }

    /** @return expAiPackage */
    public static function fromDirectory( $dir )
    {
        $dir = rtrim( (string) $dir, '/' );
        $file = $dir . '/package.json';
        if ( !is_file( $file ) )
            throw new expAiPackageException( 'The package has no package.json.' );
        return self::fromJson( (string) file_get_contents( $file, false, null, 0, self::limit( 'MaxJsonBytes', 52428800 ) + 1 ), $dir );
    }

    /**
     * @param string $json
     * @param string $dir where its files are ('' when none)
     * @return expAiPackage
     */
    public static function fromJson( $json, $dir = '' )
    {
        if ( strlen( $json ) > self::limit( 'MaxJsonBytes', 52428800 ) )
            throw new expAiPackageException( 'package.json is larger than %max bytes.', array( '%max' => self::limit( 'MaxJsonBytes', 52428800 ) ) );
        $data = json_decode( $json, true, 64 );
        if ( !is_array( $data ) )
            throw new expAiPackageException( 'package.json is not valid JSON: %error', array( '%error' => json_last_error_msg() ) );
        return new self( $data, $dir, $json );
    }

    /** @return expAiPackage a package built in memory (export) */
    public static function fromArray( array $data, $dir = '' )
    {
        return new self( $data, $dir, '' );
    }

    /**
     * Extracts a zip safely: only package.json and files/<sha256>.<ext>, no links, within the size limits.
     * @throws expAiPackageException
     */
    public static function extractZip( $zipPath, $dir )
    {
        if ( !class_exists( 'ZipArchive' ) )
            throw new expAiPackageException( 'Zip packages need the PHP zip extension; upload a directory or package.json instead.' );
        $zip = new ZipArchive();
        if ( $zip->open( $zipPath ) !== true )
            throw new expAiPackageException( 'The zip file could not be opened.' );
        $maxEntries = self::limit( 'MaxFiles', 5000 ) + 1;
        $maxTotal = self::limit( 'MaxBytes', 524288000 );
        $maxFile = self::limit( 'MaxFileBytes', 52428800 );
        if ( $zip->numFiles > $maxEntries )
            throw new expAiPackageException( 'The package has more than %max entries.', array( '%max' => $maxEntries ) );
        $total = 0;
        $entries = array();
        for ( $i = 0; $i < $zip->numFiles; $i++ )
        {
            $stat = $zip->statIndex( $i );
            $name = (string) $stat['name'];
            if ( substr( $name, -1 ) === '/' )
            {
                if ( $name !== 'files/' )
                    throw new expAiPackageException( 'The package contains the directory %name, which is not allowed.', array( '%name' => substr( $name, 0, 80 ) ) );
                continue;
            }
            if ( $name !== 'package.json' && !self::safeFileName( $name ) )
                throw new expAiPackageException( 'The package contains %name; only package.json and files/<sha256>.<ext> are allowed.', array( '%name' => substr( $name, 0, 80 ) ) );
            // symbolic links (unix mode 0120000 in the external attributes)
            if ( $zip->getExternalAttributesIndex( $i, $opsys, $attr ) && $opsys === ZipArchive::OPSYS_UNIX && ( ( $attr >> 16 ) & 0170000 ) === 0120000 )
                throw new expAiPackageException( 'The package contains a symbolic link.' );
            $size = (int) $stat['size'];
            $limit = $name === 'package.json' ? self::limit( 'MaxJsonBytes', 52428800 ) : $maxFile;
            if ( $size > $limit )
                throw new expAiPackageException( '%name is larger than %max bytes.', array( '%name' => $name, '%max' => $limit ) );
            $ratio = (int) $stat['comp_size'] > 0 ? $size / (int) $stat['comp_size'] : ( $size > 0 ? PHP_INT_MAX : 0 );
            if ( $size > 1048576 && $ratio > self::limit( 'MaxCompressionRatio', 200 ) )
                throw new expAiPackageException( '%name is compressed suspiciously well (a zip bomb?).', array( '%name' => $name ) );
            $total += $size;
            if ( $total > $maxTotal )
                throw new expAiPackageException( 'The package is larger than %max bytes.', array( '%max' => $maxTotal ) );
            $entries[$i] = $name;
        }
        if ( !in_array( 'package.json', $entries, true ) )
            throw new expAiPackageException( 'The package has no package.json.' );
        eZDir::mkdir( $dir . '/files', 0770, true );
        self::fixOwner( $dir );
        self::fixOwner( $dir . '/files' );
        foreach ( $entries as $i => $name )
        {
            $in = $zip->getStream( $name );
            if ( !$in )
                throw new expAiPackageException( '%name could not be read from the zip file.', array( '%name' => $name ) );
            $target = $dir . '/' . $name;
            $out = fopen( $target, 'wb' );
            $written = 0;
            $limit = $name === 'package.json' ? self::limit( 'MaxJsonBytes', 52428800 ) : $maxFile;
            while ( !feof( $in ) )
            {
                $chunk = fread( $in, 65536 );
                $written += strlen( $chunk );
                // the real size, not the size the zip claims
                if ( $written > $limit )
                {
                    fclose( $in );
                    fclose( $out );
                    throw new expAiPackageException( '%name is larger than %max bytes.', array( '%name' => $name, '%max' => $limit ) );
                }
                fwrite( $out, $chunk );
            }
            fclose( $in );
            fclose( $out );
            self::fixOwner( $target );
        }
        $zip->close();
    }

    /** Removes the temporary directory a zip was extracted into (nothing for a package kept elsewhere). */
    public function release()
    {
        if ( $this->dir !== '' && strpos( $this->dir, self::storageRoot() . '/tmp-' ) === 0 && is_dir( $this->dir ) )
            eZDir::recursiveDelete( $this->dir );
    }

    // ---- checksums -------------------------------------------------------------------------------------------------

    /**
     * The canonical JSON of a value: members sorted at every level, no whitespace. Objects are decoded as objects
     * (json_decode without assoc), so an empty object stays {} and an empty list [].
     * @return string
     */
    public static function canonical( $value )
    {
        return json_encode( self::sortMembers( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRESERVE_ZERO_FRACTION );
    }

    /** @return mixed the value with the members of every object sorted */
    protected static function sortMembers( $value )
    {
        if ( $value instanceof stdClass )
        {
            $members = get_object_vars( $value );
            ksort( $members, SORT_STRING );
            $out = new stdClass();
            foreach ( $members as $name => $member )
                $out->$name = self::sortMembers( $member );
            return $out;
        }
        if ( is_array( $value ) )
            return array_map( array( 'expAiPackage', 'sortMembers' ), $value );
        return $value;
    }

    /** @return string the manifest checksum of a package JSON text (sha256 hex) */
    public static function checksumOf( $json )
    {
        $value = json_decode( (string) $json, false, 64 );
        if ( !$value instanceof stdClass )
            return '';
        unset( $value->checksum );
        return hash( 'sha256', self::canonical( $value ) );
    }

    /** @return string the checksum of this package as it was read (or built) */
    public function computeChecksum()
    {
        return self::checksumOf( $this->raw !== '' ? $this->raw : $this->encode( false ) );
    }

    /** @return string the checksum the package states ('' when none) */
    public function statedChecksum()
    {
        return isset( $this->data['checksum'] ) && is_string( $this->data['checksum'] ) ? strtolower( $this->data['checksum'] ) : '';
    }

    /** @return string package.json (pretty, with the checksum when $withChecksum) */
    public function encode( $withChecksum = true )
    {
        $data = $this->data;
        unset( $data['checksum'] );
        $json = json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION );
        if ( !$withChecksum )
            return $json;
        $data['checksum'] = self::checksumOf( $json );
        return json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION ) . "\n";
    }

    // ---- writing ---------------------------------------------------------------------------------------------------

    /**
     * Writes the package: a directory (package.json, files/), or a zip when $path ends in .exppkg or .zip. Files
     * are copied from this package's directory.
     * @return string the path written
     */
    public function save( $path, array $sources = array() )
    {
        $path = (string) $path;
        $json = $this->encode( true );
        $this->data = json_decode( $json, true );
        $this->raw = $json;
        if ( preg_match( '/\.(exppkg|zip)$/i', $path ) )
        {
            if ( !class_exists( 'ZipArchive' ) )
                throw new expAiPackageException( 'Zip packages need the PHP zip extension; write a directory instead.' );
            $zip = new ZipArchive();
            if ( $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true )
                throw new expAiPackageException( 'The zip file could not be written.' );
            $zip->addFromString( 'package.json', $json );
            foreach ( $this->files() as $rel => $file )
            {
                $source = isset( $sources[$rel] ) ? $sources[$rel] : $this->filePath( $rel );
                if ( $source && is_file( $source ) )
                    $zip->addFile( $source, $rel );
            }
            $zip->close();
            self::fixOwner( $path );
            return $path;
        }
        eZDir::mkdir( $path . '/files', 0770, true );
        file_put_contents( $path . '/package.json', $json );
        foreach ( $this->files() as $rel => $file )
        {
            $source = isset( $sources[$rel] ) ? $sources[$rel] : $this->filePath( $rel );
            if ( $source && is_file( $source ) && realpath( $source ) !== realpath( $path . '/' . $rel ) )
                copy( $source, $path . '/' . $rel );
        }
        self::fixOwner( $path );
        self::fixOwner( $path . '/files' );
        self::fixOwner( $path . '/package.json' );
        $this->dir = rtrim( $path, '/' );
        return $path;
    }

    /**
     * Keeps a package on the server (var/<vardir>/expai/packages/<uuid>/) and records it in expai_package.
     * @return expAiPackageRecord
     */
    public static function store( expAiPackage $package, $origin, $userID )
    {
        $uuid = expQueue::uuid();
        $dir = self::storageRoot() . '/' . $uuid;
        if ( $package->dir() !== '' && strpos( $package->dir(), self::storageRoot() . '/tmp-' ) === 0 )
        {
            rename( $package->dir(), $dir );
            $package->dir = $dir;
            if ( !is_file( $dir . '/package.json' ) )
                file_put_contents( $dir . '/package.json', $package->raw );
        }
        else
        {
            eZDir::mkdir( $dir . '/files', 0770, true );
            file_put_contents( $dir . '/package.json', $package->raw !== '' ? $package->raw : $package->encode( true ) );
            foreach ( $package->files() as $rel => $file )
            {
                $source = $package->filePath( $rel );
                if ( $source )
                    copy( $source, $dir . '/' . $rel );
            }
            $package->dir = $dir;
        }
        foreach ( array( $dir, $dir . '/files', $dir . '/package.json' ) as $p )
            self::fixOwner( $p );
        foreach ( glob( $dir . '/files/*' ) ?: array() as $p )
            self::fixOwner( $p );
        $bytes = 0;
        foreach ( glob( $dir . '/files/*' ) ?: array() as $p )
            $bytes += (int) filesize( $p );
        $now = time();
        $record = new expAiPackageRecord( array(
            'uuid' => $uuid, 'name' => expAiText::cut( $package->name(), 150 ), 'checksum' => substr( $package->statedChecksum(), 0, 64 ),
            'format_version' => isset( $package->data['format_version'] ) ? substr( (string) $package->data['format_version'], 0, 10 ) : '',
            'state' => 'uploaded', 'object_count' => count( $package->objects() ), 'file_count' => count( $package->files() ),
            'bytes' => $bytes + strlen( $package->raw ), 'origin' => substr( (string) $origin, 0, 20 ), 'storage' => $uuid,
            'user_id' => (int) $userID, 'created' => $now, 'modified' => $now, 'report' => '' ) );
        $record->store();
        return $record;
    }
}
