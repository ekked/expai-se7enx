<?php
/**
 * The addresses of the extension's documentation.
 *
 * With Exponential 6.0.15 and later the documentation is read in the help center of the administration (exphelp,
 * doc/index.md "Help in the administration interface"), which reads, renders and searches the documentation of every
 * active extension (ids ext/expai/<path below doc/>, and ext/expai/README.md, INSTALL.md, USAGE.md for the files at
 * the top of the extension). An older Exponential (6.0.14) has no help center: there the same page is read on GitHub,
 * in the tree of the release that is installed (GITHUB_REF), so no link leads to a page the installation lacks.
 * helpCenter() decides, for the running kernel and the current user.
 *
 * The links of the extension lead straight to the help center where it is read there (helpCenter()): the menu entry
 * EI: Help (settings/menu.ini.append.php) to its index, exphelp/extensions/expai, the dashboard's Help link and the
 * "?" links to their page, exphelp/viewer/ext/expai/<page>.md (link(), the template operator expai_help_url). Without
 * the help center they lead to the view expai/help, which redirects to target() on GitHub; menuFallback() points the
 * menu entry there on such a kernel. expai/help stays for old bookmarks: the page names of expai/help/(page)/<name>
 * keep working: readme, install and usage are the files at the top of the extension, index is doc/index.md,
 * changelog-<version> is doc/changelog/<version>.md, any other name is doc/<name>.md. A name of a page the extension
 * does not have leads to the index.
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

    /** The view that redirects to the documentation (old bookmarks, and every link where there is no help center) */
    const REDIRECT = 'expai/help';

    /** The documentation on GitHub, in the tree of this release: always 'v' . the Version of ezinfo.php */
    const GITHUB_REF = 'v0.0.1.15';

    /** The repository's address on GitHub */
    const GITHUB_BASE = 'https://github.com/se7enxweb/expai/blob/';

    /** @var bool|null what helpCenter() answers instead of looking (tests), null to look */
    private static $helpCenter = null;

    /** @var bool|null kernelHasHelpCenter() once per process, for menuFallback() (the kernel does not change under it) */
    private static $kernelHelpCenter = null;

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
     * The file of a page name below the extension's directory (README.md, doc/vector.md, doc/changelog/0.0.1.0.md),
     * or null for a name that is none.
     *
     * @param string $name see helpId()
     * @return string|null
     */
    public static function file( $name )
    {
        $id = self::helpId( $name );
        if ( $id === null )
            return null;
        $path = substr( $id, strlen( self::PREFIX ) );
        return in_array( $path, array( 'README.md', 'INSTALL.md', 'USAGE.md' ), true ) ? $path : 'doc/' . $path;
    }

    /**
     * The page name itself when the extension has that page, otherwise '' (the index).
     *
     * @param string $name see helpId()
     * @return string
     */
    public static function existing( $name )
    {
        $file = (string) $name === '' ? null : self::file( $name );
        return $file !== null && is_file( dirname( __DIR__ ) . '/' . $file ) ? strtolower( trim( (string) $name ) ) : '';
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

    /**
     * The page on GitHub, in the tree of this release, or doc/index.md there for an empty or unknown name.
     *
     * @param string $name see helpId()
     * @return string
     */
    public static function githubUrl( $name = '' )
    {
        $file = (string) $name === '' ? null : self::file( $name );
        return self::GITHUB_BASE . self::GITHUB_REF . '/' . ( $file === null ? 'doc/index.md' : $file );
    }

    /**
     * Whether the running kernel's help center shows this extension's documentation: its index class with the
     * extensions' documentation (expHelpDocumentIndex::extensions(), Exponential 6.0.15 and later), the module exphelp,
     * and exphelp.ini [IndexSettings] ActiveExtensionDocs not disabled (or expai named in ExtensionDocDirs[]).
     * Nothing here fails on a kernel without them; the class is looked up through the autoload only.
     *
     * @return bool
     */
    public static function kernelHasHelpCenter()
    {
        if ( !class_exists( 'expHelpDocumentIndex' ) || !method_exists( 'expHelpDocumentIndex', 'extensions' ) )
            return false;
        if ( !class_exists( 'eZModule' ) || !eZModule::exists( 'exphelp' ) )
            return false;
        $ini = eZINI::instance( 'exphelp.ini' );
        $active = $ini->hasVariable( 'IndexSettings', 'ActiveExtensionDocs' ) ? $ini->variable( 'IndexSettings', 'ActiveExtensionDocs' ) : 'enabled';
        if ( $active !== 'disabled' )
            return true;
        $dirs = $ini->hasVariable( 'IndexSettings', 'ExtensionDocDirs' ) ? $ini->variable( 'IndexSettings', 'ExtensionDocDirs' ) : array();
        return is_array( $dirs ) && in_array( 'expai', $dirs, true );
    }

    /**
     * Whether the documentation is read in the help center: the kernel has it (kernelHasHelpCenter()) and the current
     * user may read it (exphelp/read). Otherwise it is read on GitHub.
     *
     * @return bool
     */
    public static function helpCenter()
    {
        if ( self::$helpCenter !== null )
            return self::$helpCenter;
        if ( !self::kernelHasHelpCenter() )
            return false;
        $access = eZUser::currentUser()->hasAccessTo( 'exphelp', 'read' );
        return isset( $access['accessWord'] ) && $access['accessWord'] !== 'no';
    }

    /**
     * Sets what helpCenter() answers (tests); null looks again.
     *
     * @param bool|null $helpCenter
     */
    public static function setHelpCenter( $helpCenter )
    {
        self::$helpCenter = $helpCenter === null ? null : (bool) $helpCenter;
    }

    /**
     * Where expai/help/(page)/<name> leads: the page in the help center ('/exphelp/viewer/...', for
     * eZModule::redirectTo()) or, without it, on GitHub (an absolute address). A page the extension does not have
     * leads to the index.
     *
     * @param string $name see helpId()
     * @return string
     */
    public static function target( $name = '' )
    {
        $name = self::existing( $name );
        return self::helpCenter() ? '/' . self::url( $name ) : self::githubUrl( $name );
    }

    /**
     * The module address a link of the extension uses (for |ezurl): the page in the help center
     * (exphelp/viewer/ext/expai/<page>.md, or exphelp/extensions/expai for an empty name) where it is read there
     * (helpCenter()), otherwise the view expai/help (expai/help/(page)/<page>), which redirects to GitHub. A page the
     * extension does not have leads to the index.
     *
     * @param string $name see helpId()
     * @return string
     */
    public static function link( $name = '' )
    {
        $name = self::existing( $name );
        if ( self::helpCenter() )
            return self::url( $name );
        return $name === '' ? self::REDIRECT : self::REDIRECT . '/(page)/' . $name;
    }

    /**
     * The listener of the kernel's event request/input (settings/site.ini.append.php [Event] Listeners[]): on a kernel
     * without the help center (Exponential 6.0.14, or ActiveExtensionDocs disabled) the menu entry EI: Help, which
     * menu.ini points at exphelp/extensions/expai for users with exphelp/read, leads to expai/help instead and asks for
     * no policy of the help center, which such a kernel does not have. On a kernel with the help center it changes
     * nothing. Only the menu.ini of this request is changed, never a file.
     *
     * @return bool whether the entry was pointed at expai/help
     */
    public static function menuFallback()
    {
        if ( self::$kernelHelpCenter === null )
            self::$kernelHelpCenter = self::kernelHasHelpCenter();
        if ( self::$kernelHelpCenter )
            return false;
        $ini = eZINI::instance( 'menu.ini' );
        if ( !$ini->hasVariable( 'Leftmenu_my', 'Links' ) )
            return false;
        $links = $ini->variable( 'Leftmenu_my', 'Links' );
        if ( !is_array( $links ) || !isset( $links['expai_help'] ) || $links['expai_help'] !== self::INDEX )
            return false;
        $links['expai_help'] = self::REDIRECT;
        $ini->setVariable( 'Leftmenu_my', 'Links', $links );
        if ( $ini->hasVariable( 'Leftmenu_my', 'PolicyList_expai_help' ) )
            $ini->removeSetting( 'Leftmenu_my', 'PolicyList_expai_help' );
        return true;
    }
}
