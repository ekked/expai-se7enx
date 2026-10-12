<?php
/**
 * The help pages of expai/help (doc/index.md, "Help in the administration interface"): the extension's own
 * documentation (README.md, INSTALL.md, USAGE.md, doc/*.md, doc/changelog/*.md), rendered from a Markdown subset to
 * HTML for the view expai/help/(page)/<name>.
 *
 * Safe by construction: every character of a document is HTML-escaped before it reaches the page; the renderer only
 * adds its own tags (headings, paragraphs, lists, tables, code, emphasis, links) and never passes markup of the
 * document through. Links are kept only when they point to another help page (a relative *.md path of a known
 * document, optionally with #anchor), an anchor on the same page, or an http(s) / mailto address; anything else
 * (javascript:, data:, relative paths of other files) is shown as text. Pages are looked up in a fixed list built from
 * the extension's files, so the page name can never name a file outside it.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */
class expAiHelp
{
    /** @var string the extension's directory */
    protected $root;

    /** @var array|null name => array( 'file' => relative path, 'title' => first heading ) */
    protected $pages = null;

    /** @var string the relative path of the page being rendered (to resolve its links) */
    protected $current = '';

    /** @var array heading ids used on the page being rendered */
    protected $ids = array();

    /** @var callable|null builds the address of a help page: function( string $name, string $anchor ): string */
    protected $urlBuilder;

