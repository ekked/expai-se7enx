<?php
/**
 * File containing the expAiFollowup class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * A message queued while a run works (table expai_followup). delivery "queue": sent as the next message when the
 * turn ends; "steer": delivered at the next call boundary of the lead run (with background runs). state: waiting,
 * sent, dropped. In foreground mode Vector sends the first waiting follow-up itself when a turn ends.
 */
class expAiFollowup extends eZPersistentObject
{
    public static function definition()
    {
        $int = function ( $name ) { return array( 'name' => $name, 'datatype' => 'integer', 'default' => 0, 'required' => true ); };
        $str = function ( $name, $default = '' ) { return array( 'name' => $name, 'datatype' => 'string', 'default' => $default, 'required' => true ); };
        return array(
            'fields' => array(
                'id' => $int( 'ID' ),
                'thread_id' => $int( 'ThreadID' ),
                'position' => $int( 'Position' ),
                'body' => $str( 'Body' ),
                'delivery' => $str( 'Delivery', 'queue' ),
                'state' => $str( 'State', 'waiting' ),
                'created' => $int( 'Created' ),
                'modified' => $int( 'Modified' ),
            ),
            'keys' => array( 'id' ),
            'increment_key' => 'id',
            'class_name' => 'expAiFollowup',
            'sort' => array( 'position' => 'asc' ),
            'name' => 'expai_followup',
        );
    }

    /** @return expAiFollowup[] the waiting follow-ups of a thread in their order */
    public static function waiting( $threadID )
    {
        return (array) eZPersistentObject::fetchObjectList( self::definition(), null, array( 'thread_id' => (int) $threadID, 'state' => 'waiting' ),
                                                            array( 'position' => 'asc', 'id' => 'asc' ), array( 'offset' => 0, 'length' => 50 ) );
    }

    /** @return expAiFollowup|null */
    public static function fetchByID( $id )
    {
        $object = eZPersistentObject::fetchObject( self::definition(), null, array( 'id' => (int) $id ) );
        return $object instanceof self ? $object : null;
    }

    /** @return array for the browser */
    public function present()
    {
        return array( 'id' => (int) $this->attribute( 'id' ), 'body' => (string) $this->attribute( 'body' ),
                      'delivery' => (string) $this->attribute( 'delivery' ), 'position' => (int) $this->attribute( 'position' ) );
    }
}
