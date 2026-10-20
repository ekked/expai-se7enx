<?php
/**
 * expai/help (policy expai/write or expai/summary): the address of the extension's documentation, which every link of
 * the extension uses (the menu entry EI: Help, the dashboard's Help link, the "?" links). It redirects to the page:
 * in the help center of the administration where the kernel has one that shows extension documentation (Exponential
 * 6.0.15 and later) and the user may read it (exphelp/read), otherwise on GitHub, in the tree of the installed release
 * (Exponential 6.0.14). expai/help leads to the index, expai/help/(page)/<name> to that page; see expAiHelpLinks.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$name = isset( $Params['UserParameters']['page'] ) ? (string) $Params['UserParameters']['page'] : '';
$target = expAiHelpLinks::target( $name );
if ( $target[0] === '/' )
    return $Module->redirectTo( $target );

// GitHub is another host, which the kernel's redirect refuses unless a site allows it (AllowedRedirectHosts[]); the
// address is built here from a fixed base and a checked page name, so the view sends it itself
header( 'Location: ' . $target, true, 302 );
header( 'Cache-Control: no-store' );
eZDB::checkTransactionCounter();
eZExecution::cleanExit();
