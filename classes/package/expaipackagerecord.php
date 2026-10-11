<?php
/**
 * File containing the expAiPackageRecord class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * A package the server keeps (table expai_package): name, manifest checksum, counts, where it is stored
 * (var/<vardir>/expai/packages/<storage>/), who uploaded it, its state and the report of its last validation, dry
 * run or import (JSON).
 *
 * States: uploaded, valid, invalid, importing, imported, failed.
 */
class expAiPackageRecord extends eZPersistentObject
{
    public static function definition()
    {
        $int = function ( $name ) { return array( 'name' => $name, 'datatype' => 'integer', 'default' => 0, 'required' => true ); };
        $str = function ( $name, $default = '' ) { return array( 'name' => $name, 'datatype' => 'string', 'default' => $default, 'required' => true ); };
        return array(
            'fields' => array(
                'id' => $int( 'ID' ),
                'uuid' => $str( 'UUID' ),
                'name' => $str( 'Name' ),
                'checksum' => $str( 'Checksum' ),
                'format_version' => $str( 'FormatVersion' ),
                'state' => $str( 'State', 'uploaded' ),
                'object_count' => $int( 'ObjectCount' ),
                'file_count' => $int( 'FileCount' ),
                'bytes' => $int( 'Bytes' ),
                'origin' => $str( 'Origin' ),
                'storage' => $str( 'Storage' ),
                'user_id' => $int( 'UserID' ),
                'group_uuid' => $str( 'GroupUUID' ),
                'report' => $str( 'Report' ),
                'created' => $int( 'Created' ),
                'modified' => $int( 'Modified' ),
            ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'function_attributes' => array( 'report_array' => 'reportArray', 'user' => 'user', 'progress' => 'progress' ),
            'class_name' => 'expAiPackageRecord',
            'sort' => array( 'id' => 'desc' ),
            'name' => 'expai_package',
        );
    }

    /** @return expAiPackageRecord|null */
    public static function fetchByUUID( $uuid )
    {
        if ( !preg_match( '/^[0-9a-f-]{36}$/', (string) $uuid ) )
            return null;
        return eZPersistentObject::fetchObject( self::definition(), null, array( 'uuid' => (string) $uuid ), true );
    }

    /** @return expAiPackageRecord[] */
    public static function fetchList( $offset = 0, $limit = 25, $userID = false )
    {
        return (array) eZPersistentObject::fetchObjectList( self::definition(), null, $userID ? array( 'user_id' => (int) $userID ) : null,
                                                            array( 'id' => 'desc' ), array( 'offset' => (int) $offset, 'length' => (int) $limit ), true );
    }

    /** @return int */
    public static function fetchCount( $userID = false )
    {
        return (int) eZPersistentObject::count( self::definition(), $userID ? array( 'user_id' => (int) $userID ) : null );
    }

    /** @return string the directory of the package */
    public function directory()
    {
        return expAiPackage::storageRoot() . '/' . preg_replace( '/[^0-9a-f-]/', '', (string) $this->attribute( 'storage' ) );
    }

    /** @return expAiPackage */
    public function package()
    {
        return expAiPackage::fromDirectory( $this->directory() );
    }

    /** @return array */
    public function reportArray()
    {
        $report = json_decode( (string) $this->attribute( 'report' ), true );
        return is_array( $report ) ? $report : array();
    }

    /** Stores a report (merged into the existing one under its key: validation, diff, import). */
    public function setReport( $key, array $report, $state = null )
    {
        $all = $this->reportArray();
        $all[$key] = $report;
        $this->setAttribute( 'report', json_encode( $all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR ) );
        if ( $state !== null )
            $this->setAttribute( 'state', $state );
        $this->setAttribute( 'modified', time() );
        $this->store();
    }

    /** @return eZUser|null */
    public function user()
    {
        return eZUser::fetch( (int) $this->attribute( 'user_id' ) );
    }

    /** @return array|null the progress of its import group (done, dead, running, active, total, percent) */
    public function progress()
    {
        if ( (string) $this->attribute( 'group_uuid' ) === '' || !class_exists( 'expQueue' ) )
            return null;
        $group = expQueue::resolveGroup( (string) $this->attribute( 'group_uuid' ) );
        return $group ? $group->progress() : null;
    }
}
