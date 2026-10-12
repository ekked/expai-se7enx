<?php
/**
 * Test of the help pages (expAiHelp, the view expai/help), no network, no database:
 *   - every document of the extension is a help page and renders;
 *   - the rendered HTML holds only the renderer's own tags and attributes: no markup of a document gets through
 *     (a hostile document with script, event handler, javascript:/data: links, iframes, entities and broken markup);
 *   - links: to other help pages (with anchors), anchors on the page, http(s) and mailto; relative links that leave
 *     the extension or name no document are text; every link between the documents resolves to an existing page
 *     and every #anchor of such a link to a heading of its page;
 *   - the Markdown subset: headings with ids, lists (nested), tables (code with |), fenced and indented code.
 *
 * Run from the installation root:
 *   php bin/php/ezexec.php extension/expai/tests/expaihelp_test.php -s admin
 * It prints one line per check and ends with PASS or FAIL (exit code 0 or 1).
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

$failures = 0;
$checks = 0;
$check = function ( $label, $ok, $detail = '' ) use ( &$failures, &$checks )
{
    ++$checks;
    if ( !$ok )
        ++$failures;
    echo ( $ok ? 'ok    ' : 'FAIL  ' ) . $label . ( $detail !== '' ? ' => ' . $detail : '' ) . "\n";
};

$allowedTags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul', 'ol', 'li', 'pre', 'code', 'strong', 'em', 'a',
                      'table', 'thead', 'tbody', 'tr', 'th', 'td', 'div', 'blockquote', 'hr' );
$allowedAttributes = array( 'href', 'id', 'class', 'rel', 'scope' );
/** @return array problems: tags or attributes the renderer never writes, or dangerous values */
$audit = function ( $html ) use ( $allowedTags, $allowedAttributes )
{
    $problems = array();
    preg_match_all( '#<\s*(/?)\s*([a-zA-Z][a-zA-Z0-9]*)([^>]*)>#', $html, $tags, PREG_SET_ORDER );
    foreach ( $tags as $tag )
    {
        $name = strtolower( $tag[2] );
        if ( !in_array( $name, $allowedTags, true ) )
            $problems[] = 'tag ' . $name;
        preg_match_all( '#\s([a-zA-Z_:-]+)\s*=\s*"([^"]*)"#', $tag[3], $attributes, PREG_SET_ORDER );
        $rest = preg_replace( '#\s([a-zA-Z_:-]+)\s*=\s*"([^"]*)"#', '', $tag[3] );
        if ( trim( $rest, " /" ) !== '' )
            $problems[] = 'stray text in <' . $name . '>: ' . trim( $rest );
        foreach ( $attributes as $attribute )
        {
            if ( !in_array( strtolower( $attribute[1] ), $allowedAttributes, true ) )
                $problems[] = 'attribute ' . $attribute[1];
            $value = html_entity_decode( $attribute[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            if ( strtolower( $attribute[1] ) === 'href' && !preg_match( '#^(https?://|mailto:|/|\#)#i', $value ) )
                $problems[] = 'href ' . $value;
        }
    }
    if ( preg_match( '#<\s*(script|iframe|object|embed|style|svg|img|form|input|meta|link)\b#i', $html ) )
        $problems[] = 'dangerous element';
    if ( preg_match( '#\son[a-z]+\s*=#i', preg_replace( '#>[^<]*<#', '><', $html ) ) )
        $problems[] = 'event handler attribute';
    return $problems;
};

// a fixed address builder, so the test does not depend on the siteaccess's URL settings
$help = new expAiHelp( null, function ( $name, $anchor ) { return '/admin/expai/help/(page)/' . $name . ( $anchor !== '' ? '#' . $anchor : '' ); } );
$pages = $help->pages();
$check( 'pages: readme, install, usage, index first', array_slice( array_keys( $pages ), 0, 4 ) === array( 'readme', 'install', 'usage', 'index' ), implode( ',', array_slice( array_keys( $pages ), 0, 4 ) ) );
$docCount = count( glob( 'extension/expai/doc/*.md' ) ) + count( glob( 'extension/expai/doc/changelog/*.md' ) ) + 3;
$check( 'every document is a page', count( $pages ) === $docCount, count( $pages ) . ' of ' . $docCount );
$check( 'the changelog is a page', isset( $pages['changelog-0.1.0'] ) );
$check( 'unknown names are no page', !$help->exists( '../settings/expai.ini' ) && !$help->exists( 'LICENSE' ) && !$help->exists( 'examples' ) && $help->page( 'nope' ) === null );

// every page renders, with only the renderer's markup, and its links resolve
$rendered = array();
foreach ( $pages as $name => $info )
{
    $page = $help->page( $name );
    $rendered[$name] = $page['html'];
    $problems = $audit( $page['html'] );
    $check( "page $name renders safely", $page['html'] !== '' && !$problems, implode( '; ', array_slice( $problems, 0, 3 ) ) );
}
$broken = array();
$linksSeen = 0;
foreach ( $rendered as $name => $html )
{
    preg_match_all( '#href="/admin/expai/help/\(page\)/([a-z0-9.-]+)(?:\#([A-Za-z0-9_-]+))?"#', $html, $links, PREG_SET_ORDER );
    foreach ( $links as $link )
    {
        ++$linksSeen;
        if ( !isset( $rendered[$link[1]] ) )
            $broken[] = "$name -> {$link[1]}";
        else if ( isset( $link[2] ) && $link[2] !== '' && strpos( $rendered[$link[1]], 'id="' . $link[2] . '"' ) === false )
            $broken[] = "$name -> {$link[1]}#{$link[2]}";
    }
    preg_match_all( '#href="\#([A-Za-z0-9_-]+)"#', $html, $anchors );
    foreach ( $anchors[1] as $anchor )
        if ( strpos( $html, 'id="' . $anchor . '"' ) === false )
            $broken[] = "$name -> #$anchor";
}
$check( "links between the documents resolve ($linksSeen links)", $linksSeen > 50 && !$broken, implode( ', ', $broken ) );

// relative links in the sources that the renderer could not resolve (a renamed or missing document)
$unresolved = array();
foreach ( $pages as $name => $info )
{
    $source = file_get_contents( 'extension/expai/' . $info['file'] );
    $source = preg_replace( '/```.*?```/s', '', $source );
    $source = preg_replace( '/`[^`\n]*`/', '', $source );
    preg_match_all( '/\]\(([^)\s]+\.md(?:#[^)\s]*)?)\)/', $source, $m );
    foreach ( $m[1] as $target )
        if ( !preg_match( '#^https?://#', $target ) && $help->render( '[x](' . $target . ')', $info['file'] ) === "<p>x</p>\n" )
            $unresolved[] = $name . ': ' . $target;
}
$check( 'every relative .md link of the documents names a help page', !$unresolved, implode( ', ', $unresolved ) );

// a hostile document
$hostile = <<<'MD'
# Title <script>alert(1)</script>

<script>alert("x")</script> <img src=x onerror=alert(1)> <iframe src="https://example.org"></iframe>

[click](javascript:alert(1)) [data](data:text/html;base64,PHNjcmlwdD4=) [entity](&#106;avascript:alert(1))
[quote](https://example.org/"onmouseover="alert(1)) [ok](https://example.org/a?b=1&c=2) [mail](mailto:someone@example.org)
[up](../../settings/override/site.ini.append.php) [file](../LICENSE) [doc](providers.md#registry) [here](#title-scriptalert1script)
**<b>bold</b>** *<i>it</i>* `<svg onload=alert(1)>` <a href="javascript:alert(1)">a</a> &lt;already&gt; &amp;

- item <style>body{}</style>
  - nested "quote" 'single'

| a | b |
| --- | --- |
| `x | y` | <svg/onload=alert(1)> |

```html
<script>alert(2)</script>
```

    <?php system( $_GET['x'] ); ?>

> <object data="x"></object>
MD;
$html = $help->render( $hostile, 'doc/index.md' );
$problems = $audit( $html );
$check( 'hostile document: only the renderer\'s markup', !$problems, implode( '; ', $problems ) );
$check( 'hostile document: script text is shown, escaped', strpos( $html, '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;' ) !== false );
$check( 'hostile document: no javascript:/data: link', !preg_match( '#href="[^"]*(javascript|data):#i', $html ) );
$check( 'hostile document: an entity-encoded javascript: link is text', strpos( $html, '>entity<' ) === false && strpos( $html, 'entity' ) !== false && !preg_match( '#href="&\#106;#', $html ) );
$check( 'hostile document: a quote in a URL cannot leave the attribute', !preg_match( '#href="[^"]*"\s*onmouseover#i', $html ) && strpos( $html, 'onmouseover="alert' ) === false );
$check( 'hostile document: & in a URL is escaped', strpos( $html, 'href="https://example.org/a?b=1&amp;c=2"' ) !== false );
$check( 'hostile document: mailto link', strpos( $html, 'href="mailto:someone@example.org"' ) !== false );
$check( 'hostile document: a path leaving the extension is text', strpos( $html, 'site.ini' ) === false && strpos( $html, '>up<' ) === false && strpos( $html, 'up' ) !== false );
$check( 'hostile document: a link to a file that is no document is text', strpos( $html, 'LICENSE' ) === false );
$check( 'hostile document: a link to a document with anchor', strpos( $html, 'href="/admin/expai/help/(page)/providers#registry"' ) !== false );
$check( 'hostile document: same-page anchor', strpos( $html, 'href="#title-scriptalert1script"' ) !== false && strpos( $html, 'id="title-scriptalert1script"' ) !== false );
$check( 'hostile document: emphasis around markup keeps it escaped', strpos( $html, '<strong>&lt;b&gt;bold&lt;/b&gt;</strong>' ) !== false && strpos( $html, '<em>&lt;i&gt;it&lt;/i&gt;</em>' ) !== false );
$check( 'hostile document: a code span is escaped', strpos( $html, '<code>&lt;svg onload=alert(1)&gt;</code>' ) !== false );
$check( 'hostile document: entities are shown as written', strpos( $html, '&amp;lt;already&amp;gt; &amp;amp;' ) !== false );
$check( 'hostile document: a | inside code stays in its cell', strpos( $html, '<td><code>x | y</code></td>' ) !== false );
$check( 'hostile document: fenced and indented code are escaped', strpos( $html, '&lt;script&gt;alert(2)&lt;/script&gt;' ) !== false && strpos( $html, '&lt;?php system( $_GET[&apos;x&apos;] ); ?&gt;' ) !== false );
$check( 'hostile document: nested list', preg_match( '#<li>item &lt;style&gt;body\{\}&lt;/style&gt;\s*<ul>\s*<li>nested &quot;quote&quot; &apos;single&apos;</li>#', $html ) === 1 );
$check( 'hostile document: block quote is escaped', strpos( $html, '<blockquote><p>&lt;object data=&quot;x&quot;&gt;&lt;/object&gt;</p>' ) !== false );

// the subset on a real page
$config = isset( $rendered['configuration'] ) ? $rendered['configuration'] : '';
$check( 'configuration: tables and anchored headings', substr_count( $config, '<table' ) > 20 && strpos( $config, 'id="providersettings-the-providers"' ) !== false );
$lanes = isset( $rendered['lanes'] ) ? $rendered['lanes'] : '';
$check( 'lanes: a nested list', preg_match( '#<ol>.*<li>.*<ul>#s', $lanes ) === 1 );
$check( 'permissions: indented code', isset( $rendered['permissions'] ) && strpos( $rendered['permissions'], '<pre class="exp-ai-help-code"><code>module expai, function vector' ) !== false );

echo ( $failures ? 'FAIL' : 'PASS' ) . " $checks checks, $failures failure(s)\n";
eZScript::instance()->shutdown( $failures ? 1 : 0 );
