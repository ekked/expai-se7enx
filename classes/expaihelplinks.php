<?php
/**
 * The addresses of the extension's documentation in the help center of the administration (exphelp, doc/index.md
 * "Help in the administration interface"). The documentation is read, rendered and searched there, as the documentation
 * of every active extension (ids ext/expai/<path below doc/>, and ext/expai/README.md, INSTALL.md, USAGE.md for the files
 * at the top of the extension); this class only names the pages.
 *
 * The page names of the former view expai/help/(page)/<name> keep working: helpId() turns one into its page id, and the
 * view redirects there. readme, install and usage are the files at the top of the extension, index is doc/index.md,
 * changelog-<version> is doc/changelog/<version>.md, any other name is doc/<name>.md.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */
class expAiHelpLinks
{
    /** The extension's folder in the help center */
    const PREFIX = 'ext/expai/';

    /** The extension's index in the help center (its pages and a search limited to them) */
    const INDEX = 'exphelp/extensions/expai';

    /**
     * The help center's page id of a page name of expai/help, or null for a name that is none.
     *
     * @param string $name readme, install, usage, index, changelog-<version>, or a doc/*.md name without .md
     * @return string|null
     */
    public static function helpId( $name )
    {
        $name = strtolower( trim( (string) $name ) );
        if ( !preg_match( '/^[a-z0-9][a-z0-9._-]{0,63}$/', $name ) )
            return null;
        $top = array( 'readme' => 'README.md', 'install' => 'INSTALL.md', 'usage' => 'USAGE.md' );
        if ( isset( $top[$name] ) )
            return self::PREFIX . $top[$name];
        if ( preg_match( '/^changelog-([0-9][0-9a-z.-]*)$/', $name, $m ) )
            return self::PREFIX . 'changelog/' . $m[1] . '.md';
        if ( !preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $name ) )
            return null;
        return self::PREFIX . $name . '.md';
    }

    /**
     * The module address of a page in the help center's viewer (for |ezurl), or the extension's index for an empty
     * or unknown name.
     *
     * @param string $name see helpId()
     * @return string
     */
    public static function url( $name = '' )
    {
        $id = (string) $name === '' ? null : self::helpId( $name );
        return $id === null ? self::INDEX : 'exphelp/viewer/' . $id;
    }
}
