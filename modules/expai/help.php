<?php
/**
 * expai/help (policy expai/write or expai/summary): the former address of the extension's documentation. The
 * documentation is part of the help center of the administration now (exphelp, doc/index.md "Help in the administration
 * interface"), read, rendered and searched there; this view only redirects an old address to the same page there:
 * expai/help to the extension's index (exphelp/extensions/expai), expai/help/(page)/<name> to its page
 * (exphelp/viewer/ext/expai/...), see expAiHelpLinks.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$name = isset( $Params['UserParameters']['page'] ) ? (string) $Params['UserParameters']['page'] : '';
return $Module->redirectTo( '/' . expAiHelpLinks::url( $name ) );
