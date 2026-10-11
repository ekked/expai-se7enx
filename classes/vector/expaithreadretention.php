<?php
/**
 * File containing the expAiThreadRetention class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The retention of thread data (owner decision 2026-10-11: events, proposals and checkpoints are kept 30 days),
 * run by the cronjob part "expai" (cronjobs/expai_prune.php) and by bin/php/expaithreads.php --prune:
 *
 *   expai_thread_event   older than [ThreadSettings] EventKeepDays
 *   expai_change         older than [ThreadSettings] ProposalKeepDays
 *   expai_checkpoint     older than [ThreadSettings] CheckpointKeepDays
 *   expai_plan           older than ProposalKeepDays and not running
 *   expai_followup       sent or dropped, older than EventKeepDays
 *
 * 0 keeps the rows. The threads themselves, their runs and conversations stay (the editor settles or archives them).
 */
class expAiThreadRetention
{
    /** @return array a minimal persistent object definition of a thread table (for counts and removals) */
    public static function tableDefinition( $table )
    {
        $int = array( 'name' => 'X', 'datatype' => 'integer', 'default' => 0, 'required' => true );
        $str = array( 'name' => 'X', 'datatype' => 'string', 'default' => '', 'required' => true );
        $fields = array( 'id' => $int, 'thread_id' => $int, 'created' => $int );
        if ( in_array( $table, array( 'expai_followup', 'expai_plan' ), true ) )
            $fields['state'] = $str;
        return array( 'fields' => $fields, 'keys' => array( 'id' ), 'name' => $table, 'class_name' => false );
    }

    /**
     * @param bool $dryRun count only
     * @return array table => rows removed (or that would be removed)
     */
    public static function prune( $dryRun = false, ?expAiConfig $config = null )
    {
        $config = $config ?: expAiConfig::instance();
        $out = array();
        if ( !expAiThread::available() )
            return $out;
        $now = time();
        $eventDays = $config->int( 'ThreadSettings', 'EventKeepDays', 30, 0 );
        $proposalDays = $config->int( 'ThreadSettings', 'ProposalKeepDays', 30, 0 );
        $checkpointDays = $config->int( 'ThreadSettings', 'CheckpointKeepDays', 30, 0 );
        $rules = array(
            'expai_thread_event' => array( $eventDays, '' ),
            'expai_change' => array( $proposalDays, '' ),
            'expai_checkpoint' => array( $checkpointDays, '' ),
            'expai_plan' => array( $proposalDays, " AND state <> 'running'" ),
            'expai_followup' => array( $eventDays, " AND state <> 'waiting'" ),
        );
        $db = eZDB::instance();
        foreach ( $rules as $table => $rule )
        {
            list( $days, $extra ) = $rule;
            if ( $days <= 0 )
            {
                $out[$table] = 0;
                continue;
            }
            $where = 'created < ' . (int) ( $now - $days * 86400 ) . $extra;
            $rows = $db->arrayQuery( "SELECT COUNT(*) AS n FROM $table WHERE $where" );
            $row = is_array( $rows ) && $rows ? array_change_key_case( $rows[0], CASE_LOWER ) : array( 'n' => 0 );
            $count = (int) $row['n'];
            if ( $count && !$dryRun )
                $db->query( "DELETE FROM $table WHERE $where" );
            $out[$table] = $count;
        }
        return $out;
    }
}
