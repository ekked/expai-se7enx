<?php
/**
 * expai/help (policy expai/write or expai/facts): the extension's documentation inside the administration interface
 * (doc/index.md, "Help in the administration interface"). (page)/<name> shows one document: readme, install, usage,
 * index, the name of any doc/*.md without .md (providers, vector, ai-facts, ...) or changelog-<version>. The documents
 * are rendered from Markdown by expAiHelp, which escapes every character of them; an unknown name is "not found".
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$name = isset( $Params['UserParameters']['page'] ) ? strtolower( (string) $Params['UserParameters']['page'] ) : 'index';
$help = new expAiHelp();
if ( !preg_match( '/^[a-z0-9][a-z0-9._-]{0,63}$/', $name ) || !$help->exists( $name ) )
    return $Module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );

$page = $help->page( $name );
$tpl = eZTemplate::factory();
$tpl->setVariable( 'page', $page );
$tpl->setVariable( 'pages', $help->pages() );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:expai/help.tpl' );
$Result['path'] = array( array( 'url' => 'expai/help', 'text' => ezpI18n::tr( 'extension/expai/help', 'Exponential Intelligence help' ) ),
                         array( 'url' => false, 'text' => $page['title'] ) );
