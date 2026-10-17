// The structure detector of the edit form (design/admin/javascript/expai_structure.js), without a browser:
//   node --test extension/expai/tests/js/expai_structure.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const S = require( '../../design/admin/javascript/expai_structure.js' );

const none = { embed: 0, table: 0, cell: 0, th: 0, merge: 0, custom: 0, anchor: 0, literal: 0, link: 0, otherlink: 0, heading: 0, attribute: 0 };
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
    assert.deepEqual( S.count( html ), counts( { table: 1, cell: 1, custom: 4, anchor: 2, literal: 1, link: 3, otherlink: 1, attribute: 1 } ) );
} );

test( 'formatting attributes on blocks, without the editors\' own classes', () => {
    const html = '<p class="lead">a</p><p align="center">b</p><h3 customattributes="x|1">c</h3>'
        + '<p class="mceItemHidden ezoeAlignleft">d</p><ul class="checks"><li>e</li></ul><p style="color:red">f</p>';
    // the style attribute is formatting the answer does not keep
    assert.deepEqual( S.count( html ), counts( { attribute: 5 } ) );
} );

test( 'the simplified XML of a textarea without an editor', () => {
    const xml = '<section><header level="1" anchor_name="intro">Intro</header>'
        + '<paragraph class="lead">Text <link href="ezobject://4">obj</link> <link href="https://x.org/">ext</link>'
        + '<embed href="ezobject://7" size="medium" /><embed-inline href="eznode://8"/></paragraph>'
        + '<paragraph><anchor name="a1"/><custom name="factbox">f</custom><literal class="html">&lt;b&gt;</literal></paragraph>'
        + '<paragraph><table><tr><td>x</td></tr></table></paragraph></section>';
    assert.deepEqual( S.count( xml ), counts( { embed: 2, table: 1, cell: 1, custom: 1, anchor: 2, literal: 1, link: 1, attribute: 2 } ) );
} );

test( 'data-ezelement markup', () => {
    assert.deepEqual( S.count( '<div data-ezelement="ezembed" data-href="ezcontent://1"><p>preview<img src="x"></p></div>'
        + '<div data-ezelement="eztemplate" data-eztype="custom"><p>t</p></div>' ), counts( { embed: 1, custom: 1 } ) );
} );

test( 'loss lists the kinds the answer has fewer of', () => {
    const current = '<p><img id="eZObject_1" src="a" /></p><p><img id="eZObject_2" src="b" /></p><table><tr><td>x</td></tr></table><p>t</p>';
    assert.deepEqual( S.loss( current, '<p>Shorter text.</p>' ), [ { kind: 'embed', lost: 2 }, { kind: 'table', lost: 1 }, { kind: 'cell', lost: 1 } ] );
    assert.deepEqual( S.loss( current, current + '<p>more</p>' ), [] );
    assert.deepEqual( S.loss( '<p>a</p>', '<p>b</p>' ), [] );
    // a new element does not make up for another kind
    assert.deepEqual( S.loss( '<table><tr><td>x</td></tr></table>', '<pre>x</pre>' ), [ { kind: 'table', lost: 1 }, { kind: 'cell', lost: 1 } ] );
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
    // plain text stays as it is, also with < and > in it
    assert.equal( S.toText( '3 < 5 und 7 > 2' ), '3 < 5 und 7 > 2' );
    assert.equal( S.toText( 'plain &lt;b&gt;' ), 'plain &lt;b&gt;' );
    assert.equal( S.toText( '<p>3 < 5 und 7 > 2</p>' ), '3 < 5 und 7 > 2' );
    assert.equal( S.toText( '<p>&#169; &#x263A;</p>' ), '© ☺' );
} );

test( 'the layout of a table: cells, header cells, merged cells, table and cell attributes', () => {
    const table = '<table border="1" width="100%" class="list"><tbody>'
        + '<tr><th>A</th><th colspan="2">B</th></tr>'
        + '<tr><td rowspan="2" align="right">1</td><td>2</td><td>3</td></tr>'
        + '<tr><td colspan="1" rowspan="1">4</td><td>5</td></tr></tbody></table>';
    assert.deepEqual( S.count( table ), counts( { table: 1, cell: 7, th: 2, merge: 2, attribute: 4 } ) );
    // the same table with the merged cells undone, the header cells turned into data cells and no attributes
    const flat = '<table><tbody><tr><td>A</td><td>B</td><td></td></tr><tr><td>1</td><td>2</td><td>3</td></tr>'
        + '<tr><td></td><td>4</td><td>5</td></tr></tbody></table>';
    assert.deepEqual( S.loss( table, flat ), [ { kind: 'th', lost: 2 }, { kind: 'merge', lost: 2 }, { kind: 'attribute', lost: 4 } ] );
    // a row less
    assert.deepEqual( S.loss( table, table.replace( /<tr><td colspan[\s\S]*?<\/tr>/, '' ) ), [ { kind: 'cell', lost: 2 } ] );
} );

