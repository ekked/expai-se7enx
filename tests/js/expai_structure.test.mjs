// The structure detector of the edit form (design/admin/javascript/expai_structure.js), without a browser:
//   node --test extension/expai/tests/js/expai_structure.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const S = require( '../../design/admin/javascript/expai_structure.js' );

const none = { embed: 0, table: 0, custom: 0, anchor: 0, literal: 0, link: 0, attribute: 0 };
const counts = ( over ) => Object.assign( {}, none, over );

test( 'plain answer markup has no structure', () => {
    assert.deepEqual( S.count( '<p>One <strong>two</strong> <a href="https://example.com/">three</a></p><ul><li>x</li></ul><h2>Head</h2>' ), none );
    assert.deepEqual( S.count( '' ), none );
    assert.deepEqual( S.count( null ), none );
    assert.deepEqual( S.count( 'just text, 1 < 2' ), none );
} );

test( 'Online Editor and Tiptap embeds count once, their previews not at all', () => {
    const html = '<p><img id="eZObject_57" src="/var/a.jpg" alt="medium" class="ezoeItemNonEditable" /></p>'
        + '<div id="eZObject_12" class="ezoeItemNonEditable ezoeItemContentTypeObjects" inline="false">'
        + '<div class="preview"><a href="/node/5">Teaser</a><img src="/x.png"><table><tr><td>t</td></tr></table></div></div>'
        + '<p>Text <span id="eZNode_9" inline="true" class="ezoeItemNonEditable">inline <span>preview</span></span> more</p>';
    assert.deepEqual( S.count( html ), counts( { embed: 3 } ) );
} );

test( 'tables, custom tags, anchors, literals and internal links', () => {
    const html = '<table border="1"><tbody><tr><td>a</td></tr></tbody></table>'
        + '<div class="ezoeItemCustomTag factbox" type="custom"><p>box</p></div>'
        + '<p><span class="ezoeItemCustomTag highlight" type="custom">hi</span> x<sup>2</sup></p>'
        + '<img src="/icon.png" class="ezoeItemCustomTag youtube" type="custom" />'
        + '<h2><a name="top" class="mceItemAnchor"></a>Top</h2><p><a id="tinymce8"></a></p>'
        + '<pre>code<br>line</pre>'
        + '<p><a href="ezobject://12">obj</a> <a href="eznode://3#x">node</a> <a href="/about">path</a>'
        + ' <a href="#top">jump</a> <a href="mailto:a@b.c">mail</a></p>';
    assert.deepEqual( S.count( html ), counts( { table: 1, custom: 4, anchor: 2, literal: 1, link: 3 } ) );
} );

test( 'formatting attributes on blocks, without the editors\' own classes', () => {
    const html = '<p class="lead">a</p><p align="center">b</p><h3 customattributes="x|1">c</h3>'
        + '<p class="mceItemHidden ezoeAlignleft">d</p><ul class="checks"><li>e</li></ul><p style="color:red">f</p>';
    assert.deepEqual( S.count( html ), counts( { attribute: 4 } ) );
} );

test( 'the simplified XML of a textarea without an editor', () => {
    const xml = '<section><header level="1" anchor_name="intro">Intro</header>'
        + '<paragraph class="lead">Text <link href="ezobject://4">obj</link> <link href="https://x.org/">ext</link>'
        + '<embed href="ezobject://7" size="medium" /><embed-inline href="eznode://8"/></paragraph>'
        + '<paragraph><anchor name="a1"/><custom name="factbox">f</custom><literal class="html">&lt;b&gt;</literal></paragraph>'
        + '<paragraph><table><tr><td>x</td></tr></table></paragraph></section>';
    assert.deepEqual( S.count( xml ), counts( { embed: 2, table: 1, custom: 1, anchor: 2, literal: 1, link: 1, attribute: 1 } ) );
} );

test( 'data-ezelement markup', () => {
    assert.deepEqual( S.count( '<div data-ezelement="ezembed" data-href="ezcontent://1"><p>preview<img src="x"></p></div>'
        + '<div data-ezelement="eztemplate" data-eztype="custom"><p>t</p></div>' ), counts( { embed: 1, custom: 1 } ) );
} );

test( 'loss lists the kinds the answer has fewer of', () => {
    const current = '<p><img id="eZObject_1" src="a" /></p><p><img id="eZObject_2" src="b" /></p><table><tr><td>x</td></tr></table><p>t</p>';
    assert.deepEqual( S.loss( current, '<p>Shorter text.</p>' ), [ { kind: 'embed', lost: 2 }, { kind: 'table', lost: 1 } ] );
    assert.deepEqual( S.loss( current, current + '<p>more</p>' ), [] );
    assert.deepEqual( S.loss( '<p>a</p>', '<p>b</p>' ), [] );
    // a new element does not make up for another kind
    assert.deepEqual( S.loss( '<table><tr><td>x</td></tr></table>', '<pre>x</pre>' ), [ { kind: 'table', lost: 1 } ] );
} );

test( 'describe uses singular and plural strings', () => {
    const strings = { lost_embed_1: '%count embedded object', lost_embed_n: '%count embedded objects', lost_table_1: '%count table', lost_table_n: '%count tables' };
    assert.equal( S.describe( [ { kind: 'embed', lost: 2 }, { kind: 'table', lost: 1 } ], strings ), '2 embedded objects, 1 table' );
    assert.equal( S.describe( [ { kind: 'anchor', lost: 3 } ], strings ), '3 anchor' );
    assert.equal( S.describe( [], strings ), '' );
} );

test( 'toText keeps paragraphs and list items and drops markup', () => {
    assert.equal( S.toText( '<p>One &amp; <strong>two</strong></p><ul><li>a</li><li>b</li></ul><p>x<br>y</p><script>alert(1)</script>' ),
                  'One & two\n\n- a\n- b\n\nx\ny' );
    assert.equal( S.toText( 'plain &lt;b&gt;' ), 'plain <b>' );
    assert.equal( S.toText( '<p>&#169; &#x263A;</p>' ), '© ☺' );
} );

test( 'an unclosed comment hides the rest, a closed one only itself', () => {
    assert.deepEqual( S.count( '<!-- <table> --><table></table>' ), counts( { table: 1 } ) );
    assert.deepEqual( S.count( '<table></table><!-- <table>' ), counts( { table: 1 } ) );
} );

test( 'same ignores white space between tags', () => {
    assert.ok( S.same( '<p>a</p>\n<p>b</p>', '<p>a</p><p>b</p>' ) );
    assert.ok( S.same( ' text ', 'text' ) );
    assert.ok( !S.same( '<p>a</p>', '<p>b</p>' ) );
} );

test( 'malformed markup does not hang the scanner', () => {
    const big = '<p class="a ' + 'x="y" '.repeat( 20000 ) + '<a href="' + '<'.repeat( 20000 );
    const start = Date.now();
    S.count( big );
    S.count( '<p '.repeat( 50000 ) );
    S.count( '<!--'.repeat( 50000 ) );
    S.count( '<p a="'.repeat( 50000 ) );
    assert.ok( Date.now() - start < 2000 );
} );
