<?php
/**
 * expai/vector: the Vector workspace (doc/vector.md). The view draws the shell only; the threads, the conversation
 * and every action come through the ezjscore functions expaivector::* (classes/vector/expaivectorserverfunctions.php),
 * so nothing personal is part of the page and switching threads does not reload it.
 *
 * View parameters (no GET parameters):
 *   expai/vector                      the newest active thread, or none
 *   expai/vector/(thread)/<uuid>      that thread
 *   expai/vector/(node)/<node id>     a new thread with that node as its scope ("New thread here")
 *   expai/vector/(tab)/plan|diff|cost the right pane's tab
 *   expai/vector/(shelf)/settled|archived  the list of settled or archived threads
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$config = expAiConfig::instance();
$viewParameters = isset( $Params['UserParameters'] ) && is_array( $Params['UserParameters'] ) ? $Params['UserParameters'] : array();
$param = function ( $name ) use ( $viewParameters )
{
    return isset( $viewParameters[$name] ) && is_scalar( $viewParameters[$name] ) ? (string) $viewParameters[$name] : '';
};

$thread = $param( 'thread' );
$thread = expAiThread::isUuid( $thread ) ? $thread : '';
$nodeID = (int) $param( 'node' );
$node = $nodeID ? eZContentObjectTreeNode::fetch( $nodeID ) : null;
if ( !$node instanceof eZContentObjectTreeNode || !$node->canRead() )
{
    $node = null;
    $nodeID = 0;
}
$tab = in_array( $param( 'tab' ), array( 'plan', 'diff', 'cost' ), true ) ? $param( 'tab' ) : '';
$shelf = in_array( $param( 'shelf' ), array( 'settled', 'archived' ), true ) ? $param( 'shelf' ) : '';

$panes = array();
foreach ( $config->arr( 'VectorSettings', 'Panes' ) as $pane )
{
    $template = (string) $config->get( 'VectorPane_' . $pane, 'Template', '' );
    if ( preg_match( '/^[a-z0-9_]+$/', $pane ) && $template !== '' )
        $panes[$pane] = $template;
}

$tpl = eZTemplate::factory();
$tpl->setVariable( 'vector_enabled', $config->flag( 'VectorSettings', 'Enabled', true ) );
$tpl->setVariable( 'ai_ready', $config->isEnabled() && $config->isConfigured() );
$tpl->setVariable( 'tables_ready', expAiThread::available() );
$tpl->setVariable( 'thread_uuid', $thread );
$tpl->setVariable( 'start_node', $node );
$tpl->setVariable( 'tab', $tab );
$tpl->setVariable( 'shelf', $shelf );
$tpl->setVariable( 'panes', $panes );
$tpl->setVariable( 'cost_enabled', expAiCost::enabled() );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:expai/vector.tpl' );
$Result['path'] = array( array( 'url' => 'content/dashboard', 'text' => ezpI18n::tr( 'extension/expai/dashboard', 'Dashboard' ) ),
                         array( 'url' => 'expai/dashboard', 'text' => ezpI18n::tr( 'extension/expai/dashboard', 'Exponential Intelligence' ) ),
                         array( 'url' => false, 'text' => ezpI18n::tr( 'extension/expai/vector', 'Vector' ) ) );