test( 'link attributes count, also on external links and anchors', () => {
    const html = '<p><a href="https://example.com/" target="_blank" title="Example">x</a>'
        + ' <a href="mailto:a@b.c" class="mail" id="m1">y</a> <a href="ezobject://4" view="full" customattributes="k|v">z</a>'
        + ' <a name="top" class="jump"></a><link href="https://x.org/" target="_self">w</link></p>';
    assert.deepEqual( S.count( html ), counts( { link: 1, anchor: 1, attribute: 8 } ) );
    assert.deepEqual( S.loss( html, '<p><a href="https://example.com/">x</a> <a href="mailto:a@b.c">y</a> z <a name="top"></a> w</p>' ),
                      [ { kind: 'link', lost: 1 }, { kind: 'attribute', lost: 8 } ] );
} );

test( 'CDATA and the content of script, style, literal and pre are no markup', () => {
    assert.deepEqual( S.count( '<literal><![CDATA[<table><tr><td>x</td></tr></table>]]></literal>' ), counts( { literal: 1 } ) );
    assert.deepEqual( S.count( '<![CDATA[<table>]]><p>a</p>' ), none );
    assert.deepEqual( S.count( '<script>var t = "<table><img src=x>";</script><style>a:before{content:"<table>"}</style>' ), none );
    assert.deepEqual( S.count( '<pre><span>a</span><br><table></pre><table></table>' ), counts( { literal: 1, table: 1 } ) );
    assert.deepEqual( S.count( '<script>"<table>"' ), none );
} );

test( 'links with an address of their own are counted apart from internal ones', () => {
    const html = '<p><a href="tel:+491234">t</a> <a href="www.example.de">w</a> <a href="ftp://x.org/f">f</a>'
        + ' <a href="HTTPS://EXAMPLE.COM/">h</a> <a href="//cdn.example.com/x">c</a> <a href="#top">j</a> <link url_id="5">u</link>'
        + ' <a href="mailto:a@b.c">m</a> <a>no href</a></p>';
    // http(s) and mailto survive the server's cleaning; tel, www., ftp, //host and #fragment do not
    assert.deepEqual( S.count( html ), counts( { otherlink: 5 } ) );
    assert.deepEqual( S.count( '<a href="/about">a</a><a href="eznode://2">b</a><link node_id="3">c</link>' ), counts( { link: 3 } ) );
    assert.deepEqual( S.loss( html, '<p>t w f <a href="https://example.com/">h</a> c j u m</p>' ), [ { kind: 'otherlink', lost: 5 } ] );
} );

test( 'headings the answer changes (h1, h5, h6) and the style attribute are losses', () => {
    const html = '<h1>a</h1><h2>b</h2><h5>c</h5><h6>d</h6><p style="text-align:center">e</p>';
    assert.deepEqual( S.count( html ), counts( { heading: 3, attribute: 1 } ) );
    assert.deepEqual( S.loss( html, '<h2>a</h2><h2>b</h2><h4>c</h4><h4>d</h4><p>e</p>' ),
                      [ { kind: 'heading', lost: 3 }, { kind: 'attribute', lost: 1 } ] );
    assert.deepEqual( S.loss( '<h2>a</h2><h3>b</h3><h4>c</h4>', '<h2>a</h2>' ), [] );
} );

test( 'the strings of every kind are named lost_<kind>_1 and lost_<kind>_n', () => {
    assert.equal( S.describe( [ { kind: 'otherlink', lost: 1 }, { kind: 'heading', lost: 2 } ],
                              { lost_otherlink_1: '%count link', lost_heading_n: '%count headings' } ), '1 link, 2 headings' );
} );

test( 'an unclosed embed or literal ends at the next block or at the end around it', () => {
    assert.deepEqual( S.count( '<div id="eZObject_1" class="ezoeItemNonEditable"><span>preview</span><table><tr><td>x</td></tr></table>' ),
                      counts( { embed: 1, table: 1, cell: 1 } ) );
    assert.deepEqual( S.count( '<p><iframe src="https://v.example/"><a href="/x">x</a></p><h2 class="t">T</h2>' ), counts( { embed: 1, attribute: 1 } ) );
    assert.deepEqual( S.count( '<section><pre>code</section><table></table>' ), counts( { literal: 1, table: 1 } ) );
    // a closed embed keeps its preview out, blocks in it too
    assert.deepEqual( S.count( '<div id="eZObject_1"><div><table></table></div></div><p>a</p>' ), counts( { embed: 1 } ) );
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
    S.count( '<![CDATA['.repeat( 50000 ) );
    S.count( '<script>'.repeat( 50000 ) );
    S.count( '<div id="eZObject_1">'.repeat( 50000 ) );
    S.count( '<pre><span>'.repeat( 50000 ) );
    assert.ok( Date.now() - start < 2000 );
} );

test( 'toText takes a long answer full of unclosed tags apart in linear time', () => {
    const start = Date.now();
    S.toText( '<p>x</p>' + '<style '.repeat( 50000 ) );
    S.toText( '<p>x</p>' + '<a '.repeat( 100000 ) );
    S.toText( '<p>x</p>' + '<script>'.repeat( 50000 ) );
    assert.ok( Date.now() - start < 2000 );
    // an unclosed style element is left to the tag rule, a closed one goes with its content
    assert.equal( S.toText( '<p>a</p><style>b{}</style><p>c</p><style>d' ), 'a\n\nc\n\nd' );
    assert.equal( S.toText( '<p>a</p><SCRIPT type="x">1</SCRIPT ><p>b</p>' ), 'a\n\nb' );
} );
