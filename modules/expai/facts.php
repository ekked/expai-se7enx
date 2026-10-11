<?php
/**
 * expai/facts (policy expai/facts): the "AI facts" page for a data protection officer (doc/ai-facts.md). One printable
 * page with what goes where: per provider the region, the retention, the date the terms were checked, where the key
 * comes from (never the key), who may use it, the models with their write gate and probe date, the lanes and their
 * retention, the data policy rules, the limits and budgets against this month's usage, the tools that reach outside
 * the site and the master switch. No view parameters change what is shown; printing uses the browser (Save as PDF).
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/** @var eZModule $Module */
$Module = $Params['Module'];

$tpl = eZTemplate::factory();
$tpl->setVariable( 'facts', ( new expAiFacts() )->collect() );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:expai/facts.tpl' );
$Result['path'] = array( array( 'url' => 'expai/dashboard', 'text' => ezpI18n::tr( 'extension/expai/facts', 'Exponential Intelligence' ) ),
                         array( 'url' => false, 'text' => ezpI18n::tr( 'extension/expai/facts', 'AI facts' ) ) );
