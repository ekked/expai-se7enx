<?php
/**
 * Cronjob expai_prune: removes rows of expai_usage_log older than expai.ini [UsageSettings] RetentionDays.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/** @var eZCLI $cli */
$days = expAiConfig::instance()->int( 'UsageSettings', 'RetentionDays', 365, 0 );
if ( $days <= 0 )
{
    if ( !$isQuiet )
        $cli->output( 'expai: RetentionDays is 0, usage rows are kept' );
    return;
}
$removed = expAiUsage::prune( $days );
if ( !$isQuiet )
    $cli->output( "expai: removed $removed usage row(s) older than $days days" );
