<?php
/**
 * expAiHelpLinks: the extension's documentation is in the help center (exphelp); the page names of the former view
 * expai/help/(page)/<name> become its page ids there, and the "?" links point there. Reads files and needs no database.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

class expAiHelpLinksTest extends PHPUnit\Framework\TestCase
{
    private static function extension()
    {
        return dirname( __DIR__, 4 );
    }

    public static function setUpBeforeClass(): void
    {
        if ( !class_exists( 'expAiHelpLinks', false ) )
            require_once self::extension() . '/classes/expaihelplinks.php';
    }

    public function testOldPageNamesBecomeHelpCenterIds(): void
    {
        $this->assertSame( 'ext/expai/README.md', expAiHelpLinks::helpId( 'readme' ) );
        $this->assertSame( 'ext/expai/INSTALL.md', expAiHelpLinks::helpId( 'install' ) );
        $this->assertSame( 'ext/expai/USAGE.md', expAiHelpLinks::helpId( 'USAGE' ) );
        $this->assertSame( 'ext/expai/index.md', expAiHelpLinks::helpId( 'index' ) );
        $this->assertSame( 'ext/expai/vector.md', expAiHelpLinks::helpId( 'vector' ) );
        $this->assertSame( 'ext/expai/full-view-panel.md', expAiHelpLinks::helpId( 'full-view-panel' ) );
        $this->assertSame( 'ext/expai/changelog/0.0.1.0.md', expAiHelpLinks::helpId( 'changelog-0.0.1.0' ) );
        foreach ( array( '', '../etc/passwd', 'a/b', 'x.y', '.hidden', 'a b', str_repeat( 'a', 80 ), 'javascript:alert(1)' ) as $bad )
            $this->assertNull( expAiHelpLinks::helpId( $bad ), $bad );
    }

    public function testAddresses(): void
    {
        $this->assertSame( 'exphelp/extensions/expai', expAiHelpLinks::url() );
        $this->assertSame( 'exphelp/extensions/expai', expAiHelpLinks::url( '../x' ) );
        $this->assertSame( 'exphelp/viewer/ext/expai/chains.md', expAiHelpLinks::url( 'chains' ) );
    }

    /** Every "?" link names a page the extension has, and nothing renders the documentation in the extension itself */
    public function testHelpLinksNameExistingPagesAndNoViewerIsLeft(): void
    {
        $root = self::extension();
        $pages = array();
        foreach ( glob( $root . '/design/admin/templates/{,*/,*/*/}*.tpl', GLOB_BRACE ) ?: array() as $file )
            if ( preg_match_all( "/help_link\\.tpl' page='([a-z0-9-]+)'/", (string) file_get_contents( $file ), $m ) )
                $pages = array_merge( $pages, $m[1] );
        $this->assertNotEmpty( $pages );
        foreach ( array_unique( $pages ) as $page )
            $this->assertFileExists( $root . '/doc/' . $page . '.md', "the \"?\" link to $page" );
        $this->assertFileDoesNotExist( $root . '/classes/expaihelp.php' );
        $this->assertFileDoesNotExist( $root . '/design/admin/templates/expai/help.tpl' );
        $this->assertStringContainsString( "exphelp/viewer/ext/expai/", (string) file_get_contents( $root . '/design/admin/templates/expai/parts/help_link.tpl' ) );
        $this->assertStringContainsString( 'expAiHelpLinks::url(', (string) file_get_contents( $root . '/modules/expai/help.php' ) );
    }
}
