<?php
/**
 * The template operator expai_json: JSON for <script type="application/json"> blocks. No database.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

require_once __DIR__ . '/expaicoretestcase.php';
require_once EXPAI_TEST_CHECKOUT . '/autoloads/expaitemplateoperators.php';

class expAiTemplateOperatorsTest extends expAiCoreTestCase
{
    public function testTheOperatorIsRegistered(): void
    {
        $operators = new expAiTemplateOperators();
        $this->assertContains( 'expai_json', $operators->operatorList() );
        $this->assertArrayHasKey( 'expai_json', $operators->namedParameterList() );
    }

    public function testMarkupInAValueCannotEndTheScriptElement(): void
    {
        $json = expAiTemplateOperators::json( array( 'label' => '</script><script>alert("x")</script> & <!-- \'q\'' ) );
        foreach ( array( '<', '>', '&', "'" ) as $char )
            $this->assertStringNotContainsString( $char, $json, "'$char' is escaped" );
        $decoded = json_decode( $json, true );
        $this->assertSame( '</script><script>alert("x")</script> & <!-- \'q\'', $decoded['label'], 'the value survives the round trip' );
    }

    public function testBrokenUtf8IsSubstitutedNotDropped(): void
    {
        $json = expAiTemplateOperators::json( array( 'a' => "ok\xC3(", 'b' => 'fine' ) );
        $decoded = json_decode( $json, true );
        $this->assertIsArray( $decoded, 'one broken string does not empty the whole block' );
        $this->assertSame( 'fine', $decoded['b'] );
        $this->assertStringContainsString( "\u{FFFD}", $decoded['a'] );
    }

    public function testAnUnencodableValueGivesNull(): void
    {
        $this->assertSame( 'null', expAiTemplateOperators::json( NAN ) );
    }

    public function testModifyWritesTheJson(): void
    {
        $operators = new expAiTemplateOperators();
        $value = array( 'n' => 1 );
        $operators->modify( null, 'expai_json', array(), '', '', $value, array() );
        $this->assertSame( '{"n":1}', $value );
    }

    public function testAnAssetVersionIsTheChangeTimeOfTheFile(): void
    {
        $file = EXPAI_TEST_CHECKOUT . '/design/admin/javascript/expai.js';
        $this->assertSame( (string) filemtime( $file ), expAiTemplateOperators::assetVersion( 'javascript/expai.js' ) );
        $this->assertSame( (string) filemtime( $file ), expAiTemplateOperators::assetVersion( '/javascript/expai.js' ) );
    }

    public function testAnUnknownAssetFallsBackToTheExtensionVersion(): void
    {
        $this->assertMatchesRegularExpression( '/^\d+(\.\d+)+$/', expAiTemplateOperators::assetVersion( 'javascript/none.js' ) );
        $this->assertMatchesRegularExpression( '/^\d+(\.\d+)+$/', expAiTemplateOperators::assetVersion( '../extension.xml' ), 'no path outside design/admin' );
    }

    public function testOnlyUnknownIsTranslatedInARetention(): void
    {
        foreach ( array( 'none', 'zdr', '30d', '' ) as $retention )
            $this->assertSame( $retention, expAiTemplateOperators::retention( $retention ) );
        $this->assertNotSame( '', expAiTemplateOperators::retention( 'Unknown' ) );
    }
}
