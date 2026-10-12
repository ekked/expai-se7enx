<?php
/**
 * expAiHelp: the Markdown subset of the help pages (rules, heading ids, links that stay inside the extension, the
 * page address). No database.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

require_once __DIR__ . '/expaicoretestcase.php';

class expAiHelpTest extends expAiT386DCoreTestCase
{
    public function testRulesAndHeadingIds(): void
    {
        $help = new expAiHelp( null, function ( $name, $anchor ) { return "help:$name" . ( $anchor !== '' ? "#$anchor" : '' ); } );
        $html = $help->render( "# Same\n\n---\n\n## Same\n\n## `!!!`\n\n* * *\n" );
        $this->assertSame( 2, substr_count( $html, '<hr />' ) );
        $this->assertStringContainsString( 'id="same"', $html );
        $this->assertStringContainsString( 'id="same-1"', $html, 'a second heading of the same text gets a number' );
        $this->assertStringContainsString( 'id="section"', $html, 'a heading of signs only' );
    }

    public function testLinks(): void
    {
        $help = new expAiHelp( null, function ( $name, $anchor ) { return "help:$name" . ( $anchor !== '' ? "#$anchor" : '' ); } );
        $help->render( 'x', 'doc/index.md' );
        $this->assertNull( $help->href( '' ) );
        $this->assertNull( $help->href( "with space" ) );
        $this->assertSame( array( 'url' => '#top', 'external' => false ), $help->href( '#top' ) );
        $this->assertNull( $help->href( '#bad anchor!' ) );
        $this->assertSame( array( 'url' => 'https://example.invalid/', 'external' => true ), $help->href( 'https://example.invalid/' ) );
        $this->assertNull( $help->href( 'javascript:alert(1)' ) );
        $this->assertNull( $help->href( '//example.invalid/x' ) );
        $this->assertNull( $help->href( '../../../etc/passwd' ), 'a path that leaves the extension' );
        $this->assertNull( $help->href( 'chains.md#bad!' ) );
        $this->assertNull( $help->href( 'no-such-page.md' ) );
        $this->assertSame( array( 'url' => 'help:chains#permissions', 'external' => false ), $help->href( 'chains.md#permissions' ) );
        $this->assertSame( array( 'url' => 'help:chains', 'external' => false ), $help->href( './chains.md' ) );

        $plain = new expAiHelp();
        $url = $plain->url( 'chains', 'top' );
        $this->assertStringEndsWith( 'expai/help/(page)/chains#top', $url );
        $this->assertStringEndsWith( 'expai/help/(page)/chains', $plain->url( 'chains' ) );
    }
}
