/*!
 * expai - Exponential Intelligence
 * Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * GNU General Public License v2.0 (or any later version)
 *
 * The structure of a rich text value (doc/edit-view-integration.md "Structure is never lost silently"). Loaded on
 * every admin page before expai.js (design.ini [JavaScriptSettings] BackendJavaScriptList[]); also a CommonJS module,
 * so tests/js/expai_structure.test.mjs runs it with node. No DOM: it reads the markup with a small tag scanner, so
 * it works on the HTML of the Online Editor (TinyMCE), of Tiptap (exp_oe_tiptap) and on the simplified XML of a
 * textarea without an editor alike.
 *
 *   ExpAiStructure.count( value )              { embed, table, custom, anchor, literal, link, attribute }
 *   ExpAiStructure.loss( current, proposed )   [ { kind, lost } ]: the kinds the proposed value has fewer of
 *   ExpAiStructure.describe( loss, strings )   "2 embedded objects, 1 table" (strings lost_<kind>_1 / lost_<kind>_n)
 *   ExpAiStructure.toText( html )              the value as plain text with paragraphs, for a preview
 *   ExpAiStructure.same( a, b )                the two values are the same, white space between tags aside
 */
(function ( root, factory ) {
    'use strict';
    var api = factory();
    if ( typeof module === 'object' && module.exports ) {
        module.exports = api;
    } else {
        root.ExpAiStructure = api;
    }
})( typeof window !== 'undefined' ? window : this, function () {
    'use strict';

    var KINDS = [ 'embed', 'table', 'custom', 'anchor', 'literal', 'link', 'attribute' ];

    var VOID = { area: 1, base: 1, br: 1, col: 1, embed: 1, hr: 1, img: 1, input: 1, link: 1, meta: 1, param: 1, source: 1, track: 1, wbr: 1 };

    // elements that carry formatting attributes worth keeping (blocks, list items, cells, inline styles)
    var FORMATTED = { p: 1, h1: 1, h2: 1, h3: 1, h4: 1, h5: 1, h6: 1, ul: 1, ol: 1, li: 1, td: 1, th: 1, tr: 1,
                      strong: 1, b: 1, em: 1, i: 1, paragraph: 1, header: 1, section: 1, emphasize: 1 };

    var EMBED_ELEMENTS = { embed: 1, 'embed-inline': 1, object: 1, iframe: 1, video: 1, audio: 1 };

    // no "<" in names and unquoted values: an unclosed tag ends at the next one, so the scan stays linear
    var TAG = /<!--[\s\S]*?(?:-->|$)|<(\/?)([a-zA-Z][\w:.-]*)((?:\s+[^\s"'<>\/=]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s"'<>]+))?)*)\s*(\/?)>/g;
    var ATTRIBUTE = /([^\s"'<>\/=]+)(?:\s*=\s*("[^"]*"|'[^']*'|[^\s"'<>]+))?/g;

    function attributes( text ) {
        var out = {}, m;
        ATTRIBUTE.lastIndex = 0;
        while ( ( m = ATTRIBUTE.exec( text || '' ) ) ) {
            var value = m[2] === undefined ? '' : m[2];
            if ( value.charAt( 0 ) === '"' || value.charAt( 0 ) === "'" ) {
                value = value.slice( 1, -1 );
            }
            out[m[1].toLowerCase()] = value;
        }
        return out;
    }

    function has( attrs, name ) {
        return Object.prototype.hasOwnProperty.call( attrs, name );
    }

    function classes( attrs ) {
        return String( attrs['class'] || '' ).split( /\s+/ ).filter( Boolean );
    }

    /** The classes the editors add themselves (ezoeItem..., mceItem..., ezoeAlign...) are not the author's */
    function ownClasses( attrs ) {
        return classes( attrs ).filter( function ( c ) { return !/^(ezoe|mce)/i.test( c ); } );
    }

    /** A link the answer cannot keep: to an object or node, or a path on the site (the answer keeps http(s) and mailto) */
    function internalLink( attrs ) {
        if ( has( attrs, 'object_id' ) || has( attrs, 'node_id' ) || has( attrs, 'url_id' ) ) {
            return true;
        }
        var href = String( attrs.href || '' ).trim();
        if ( href === '' || href.charAt( 0 ) === '#' ) {
            return false;
        }
        return !/^(https?:\/\/|mailto:)/i.test( href );
    }

    /**
     * The kind of one opening tag, or ''. skip: true for an embed whose content is the editor's preview (its links,
     * images and tables are not part of the text).
     */
    function classify( name, attrs ) {
        var cls = classes( attrs );
        var element = String( attrs['data-ezelement'] || '' ).toLowerCase();
        if ( EMBED_ELEMENTS[name] || /^ez(object|node)_\d+/i.test( attrs.id || '' ) || /^ezembed/.test( element )
             || cls.indexOf( 'ezoeItemNonEditable' ) !== -1 || cls.some( function ( c ) { return /^ezoeItemContentType/.test( c ); } ) ) {
            return { kind: 'embed', skip: !VOID[name] };
        }
        if ( name === 'custom' || name === 'sub' || name === 'sup' || /^eztemplate/.test( element )
             || cls.indexOf( 'ezoeItemCustomTag' ) !== -1 || String( attrs.type || '' ).toLowerCase() === 'custom' ) {
            return { kind: 'custom' };
        }
        if ( name === 'img' ) {
            return { kind: 'embed' };
        }
        if ( name === 'table' ) {
            return { kind: 'table' };
        }
        if ( name === 'pre' || name === 'literal' ) {
            return { kind: 'literal' };
        }
        if ( name === 'anchor' || ( name === 'a' && !has( attrs, 'href' ) && ( attrs.name || attrs.id ) ) || ( name === 'header' && attrs.anchor_name ) ) {
            return { kind: 'anchor' };
        }
        if ( ( name === 'a' || name === 'link' ) && internalLink( attrs ) ) {
            return { kind: 'link' };
        }
        if ( FORMATTED[name] && ( attrs.align || attrs.customattributes || ownClasses( attrs ).length
             || Object.keys( attrs ).some( function ( a ) { return a.indexOf( 'custom:' ) === 0; } ) ) ) {
            return { kind: 'attribute' };
        }
        return null;
    }

    /** How many structure-bearing elements of each kind the value has */
    function count( value ) {
        var out = {};
        KINDS.forEach( function ( k ) { out[k] = 0; } );
        var text = String( value === undefined || value === null ? '' : value );
        var skip = null, m;
        TAG.lastIndex = 0;
        while ( ( m = TAG.exec( text ) ) ) {
            if ( !m[2] ) {
                continue; // a comment
            }
            var name = m[2].toLowerCase(), closing = m[1] === '/', selfClosing = m[4] === '/' || !!VOID[name];
            if ( skip ) {
                if ( name === skip.name && !selfClosing ) {
                    skip.depth += closing ? -1 : 1;
                    if ( skip.depth === 0 ) {
                        skip = null;
                    }
                }
                continue;
            }
            if ( closing ) {
                continue;
            }
            var found = classify( name, attributes( m[3] ) );
            if ( !found ) {
                continue;
            }
            out[found.kind]++;
            if ( found.skip && !selfClosing ) {
                skip = { name: name, depth: 1 };
            }
        }
        return out;
    }

    /** The kinds the proposed value would lose: [ { kind, lost } ], in the order of KINDS; [] when nothing is lost */
    function loss( current, proposed ) {
        var before = count( current ), after = count( proposed );
        return KINDS.filter( function ( k ) { return after[k] < before[k]; } ).map( function ( k ) {
            return { kind: k, lost: before[k] - after[k] };
        } );
    }

    /** "2 embedded objects, 1 table": strings lost_<kind>_1 and lost_<kind>_n with %count, joined with ", " */
    function describe( list, strings ) {
        strings = strings || {};
        return ( list || [] ).map( function ( l ) {
            var text = strings['lost_' + l.kind + ( l.lost === 1 ? '_1' : '_n' )] || ( '%count ' + l.kind );
            return String( text ).split( '%count' ).join( String( l.lost ) );
        } ).join( ', ' );
    }

    function decode( text ) {
        return text.replace( /&(#x[0-9a-f]+|#\d+|amp|lt|gt|quot|apos|nbsp|#39);/gi, function ( all, e ) {
            var key = e.toLowerCase();
            if ( key.charAt( 0 ) === '#' ) {
                var code = key.charAt( 1 ) === 'x' ? parseInt( key.slice( 2 ), 16 ) : parseInt( key.slice( 1 ), 10 );
                return code > 0 && code < 0x110000 ? String.fromCodePoint( code ) : '';
            }
            return { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' }[key];
        } );
    }

    /** The value as plain text, blocks as paragraphs and list items as "- " lines (for a preview through renderText) */
    function toText( value ) {
        var text = String( value === undefined || value === null ? '' : value );
        if ( text.indexOf( '<' ) === -1 ) {
            return decode( text );
        }
        text = text.replace( /<(script|style)\b[\s\S]*?<\/\1\s*>/gi, '' )
            .replace( /<br\s*\/?>/gi, '\n' )
            .replace( /<li\b[^>]*>/gi, '\n- ' )
            .replace( /<\/(p|h[1-6]|ul|ol|pre|table|tr|div|paragraph|header|section|literal|blockquote)\s*>/gi, '\n\n' )
            .replace( /<\/(td|th)\s*>/gi, ' ' )
            .replace( /<[^>]*>/g, '' );
        return decode( text ).replace( /[ \t]+\n/g, '\n' ).replace( /\n{3,}/g, '\n\n' ).trim();
    }

    /** The same value, ignoring white space between tags and at the ends (editors reformat it) */
    function same( a, b ) {
        var norm = function ( v ) {
            return String( v === undefined || v === null ? '' : v ).replace( />\s+</g, '><' ).replace( /\s+/g, ' ' ).trim();
        };
        return norm( a ) === norm( b );
    }

    return { kinds: KINDS, count: count, loss: loss, describe: describe, toText: toText, same: same };
} );
