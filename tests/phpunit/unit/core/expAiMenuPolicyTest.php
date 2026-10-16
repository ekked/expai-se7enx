<?php
/**
 * The menu links of expai check what their views require (settings/menu.ini.append.php against module.php).
 *
 * EI: Help (expai/help) opens with expai/write OR expai/summary; a menu link that required expai/write hid the
 * documentation from a user with expai/summary only, who could open it. A view with "a or b" has no PolicyList
 * (menu.ini [MenuAccessSettings] CheckViewAccess asks the view, as for setup/menu); every other link names its view's
 * function; a link may be narrower than its view only where NARROWER says so.
 *
 * Reads the two files as text and needs no kernel: module.php is not included (it builds limitation values from the
 * database). Also runs on its own: php vendor/bin/phpunit --no-configuration extension/expai/tests/phpunit/unit/core/expAiMenuPolicyTest.php
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

class expAiMenuPolicyTest extends PHPUnit\Framework\TestCase
{
    /** [section/link => policies] entries that are narrower than the view on purpose */
    private const NARROWER = array(
        // in Audit only for those who see everyone's requests
        'Leftmenu_audit/expai_log' => array( 'expai/log' ),
        // the views check only their own function; the menu lists them for users who hold expai/write as well
        'Leftmenu_my/expai_vector' => array( 'expai/write' ),
        'Leftmenu_my/expai_chain'  => array( 'expai/write' ),
    );

    private static function extension()
    {
        return dirname( __DIR__, 4 );
    }

    private static function read( $relative )
    {
        $path = self::extension() . '/' . $relative;
        if ( !is_file( $path ) )
            throw new RuntimeException( "missing $path" );
        return (string) file_get_contents( $path );
    }

    /** @return array<string,string> view => its functions entry ("write", "write or summary") */
    public static function viewFunctions()
    {
        preg_match_all( "/\\\$ViewList\\['(\\w+)'\\]\\s*=\\s*array\\((.*?)\\);/s", self::read( 'modules/expai/module.php' ), $views, PREG_SET_ORDER );
        $functions = array();
        foreach ( $views as $view )
        {
            if ( preg_match( "/'functions'\\s*=>\\s*array\\(\\s*'([^']+)'\\s*\\)/", $view[2], $m ) )
                $functions[$view[1]] = $m[1];
        }
        return $functions;
    }

    /** @return array<string,array{url:string,policies:string[]}> section/link => its address and PolicyList */
    public static function menuLinks()
    {
        $links = array();
        $section = '';
        foreach ( preg_split( '/\R/', self::read( 'settings/menu.ini.append.php' ) ) as $line )
        {
            if ( preg_match( '/^\[(\w+)\]\s*$/', $line, $m ) )
                $section = $m[1];
            elseif ( preg_match( '/^Links\[(\w+)\]=(.+)$/', $line, $m ) )
                $links["$section/{$m[1]}"]['url'] = trim( $m[2] );
            elseif ( preg_match( '/^PolicyList_(\w+)\[\]=(.+)$/', $line, $m ) )
                $links["$section/{$m[1]}"]['policies'][] = trim( $m[2] );
        }
        return $links;
    }

    public function testTheViewsAndLinksAreRead()
    {
        $views = self::viewFunctions();
        $this->assertSame( 'write or summary', $views['help'] ?? null );
        $this->assertSame( 'write', $views['log'] ?? null );
        $this->assertArrayHasKey( 'Leftmenu_my/expai_help', self::menuLinks() );
    }

    public function testEveryLinkChecksWhatItsViewRequires()
    {
        $views = self::viewFunctions();
        $problems = array();
        foreach ( self::menuLinks() as $key => $link )
        {
            if ( !isset( $link['url'] ) || !preg_match( '#^expai/(\w+)#', $link['url'], $m ) || !isset( $views[$m[1]] ) )
                continue;
            $needs = $views[$m[1]];
            $policies = $link['policies'] ?? array();
            if ( strpos( $needs, ' or ' ) !== false )
            {
                if ( $policies !== array() )
                    $problems[] = "$key: {$link['url']} opens with $needs; a PolicyList cannot say that, CheckViewAccess does";
                continue;
            }
            if ( !in_array( "expai/$needs", $policies, true ) )
                $problems[] = "$key: {$link['url']} needs expai/$needs, the menu checks " . ( $policies ? implode( ' + ', $policies ) : 'nothing' );
            foreach ( $policies as $policy )
            {
                if ( strpos( $policy, 'expai/' ) === 0 && $policy !== "expai/$needs" && !in_array( $policy, self::NARROWER[$key] ?? array(), true ) )
                    $problems[] = "$key: $policy is not what {$link['url']} requires (expai/$needs)";
            }
        }
        $this->assertSame( array(), $problems );
    }
}