    public function __construct( $root = null, $urlBuilder = null )
    {
        $this->root = $root !== null ? rtrim( $root, '/' ) : dirname( __DIR__ );
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Every help page: README, INSTALL, USAGE, then doc/*.md in the order of doc/index.md (the rest by name), then
     * the changelogs. The name is the file name without .md, lower case (readme, install, usage, providers, ...;
     * changelog-0.0.1.0 for doc/changelog/0.0.1.0.md).
     *
     * @return array name => array( 'file' => ..., 'title' => ... )
     */
    public function pages()
    {
        if ( $this->pages !== null )
            return $this->pages;
        $files = array( 'README.md', 'INSTALL.md', 'USAGE.md', 'doc/index.md' );
        $order = array();
        $index = @file_get_contents( $this->root . '/doc/index.md' );
        if ( is_string( $index ) && preg_match_all( '/\]\(([a-z0-9][a-z0-9._-]*\.md)\)/', $index, $m ) )
            foreach ( $m[1] as $file )
                $order[] = 'doc/' . $file;
        $docs = glob( $this->root . '/doc/*.md' ) ?: array();
        sort( $docs );
        foreach ( $docs as $path )
            $order[] = 'doc/' . basename( $path );
        $logs = glob( $this->root . '/doc/changelog/*.md' ) ?: array();
        usort( $logs, 'strnatcmp' );
        foreach ( array_reverse( $logs ) as $path )
            $order[] = 'doc/changelog/' . basename( $path );
        $this->pages = array();
        foreach ( array_merge( $files, $order ) as $file )
        {
            $name = self::nameOf( $file );
            if ( $name === '' || isset( $this->pages[$name] ) || !is_file( $this->root . '/' . $file ) )
                continue;
            $this->pages[$name] = array( 'file' => $file, 'title' => $this->titleOf( $file ) );
        }
        return $this->pages;
    }

    /** @return string the page name of a relative file path ('' when it is not a help page) */
    public static function nameOf( $file )
    {
        if ( preg_match( '#^doc/changelog/([0-9][0-9a-z.-]*)\.md$#i', $file, $m ) )
            return 'changelog-' . strtolower( $m[1] );
        if ( preg_match( '#^(?:doc/)?([a-z0-9][a-z0-9_-]*)\.md$#i', $file, $m ) )
            return strtolower( $m[1] );
        return '';
    }

    /** @return bool whether the name is a help page */
    public function exists( $name )
    {
        $pages = $this->pages();
        return is_string( $name ) && isset( $pages[$name] );
    }

    /**
     * @param string $name a page name
     * @return array|null array( 'name', 'file', 'title', 'html' ), or null for an unknown page
     */
    public function page( $name )
    {
        if ( !$this->exists( $name ) )
            return null;
        $page = $this->pages[$name];
        $text = (string) @file_get_contents( $this->root . '/' . $page['file'] );
        return array( 'name' => $name, 'file' => $page['file'], 'title' => $page['title'],
                      'html' => $this->render( $text, $page['file'] ) );
    }

    protected function titleOf( $file )
    {
        $handle = @fopen( $this->root . '/' . $file, 'r' );
        $title = '';
        if ( $handle )
        {
            for ( $i = 0; $i < 20 && ( $line = fgets( $handle ) ) !== false; $i++ )
                if ( preg_match( '/^#\s+(.+?)\s*#*\s*$/', $line, $m ) )
                {
                    $title = $m[1];
                    break;
                }
            fclose( $handle );
        }
        $title = trim( str_replace( array( '`', '**' ), '', $title ) );
        return $title !== '' ? $title : self::nameOf( $file );
    }

    /**
     * Renders Markdown text (the subset the documentation uses) as HTML. Every character of the text is escaped.
     *
     * @param string $text
     * @param string $file the relative path of the document (for its relative links)
     * @return string HTML
     */
    public function render( $text, $file = 'doc/index.md' )
    {
        $this->current = $file;
        $this->ids = array();
        $text = str_replace( array( "\r\n", "\r", "\0" ), array( "\n", "\n", '' ), (string) $text );
        // a document is never markup: the comment-like "<?php" heads of setting files and stray tags stay text
        return $this->blocks( explode( "\n", $text ) );
    }

    protected function blocks( array $lines )
    {
        $html = '';
        $count = count( $lines );
        $i = 0;
        while ( $i < $count )
        {
            $line = $lines[$i];
            if ( trim( $line ) === '' )
            {
                $i++;
                continue;
            }
            // fenced code
            if ( preg_match( '/^\s*(```|~~~)\s*([A-Za-z0-9_+-]*)\s*$/', $line, $m ) )
            {
                $code = array();
                for ( $i++; $i < $count && !preg_match( '/^\s*' . preg_quote( $m[1], '/' ) . '\s*$/', $lines[$i] ); $i++ )
                    $code[] = $lines[$i];
                $i++;
                $html .= '<pre class="exp-ai-help-code"><code>' . self::e( implode( "\n", self::dedent( $code ) ) ) . "</code></pre>\n";
                continue;
            }
            // indented code (four spaces or a tab)
            if ( preg_match( '/^(    |\t)/', $line ) )
            {
                $code = array();
                while ( $i < $count && ( preg_match( '/^(    |\t)/', $lines[$i] ) || ( trim( $lines[$i] ) === '' && $i + 1 < $count && preg_match( '/^(    |\t)/', $lines[$i + 1] ) ) ) )
                    $code[] = $lines[$i++];
                $html .= '<pre class="exp-ai-help-code"><code>' . self::e( implode( "\n", self::dedent( $code ) ) ) . "</code></pre>\n";
                continue;
            }
            // heading
            if ( preg_match( '/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m ) )
            {
                $level = strlen( $m[1] );
                $html .= '<h' . $level . ' id="' . self::e( $this->headingId( $m[2] ) ) . '">' . $this->inline( $m[2] ) . '</h' . $level . ">\n";
                $i++;
                continue;
            }
            // rule
            if ( preg_match( '/^\s*([-*_])(\s*\1){2,}\s*$/', $line ) )
            {
                $html .= "<hr />\n";
                $i++;
                continue;
            }
            // table: a row line followed by a separator line
            if ( preg_match( '/^\s*\|/', $line ) && $i + 1 < $count && preg_match( '/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/', $lines[$i + 1] ) )
            {
                $head = $this->cells( $line );
                $i += 2;
                $rows = array();
                while ( $i < $count && preg_match( '/^\s*\|/', $lines[$i] ) )
                    $rows[] = $this->cells( $lines[$i++] );
                $html .= $this->table( $head, $rows );
                continue;
            }
            // block quote
            if ( preg_match( '/^\s*>/', $line ) )
            {
                $quote = array();
                while ( $i < $count && preg_match( '/^\s*>\s?(.*)$/', $lines[$i], $m ) )
                {
                    $quote[] = $m[1];
                    $i++;
                }
                $html .= '<blockquote>' . $this->blocks( $quote ) . "</blockquote>\n";
                continue;
            }
            // list
            if ( preg_match( '/^(\s*)([-*+]|\d{1,3}[.)])\s+/', $line, $m ) )
            {
                list( $list, $i ) = $this->listBlock( $lines, $i, strlen( $m[1] ) );
                $html .= $list;
                continue;
            }
            // paragraph: until a blank line or the start of another block
            $para = array();
            while ( $i < $count && trim( $lines[$i] ) !== '' )
            {
                if ( $para && ( preg_match( '/^(#{1,6})\s|^\s*(```|~~~)|^\s*>|^\s*([-*+]|\d{1,3}[.)])\s+/', $lines[$i] )
                                || ( preg_match( '/^\s*\|/', $lines[$i] ) && $i + 1 < $count && preg_match( '/^\s*\|?\s*:?-{2,}/', $lines[$i + 1] ) ) ) )
                    break;
                $para[] = trim( $lines[$i++] );
            }
            $html .= '<p>' . $this->inline( implode( "\n", $para ) ) . "</p>\n";
        }
        return $html;
    }

    /** @return array( string html, int next line ) */
    protected function listBlock( array $lines, $i, $indent )
    {
        $count = count( $lines );
        preg_match( '/^\s*([-*+]|\d{1,3}[.)])\s+/', $lines[$i], $m );
        $ordered = !in_array( $m[1], array( '-', '*', '+' ), true );
        $items = array();
        $item = null;
        $blank = false;
        while ( $i < $count )
        {
            $line = $lines[$i];
            if ( trim( $line ) === '' )
            {
                $blank = true;
                if ( $item !== null )
                    $item[] = '';
                $i++;
                continue;
            }
            $lead = strlen( $line ) - strlen( ltrim( $line, ' ' ) );
            $marker = preg_match( '/^(\s*)([-*+]|\d{1,3}[.)])(\s+)(.*)$/', $line, $mm );
            if ( $marker && $lead === $indent && ( in_array( $mm[2], array( '-', '*', '+' ), true ) !== $ordered ) )
            {
                if ( $item !== null )
                    $items[] = $item;
                $item = array( $mm[4] );
                $blank = false;
                $i++;
                continue;
            }
            if ( $lead > $indent && $item !== null )
            {
                // continuation (or a nested block): keep it relative to the item's content
                $item[] = substr( $line, min( $lead, $indent + 2 ) );
                $blank = false;
                $i++;
                continue;
            }
            if ( !$blank && $item !== null && !$marker && $lead <= $indent && !preg_match( '/^(#{1,6})\s|^\s*(```|~~~|\|)/', $line ) )
            {
                // a lazy continuation line of the item's paragraph
                $item[] = trim( $line );
                $i++;
                continue;
            }
            break;
        }
        if ( $item !== null )
            $items[] = $item;
        $tag = $ordered ? 'ol' : 'ul';
        $html = '<' . $tag . ">\n";
        foreach ( $items as $item )
        {
            while ( $item && end( $item ) === '' )
                array_pop( $item );
            $inner = $this->blocks( $item );
            // a single paragraph is shown without <p>, as a tight list
            if ( substr_count( $inner, '<p>' ) === 1 && strpos( $inner, '<p>' ) === 0 )
                $inner = preg_replace( '#^<p>(.*?)</p>\n#s', '$1', $inner, 1 );
            $html .= '<li>' . rtrim( $inner ) . "</li>\n";
        }
        return array( $html . '</' . $tag . ">\n", $i );
    }

    protected function cells( $line )
    {
        $line = trim( $line );
        if ( substr( $line, 0, 1 ) === '|' )
            $line = substr( $line, 1 );
        if ( substr( $line, -1 ) === '|' && substr( $line, -2 ) !== '\\|' )
            $line = substr( $line, 0, -1 );
        // split on | outside code spans; \| is a literal bar
        $cells = array();
        $cell = '';
        $code = false;
        $length = strlen( $line );
        for ( $k = 0; $k < $length; $k++ )
        {
            $char = $line[$k];
            if ( $char === '\\' && $k + 1 < $length && $line[$k + 1] === '|' )
            {
                $cell .= '|';
                $k++;
                continue;
            }
            if ( $char === '`' )
                $code = !$code;
            if ( $char === '|' && !$code )
            {
                $cells[] = trim( $cell );
                $cell = '';
                continue;
            }
            $cell .= $char;
        }
        $cells[] = trim( $cell );
        return $cells;
    }

    protected function table( array $head, array $rows )
    {
        $html = '<div class="exp-ai-help-table"><table class="list"><thead><tr>';
        foreach ( $head as $cell )
            $html .= '<th scope="col">' . $this->inline( $cell ) . '</th>';
        $html .= "</tr></thead>\n<tbody>\n";
        foreach ( $rows as $row )
        {
            $html .= '<tr>';
            for ( $c = 0; $c < max( count( $head ), 1 ); $c++ )
                $html .= '<td>' . $this->inline( isset( $row[$c] ) ? $row[$c] : '' ) . '</td>';
            $html .= "</tr>\n";
        }
        return $html . "</tbody></table></div>\n";
    }

    /**
     * Inline Markdown: code spans, links, bold, italics, bare http(s) addresses. Every piece of text is escaped;
     * only the renderer's own tags are added.
     */
    public function inline( $text, $links = true )
    {
        $out = '';
        // code spans first: their content is never formatted
        $parts = preg_split( '/(`+)(.+?)\1/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
        for ( $k = 0, $n = count( $parts ); $k < $n; $k++ )
        {
            if ( $k % 3 === 0 )
                $out .= $links ? $this->linked( $parts[$k] ) : $this->emphasis( $parts[$k] );
            else if ( $k % 3 === 2 )
                $out .= '<code>' . self::e( trim( $parts[$k] ) ) . '</code>';
        }
        return $out;
    }

    protected function linked( $text )
    {
        $out = '';
        $offset = 0;
        // [text](target) and [text](target "title"); the text may hold code spans, not brackets
        if ( preg_match_all( '/\[([^\[\]]+)\]\(\s*<?([^\s()<>]*(?:\([^\s()<>]*\))?[^\s()<>]*)>?(?:\s+"[^"]*")?\s*\)/', $text, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) )
        {
            foreach ( $all as $match )
            {
                $out .= $this->autolinked( substr( $text, $offset, $match[0][1] - $offset ) );
                $label = $this->inline( $match[1][0], false );
                $href = $this->href( $match[2][0] );
                $out .= $href !== null ? '<a href="' . self::e( $href['url'] ) . '"' . ( $href['external'] ? ' rel="noopener noreferrer"' : '' ) . '>' . $label . '</a>' : $label;
                $offset = $match[0][1] + strlen( $match[0][0] );
            }
        }
        return $out . $this->autolinked( substr( $text, $offset ) );
    }

    protected function autolinked( $text )
    {
        $out = '';
        $parts = preg_split( '#(https?://[^\s<>"\'`]+[^\s<>"\'`.,;:!?)\]])#', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
        foreach ( $parts as $k => $part )
            $out .= $k % 2 ? '<a href="' . self::e( $part ) . '" rel="noopener noreferrer">' . self::e( $part ) . '</a>' : $this->emphasis( $part );
        return $out;
    }

    protected function emphasis( $text )
    {
        $text = self::e( $text );
        $text = preg_replace( '/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $text );
        $text = preg_replace( '/(?<![\w*])\*(?=[^\s*])([^*\n]+?)(?<=[^\s*])\*(?![\w*])/', '<em>$1</em>', $text );
        return str_replace( "\n", ' ', $text );
    }

    /**
     * @param string $target the link target as written
     * @return array|null array( 'url', 'external' ), or null when the link is shown as text
     */
    public function href( $target )
    {
        $target = trim( html_entity_decode( $target, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        if ( $target === '' || preg_match( '/[\x00-\x20\x7f]/', $target ) )
            return null;
        if ( $target[0] === '#' )
            return preg_match( '/^#[A-Za-z0-9_-]+$/', $target ) ? array( 'url' => $target, 'external' => false ) : null;
        if ( preg_match( '#^(https?://[^/\s]+|mailto:)#i', $target ) )
            return array( 'url' => $target, 'external' => true );
        if ( preg_match( '/^[a-z][a-z0-9+.-]*:/i', $target ) || strpos( $target, '//' ) === 0 )
            return null;
        // a relative document of this extension
        $anchor = '';
        if ( ( $hash = strpos( $target, '#' ) ) !== false )
        {
            $anchor = substr( $target, $hash + 1 );
            $target = substr( $target, 0, $hash );
            if ( !preg_match( '/^[A-Za-z0-9_-]*$/', $anchor ) )
                return null;
        }
        $path = self::resolve( dirname( $this->current ), $target );
        if ( $path === null )
            return null;
        $name = self::nameOf( $path );
        if ( $name === '' || !$this->exists( $name ) || $this->pages[$name]['file'] !== $path )
            return null;
        return array( 'url' => $this->url( $name, $anchor ), 'external' => false );
    }

    /** @return string the address of a help page */
    public function url( $name, $anchor = '' )
    {
        if ( $this->urlBuilder )
            return call_user_func( $this->urlBuilder, $name, $anchor );
        $url = 'expai/help/(page)/' . rawurlencode( $name );
        eZURI::transformURI( $url );
        return $url . ( $anchor !== '' ? '#' . $anchor : '' );
    }

    /** @return string|null a relative path inside the extension, or null when it leaves it */
    protected static function resolve( $dir, $target )
    {
        $parts = ( $dir === '.' || $dir === '' ) ? array() : explode( '/', $dir );
        foreach ( explode( '/', $target ) as $part )
        {
            if ( $part === '' || $part === '.' )
                continue;
            if ( $part === '..' )
            {
                if ( !$parts )
                    return null;
                array_pop( $parts );
                continue;
            }
            $parts[] = $part;
        }
        return $parts ? implode( '/', $parts ) : null;
    }

    /** GitHub-style heading ids, so links like chains.md#permissions land on their heading */
    protected function headingId( $text )
    {
        $id = mb_strtolower( str_replace( '`', '', $text ), 'UTF-8' );
        $id = preg_replace( '/[^\p{L}\p{N} _-]+/u', '', $id );
        $id = str_replace( ' ', '-', trim( $id ) );
        if ( $id === '' )
            $id = 'section';
        $base = $id;
        for ( $n = 1; isset( $this->ids[$id] ); $n++ )
            $id = $base . '-' . $n;
        $this->ids[$id] = true;
        return $id;
    }

    protected static function dedent( array $lines )
    {
        $min = null;
        foreach ( $lines as $line )
            if ( trim( $line ) !== '' )
            {
                $lead = strlen( $line ) - strlen( ltrim( $line, " \t" ) );
                $min = $min === null ? $lead : min( $min, $lead );
            }
        if ( !$min )
            return $lines;
        return array_map( function ( $line ) use ( $min ) { return (string) substr( $line, $min ); }, $lines );
    }

    public static function e( $text )
    {
        return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
    }
}
