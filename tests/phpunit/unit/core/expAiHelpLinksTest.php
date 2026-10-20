<?php
/**
 * expAiHelpLinks: the extension's documentation is in the help center (exphelp, Exponential 6.0.15 and later) or, on a
 * kernel without it (6.0.14), on GitHub in the tree of the release; the page names of expai/help/(page)/<name> become
 * the page ids there. The links lead straight to the help center where it is read there, otherwise to expai/help, which
 * redirects to GitHub. Reads files and needs no database.
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

    protected function tearDown(): void
    {
        expAiHelpLinks::setHelpCenter( null );
    }

    public function testGitHubAddressesArePinnedToTheRelease(): void
    {
        $base = 'https://github.com/se7enxweb/expai/blob/' . expAiHelpLinks::GITHUB_REF . '/';
        $this->assertSame( $base . 'doc/index.md', expAiHelpLinks::githubUrl() );
        $this->assertSame( $base . 'doc/index.md', expAiHelpLinks::githubUrl( '../x' ) );
        $this->assertSame( $base . 'doc/chains.md', expAiHelpLinks::githubUrl( 'chains' ) );
        $this->assertSame( $base . 'README.md', expAiHelpLinks::githubUrl( 'readme' ) );
        $this->assertSame( $base . 'doc/changelog/0.0.1.0.md', expAiHelpLinks::githubUrl( 'changelog-0.0.1.0' ) );
        // the tag is the release's: ezinfo.php and extension.xml carry the same version
        if ( !class_exists( 'expaiInfo', false ) )
            require_once self::extension() . '/ezinfo.php';
        $info = expaiInfo::info();
        $this->assertSame( 'v' . $info['Version'], expAiHelpLinks::GITHUB_REF );
        $xml = simplexml_load_file( self::extension() . '/extension.xml' );
        $this->assertSame( $info['Version'], (string) $xml->metadata->version );
    }

    public function testTargetFollowsTheKernel(): void
    {
        expAiHelpLinks::setHelpCenter( true );
        $this->assertSame( '/exphelp/extensions/expai', expAiHelpLinks::target() );
        $this->assertSame( '/exphelp/viewer/ext/expai/vector.md', expAiHelpLinks::target( 'vector' ) );
        $this->assertSame( '/exphelp/viewer/ext/expai/README.md', expAiHelpLinks::target( 'readme' ) );
        // a page the extension does not have leads to the index, not to a "not found"
        $this->assertSame( '/exphelp/extensions/expai', expAiHelpLinks::target( 'no-such-page' ) );
        expAiHelpLinks::setHelpCenter( false );
        $base = 'https://github.com/se7enxweb/expai/blob/' . expAiHelpLinks::GITHUB_REF . '/';
        $this->assertSame( $base . 'doc/index.md', expAiHelpLinks::target() );
        $this->assertSame( $base . 'doc/vector.md', expAiHelpLinks::target( 'vector' ) );
        $this->assertSame( $base . 'doc/index.md', expAiHelpLinks::target( 'no-such-page' ) );
        $this->assertSame( $base . 'doc/index.md', expAiHelpLinks::target( 'javascript:alert(1)' ) );
    }

    public function testLinksLeadStraightToTheHelpCenter(): void
    {
        expAiHelpLinks::setHelpCenter( true );
        $this->assertSame( 'exphelp/extensions/expai', expAiHelpLinks::link() );
        $this->assertSame( 'exphelp/viewer/ext/expai/vector.md', expAiHelpLinks::link( 'vector' ) );
        $this->assertSame( 'exphelp/viewer/ext/expai/dashboard.md', expAiHelpLinks::link( 'dashboard' ) );
        $this->assertSame( 'exphelp/viewer/ext/expai/README.md', expAiHelpLinks::link( 'readme' ) );
        $this->assertSame( 'exphelp/extensions/expai', expAiHelpLinks::link( 'no-such-page' ) );
        $this->assertSame( 'exphelp/extensions/expai', expAiHelpLinks::link( 'javascript:alert(1)' ) );
        // the same page the redirect leads to
        foreach ( array( '', 'vector', 'chains', 'alt-text', 'full-view-panel', 'readme' ) as $name )
            $this->assertSame( expAiHelpLinks::target( $name ), '/' . expAiHelpLinks::link( $name ), $name );
        // without the help center (6.0.14, or no exphelp/read) the links keep going to expai/help, which redirects to GitHub
        expAiHelpLinks::setHelpCenter( false );
        $this->assertSame( 'expai/help', expAiHelpLinks::link() );
        $this->assertSame( 'expai/help/(page)/vector', expAiHelpLinks::link( 'vector' ) );
        $this->assertSame( 'expai/help/(page)/readme', expAiHelpLinks::link( 'README' ) );
        $this->assertSame( 'expai/help', expAiHelpLinks::link( 'no-such-page' ) );
        $this->assertSame( 'expai/help', expAiHelpLinks::link( '../etc/passwd' ) );
    }

    /** The check runs against the kernel the suite runs on, whatever it is, and never fails on one without help */
    public function testKernelCheckNeverFails(): void
    {
        $this->assertIsBool( expAiHelpLinks::kernelHasHelpCenter() );
        if ( !class_exists( 'expHelpDocumentIndex' ) )
            $this->assertFalse( expAiHelpLinks::kernelHasHelpCenter() );
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
        // the links are built by expAiHelpLinks::link() (expai_help_url), which decides between the help center and
        // expai/help; no template names a page address itself, and expai/help still redirects for old bookmarks
        $this->assertStringContainsString( 'expai_help_url( $page )|ezurl', (string) file_get_contents( $root . '/design/admin/templates/expai/parts/help_link.tpl' ) );
        $this->assertStringContainsString( 'expai_help_url()|ezurl', (string) file_get_contents( $root . '/design/admin/templates/expai/dashboard.tpl' ) );
        $this->assertStringContainsString( 'expAiHelpLinks::target(', (string) file_get_contents( $root . '/modules/expai/help.php' ) );
        foreach ( glob( $root . '/design/admin/templates/{,*/,*/*/}*.tpl', GLOB_BRACE ) ?: array() as $file )
            $this->assertDoesNotMatchRegularExpression( "#'expai/help#", (string) file_get_contents( $file ), $file );
        foreach ( glob( $root . '/design/admin/templates/{,*/,*/*/}*.tpl', GLOB_BRACE ) ?: array() as $file )
            $this->assertDoesNotMatchRegularExpression( "#'exphelp/#", (string) file_get_contents( $file ), $file );
    }
}
