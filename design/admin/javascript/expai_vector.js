/*!
 * expai - Vector, the workspace of threads (doc/vector.md)
 * Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * GNU General Public License v2.0 (or any later version)
 *
 * Loaded only by expai/vector (ezscript_require in expai/vector.tpl), after expai.js and expai_palette.js. Everything
 * comes through ezjscore (expaivector::*): the thread list, the open thread, runs (foreground steps, as the panel),
 * stop, the queue of follow-ups, the scope. Events arrive by polling (expaivector::events) or, where the server
 * streams (bootstrap.transport = sse), by Server-Sent Events (expai/stream) with a fallback to polling.
 *
 *   window.ExpAiVectorApp = { open( uuid ), setModel( alias ), toast( text ), bindingsChanged( bindings ) }
 *   ExpAi events: vector:open, vector:step, vector:done
 */
(function ( window, document ) {
    'use strict';
    // the script is loaded in the page head: everything starts once the shell is there
    function init() {
        var ExpAi = window.ExpAi;
        var Keys = window.ExpAiKeys;
        var root = document.getElementById( 'exp-ai-vector' );
        if ( !ExpAi || !Keys || !root || !root.querySelector( '.exp-ai-v-grid' ) ) {
            return;
        }
        var esc = ExpAi.esc, fmt = ExpAi.format, url = ExpAi.url;
        var S = {};
        try { S = JSON.parse( root.querySelector( '.exp-ai-v-strings' ).textContent ) || {}; } catch ( e ) { S = {}; }
        function T( key, args ) { return fmt( S[key] || key, args || {} ); }
        function api( fn, data ) { return ExpAi.api( 'expaivector::' + fn, data ); }
        function q( sel, ctx ) { return ( ctx || root ).querySelector( sel ); }
        function qa( sel, ctx ) { return Array.prototype.slice.call( ( ctx || root ).querySelectorAll( sel ) ); }

        var el = {
            list: q( '.exp-ai-v-list' ), filter: q( '.exp-ai-v-filter' ), empty: q( '.exp-ai-v-empty' ), thread: q( '.exp-ai-v-thread' ),
            title: q( '.exp-ai-v-title' ), state: q( '.exp-ai-v-state' ), chips: q( '.exp-ai-v-chips' ), note: q( '.exp-ai-v-note' ),
            model: q( '.exp-ai-v-model' ), lane: q( '.exp-ai-v-lane' ), review: q( '.exp-ai-v-review' ), log: q( '.exp-ai-v-log' ),
            older: q( '.exp-ai-v-older' ), working: q( '.exp-ai-v-working' ), workingText: q( '.exp-ai-v-working-text' ), error: q( '.exp-ai-v-thread .exp-ai-v-error' ),
            input: q( '.exp-ai-v-input' ), run: q( '.exp-ai-v-run' ), stop: q( '.exp-ai-v-stop' ), queueBtn: q( '.exp-ai-v-queue-btn' ),
            queue: q( '.exp-ai-v-queue' ), queueList: q( '.exp-ai-v-queue-list' ), hint: q( '.exp-ai-v-hint' ), live: q( '[data-live="steps"]' ),
            readonly: q( '.exp-ai-v-readonly' ), dialog: q( '.exp-ai-v-scope-dialog' ), toast: q( '.exp-ai-v-toast' ), side: q( '.exp-ai-v-side' ),
            threadsPane: q( '.exp-ai-v-threads' )
        };

        var app = {
            boot: null, bindings: {}, shelf: root.getAttribute( 'data-shelf' ) || '', rows: [], shared: [], current: null, busy: false,
            stopRequested: false, followups: [], activeItem: -1, cursor: {}, oldest: null, pollTimer: null, stream: null, lanes: [], scopeDraft: null
        };

        /* ------------------------------------------------------------ utilities */

        function stateLabel( s ) { return T( 'state_' + s ); }
        function money( t ) { return t || ''; }
        function timeText( seconds ) {
            var d = new Date( seconds * 1000 ), now = new Date();
            var hm = ( '0' + d.getHours() ).slice( -2 ) + ':' + ( '0' + d.getMinutes() ).slice( -2 );
            return d.toDateString() === now.toDateString() ? hm : d.toLocaleDateString() + ' ' + hm;
        }
        function showError( box, err ) {
            if ( !box ) { return; }
            if ( !err ) { box.hidden = true; box.textContent = ''; return; }
            box.hidden = false;
            box.textContent = err.kind === 'timeout' ? T( 'timeout' ) : T( 'error' ) + ' ' + ( err.message || '' );
        }
        var toastTimer = null, toastUndo = null;
        function toast( text, undo ) {
            if ( !el.toast ) { return; }
            q( '.exp-ai-v-toast-text' ).textContent = text;
            q( '.exp-ai-v-undo' ).hidden = !undo;
            toastUndo = undo || null;
            el.toast.hidden = false;
            window.clearTimeout( toastTimer );
            toastTimer = window.setTimeout( function () { el.toast.hidden = true; toastUndo = null; }, 5000 );
        }
        var liveAt = 0;
        function announce( text ) {
            // the live region speaks at most every 2 seconds
            if ( !el.live || Date.now() - liveAt < 2000 ) { return; }
            liveAt = Date.now();
            el.live.textContent = text;
        }
        function keysOf( command ) { return app.bindings[command] ? app.bindings[command].keys : ''; }
        function paintKeys() {
            qa( 'kbd[data-keys]', document ).forEach( function ( k ) {
                var keys = keysOf( k.getAttribute( 'data-keys' ) );
                k.textContent = keys ? Keys.display( keys ) : '';
                k.hidden = !keys;
            } );
            el.hint.textContent = app.busy ? T( 'hint_running', { '%queue': Keys.display( keysOf( 'queue' ) ), '%stop': Keys.display( keysOf( 'stop' ) ) } )
                                           : T( 'hint_idle', { '%run': Keys.display( keysOf( 'run' ) ) } );
        }
        function canWrite() { return !!( app.current && app.current.can_write ); }

        /* ----------------------------------------------------------- the list */

        function section( row ) {
            if ( row.state === 'queued' || row.state === 'running' ) { return 'working'; }
            if ( row.state === 'review' || row.state === 'waiting_input' || row.state === 'failed' ) { return 'needs'; }
            if ( row.shelf === 'pinned' ) { return 'pinned'; }
            return 'recent';
        }

        function rowHtml( row ) {
            var selected = app.current && app.current.uuid === row.uuid;
            return '<div role="option" id="exp-ai-v-row-' + esc( row.uuid ) + '" class="exp-ai-v-row exp-ai-v-st-' + esc( row.state ) + ( selected ? ' exp-ai-v-row-open' : '' ) + '"'
                 + ' data-uuid="' + esc( row.uuid ) + '" aria-selected="' + ( selected ? 'true' : 'false' ) + '" title="' + esc( stateLabel( row.state ) + ( row.cost ? ' · ' + row.cost : '' ) ) + '">'
                 + '<span class="exp-ai-v-glyph" aria-hidden="true"></span>'
                 + '<span class="exp-ai-v-row-title' + ( row.untitled ? ' exp-ai-v-untitled' : '' ) + '">' + esc( row.title ) + '</span>'
                 + ( row.unread ? '<span class="exp-ai-v-unread" title="' + esc( T( 'unread' ) ) + '"><span class="exp-ai-sr">' + esc( T( 'unread' ) ) + '</span></span>' : '' )
                 + '<span class="exp-ai-v-row-meta">' + ( row.bulk ? '<span class="exp-ai-v-badge">' + esc( T( 'bulk' ) ) + '</span> ' : '' )
                 + esc( row.origin === 'panel' ? T( 'origin_panel' ) : stateLabel( row.state ) ) + '</span></div>';
        }

        function renderList() {
            var groups = app.shelf ? [ [ app.shelf === 'settled' ? 'section_settled' : 'section_archived', app.rows ] ] : [
                [ 'section_working', app.rows.filter( function ( r ) { return section( r ) === 'working'; } ) ],
                [ 'section_needs', app.rows.filter( function ( r ) { return section( r ) === 'needs'; } ) ],
                [ 'section_pinned', app.rows.filter( function ( r ) { return section( r ) === 'pinned'; } ) ],
                [ 'section_recent', app.rows.filter( function ( r ) { return section( r ) === 'recent'; } ) ],
                [ 'section_shared', app.shared ]
            ];
            var html = '';
            groups.forEach( function ( g ) {
                if ( !g[1].length ) { return; }
                html += '<div class="exp-ai-v-section" role="group" aria-label="' + esc( T( g[0] ) ) + '"><h3>' + esc( T( g[0] ) ) + ' <span>' + g[1].length + '</span></h3>'
                      + g[1].map( rowHtml ).join( '' ) + '</div>';
            } );
            el.list.innerHTML = html || '<p class="exp-ai-v-list-empty">' + esc( el.filter.value.trim() ? T( 'no_match' ) : T( 'no_threads' ) ) + '</p>';
            if ( app.current ) {
                el.list.setAttribute( 'aria-activedescendant', 'exp-ai-v-row-' + app.current.uuid );
            }
            var needs = app.rows.filter( function ( r ) { return section( r ) === 'needs'; } ).length;
            var base = document.title.replace( /^\(\d+\)\s+/, '' );
            document.title = needs ? '(' + needs + ') ' + base : base;
            qa( '.exp-ai-v-shelves a' ).forEach( function ( a ) {
                a.setAttribute( 'aria-current', a.getAttribute( 'data-shelf' ) === app.shelf ? 'page' : 'false' );
            } );
        }

        var listSeq = 0;
        function loadList() {
            var seq = ++listSeq;
            return api( 'threads', { shelf: app.shelf || 'active', search: el.filter.value.trim() } ).then( function ( data ) {
                if ( seq !== listSeq ) { return; }
                app.rows = data.threads || [];
                app.shared = app.shelf ? [] : data.shared || [];
                [ 'settled', 'archived' ].forEach( function ( k ) {
                    var c = q( '[data-count="' + k + '"]' );
                    if ( c ) { c.textContent = data.counts && data.counts[k] ? data.counts[k] : ''; }
                } );
                renderList();
            } ).catch( function ( err ) { el.list.innerHTML = '<p class="exp-ai-v-error">' + esc( T( 'error' ) + ' ' + ( err.message || '' ) ) + '</p>'; } );
        }

        function orderedRows() {
            return qa( '.exp-ai-v-row', el.list ).map( function ( r ) { return r.getAttribute( 'data-uuid' ); } );
        }

        /* ---------------------------------------------------------- the thread */

        function setHeader( t ) {
            app.current = t;
            root.setAttribute( 'data-thread', t.uuid );
            el.empty.hidden = true;
            el.thread.hidden = false;
            el.title.value = t.untitled ? '' : t.title;
            el.title.placeholder = T( 'new_thread' );
            el.title.readOnly = !t.can_write;
            el.state.textContent = stateLabel( t.state );
            el.state.className = 'exp-ai-v-state exp-ai-v-st-' + t.state;
            el.readonly.hidden = t.can_write;
            var pin = q( '[data-command="pin"]' );
            pin.setAttribute( 'aria-pressed', t.shelf === 'pinned' ? 'true' : 'false' );
            qa( '.exp-ai-v-thread-actions button, .exp-ai-v-controls button, .exp-ai-v-controls select, .exp-ai-v-composer textarea, .exp-ai-v-composer button' ).forEach( function ( b ) {
                b.disabled = !t.can_write;
            } );
            renderChips( t );
            renderSettings( t );
            renderCost( t );
        }

        function chip( node, kind ) {
            return '<span class="exp-ai-v-chip exp-ai-v-chip-' + kind + '" title="' + esc( T( 'scope_' + kind ) ) + '">'
                 + '<span class="exp-ai-v-chip-kind" aria-hidden="true">' + ( kind === 'subtree' ? '▾' : '●' ) + '</span>' + esc( node.name ) + '</span>';
        }

        function renderChips( t ) {
            var s = t.scope || {};
            var html = ( s.nodes || [] ).map( function ( n ) { return chip( n, 'node' ); } ).join( '' )
                     + ( s.subtrees || [] ).map( function ( n ) { return chip( n, 'subtree' ); } ).join( '' )
                     + ( s.classes || [] ).map( function ( c ) { return '<span class="exp-ai-v-chip exp-ai-v-chip-class">' + esc( c.name ) + '</span>'; } ).join( '' )
                     + ( s.languages || [] ).map( function ( l ) { return '<span class="exp-ai-v-chip exp-ai-v-chip-lang">' + esc( l.code ) + '</span>'; } ).join( '' );
            el.chips.innerHTML = html || '<span class="exp-ai-v-chip-none">' + esc( T( 'scope_none' ) ) + '</span>';
            if ( t.bulk ) {
                el.chips.insertAdjacentHTML( 'beforeend', '<span class="exp-ai-v-badge">' + esc( T( 'bulk' ) ) + '</span>' );
            }
        }

        function renderSettings( t ) {
            qa( '.exp-ai-v-seg [data-mode]' ).forEach( function ( b ) {
                var on = b.getAttribute( 'data-mode' ) === t.run_mode;
                b.setAttribute( 'aria-checked', on ? 'true' : 'false' );
                b.tabIndex = on ? 0 : -1;
                if ( b.getAttribute( 'data-mode' ) === 'write' && t.bulk ) { b.disabled = true; }
            } );
            // models: grouped by provider, from the models the user may use
            var models = app.boot ? app.boot.models : null;
            var html = '';
            var found = false;
            ( models ? models.groups : [] ).forEach( function ( g ) {
                html += '<optgroup label="' + esc( g.label ) + '">' + ( g.models || [] ).map( function ( m ) {
                    if ( m.alias === t.model_alias ) { found = true; }
                    return '<option value="' + esc( m.alias ) + '"' + ( m.alias === t.model_alias ? ' selected' : '' ) + '>' + esc( m.label + ( m.writes ? '' : ' · ' + T( 'model_reads_only' ) ) ) + '</option>';
                } ).join( '' ) + '</optgroup>';
            } );
            if ( !found && t.model_alias ) {
                html = '<option value="' + esc( t.model_alias ) + '" selected>' + esc( t.model_label || t.model_alias ) + '</option>' + html;
            }
            el.model.innerHTML = html;
            el.model.disabled = !t.can_write || !( app.boot && app.boot.model_choice );
            el.lane.innerHTML = ( app.lanes || [] ).map( function ( l ) {
                return '<option value="' + esc( l.name ) + '"' + ( l.name === t.lane ? ' selected' : '' ) + ( !l.usable || l.batch ? ' disabled' : '' ) + '>'
                     + esc( l.label + ( l.batch ? ' · ' + T( 'lane_batch_note' ) : '' ) ) + '</option>';
            } ).join( '' );
            var reviews = app.boot ? app.boot.choices.review : [ 'staged', 'direct' ];
            qa( 'option', el.review ).forEach( function ( o ) {
                o.disabled = reviews.indexOf( o.value ) === -1 || ( o.value === 'direct' && t.bulk && t.origin !== 'panel' );
                o.selected = o.value === t.review_policy;
            } );
            var notes = [];
            if ( t.bulk && t.run_mode === 'plan' ) { notes.push( T( 'bulk_note' ) ); }
            else if ( t.run_mode === 'plan' ) { notes.push( T( 'plan_note' ) ); }
            else if ( t.review_policy === 'staged' && t.origin !== 'panel' ) { notes.push( T( 'staged_note' ) ); }
            else { notes.push( T( 'direct_note' ) ); }
            if ( t.lane && t.lane !== 'auto' && t.lane !== 'standard' ) { notes.push( T( 'lane_foreground' ) ); }
            el.note.textContent = notes.join( ' ' );
            // the model select was rebuilt: the states of the models (stopped, unreachable) are applied again (expai.js)
            if ( app.current && app.current.uuid === t.uuid ) { ExpAi.emit( 'vector:open', { thread: t, header: true } ); }
        }

        function renderCost( t ) {
            var dl = q( '.exp-ai-v-cost' );
            if ( dl ) {
                var rows = [];
                if ( t.cost ) { rows.push( [ T( 'cost_spent' ), t.cost ] ); }
                rows.push( [ T( 'cost_tokens' ), t.tokens ], [ T( 'cost_requests' ), String( t.requests ) ], [ T( 'cost_model' ), t.model_label || t.model_alias || '-' ],
                           [ T( 'cost_lane' ), ( app.lanes.filter( function ( l ) { return l.name === t.lane; } )[0] || { label: t.lane } ).label ] );
                dl.innerHTML = rows.map( function ( r ) { return '<div><dt>' + esc( r[0] ) + '</dt><dd>' + esc( r[1] ) + '</dd></div>'; } ).join( '' );
            }
            var count = q( '[data-tab-count="cost"]' );
            if ( count ) { count.textContent = t.cost || ''; }
        }

        function renderDrafts( drafts ) {
            var ul = q( '.exp-ai-v-drafts' );
            if ( !ul ) { return; }
            ul.innerHTML = ( drafts || [] ).map( function ( d, i ) {
                return '<li role="option" id="exp-ai-v-draft-' + i + '" class="exp-ai-v-draft"><span class="exp-ai-mark" aria-hidden="true"></span><span class="exp-ai-v-draft-name">' + esc( d.name ) + '</span>'
                     + '<span class="exp-ai-v-draft-meta">' + esc( d.class_name + ' · ' + d.language + ' · ' + T( 'status_' + d.status ) ) + '</span>'
                     + '<span class="exp-ai-v-draft-links">' + ( d.urls.edit ? '<a href="' + esc( url( d.urls.edit ) ) + '">' + esc( T( 'edit' ) ) + '</a>' : '' )
                     + '<a href="' + esc( url( d.urls.view ) ) + '">' + esc( T( 'view' ) ) + '</a></span></li>';
            } ).join( '' );
            var none = q( '[data-placeholder="drafts"]' );
            if ( none ) { none.hidden = !!( drafts && drafts.length ); }
            var count = q( '[data-tab-count="diff"]' );
            var open = ( drafts || [] ).filter( function ( d ) { return d.is_draft; } ).length;
            if ( count ) { count.textContent = open ? String( open ) : ''; }
        }

        function stepsHtml( steps ) {
            // consecutive reads collapse into one line; writes and failures always show
            var out = [], reads = [];
            function flush() {
                if ( !reads.length ) { return; }
                out.push( reads.length === 1 ? '<li class="exp-ai-step">' + esc( reads[0].text ) + '</li>'
                    : '<li class="exp-ai-step exp-ai-v-reads"><details><summary>' + esc( T( 'lookups', { '%count': reads.length } ) ) + '</summary><ul>'
                      + reads.map( function ( s ) { return '<li>' + esc( s.text ) + '</li>'; } ).join( '' ) + '</ul></details></li>' );
                reads = [];
            }
            ( steps || [] ).forEach( function ( s ) {
                if ( s.ok && !s.writes ) { reads.push( s ); return; }
                flush();
                out.push( '<li class="exp-ai-step' + ( s.ok ? '' : ' exp-ai-step-failed' ) + ( s.writes ? ' exp-ai-step-write' : '' ) + '">' + esc( s.text ) + '</li>' );
            } );
            flush();
            return out.join( '' );
        }

        function exchangeHtml( x ) {
            var drafts = ( x.drafts || [] ).map( function ( d ) {
                return '<a class="exp-ai-chip-link" href="' + esc( url( d.view ) ) + '"><span class="exp-ai-mark" aria-hidden="true"></span>' + esc( d.name ) + '</a>';
            } ).join( ' ' );
            var steps = stepsHtml( x.steps );
            return ( x.notice ? '<p class="exp-ai-fallback-notice" role="status">' + esc( x.notice ) + '</p>' : '' )
                 + '<div class="exp-ai-v-msg exp-ai-v-msg-user"><span class="exp-ai-v-who">' + esc( T( 'you' ) ) + ' <time>' + esc( timeText( x.time ) ) + '</time></span>'
                 + '<div class="exp-ai-v-body">' + ExpAi.renderText( x.user ) + '</div></div>'
                 + ( steps ? '<ul class="exp-ai-steps exp-ai-v-steps">' + steps + '</ul>' : '' )
                 + '<div class="exp-ai-v-msg exp-ai-v-msg-ai"><span class="exp-ai-v-who"><span class="exp-ai-mark" aria-hidden="true"></span> ' + esc( T( 'assistant' ) ) + '</span>'
                 + '<div class="exp-ai-v-body">' + ( x.reply ? ExpAi.renderText( x.reply ) : '<p class="exp-ai-v-muted">' + esc( T( 'no_reply' ) ) + '</p>' ) + '</div>'
                 + ( drafts ? '<div class="exp-ai-v-drafts-inline"><span>' + esc( T( 'drafts_written' ) ) + ':</span> ' + drafts + '</div>' : '' ) + '</div>';
        }

        function showExchange( x, prepend ) {
            var li = el.log.querySelector( 'li[data-id="' + x.id + '"]' );
            if ( !li ) {
                li = document.createElement( 'li' );
                li.className = 'exp-ai-v-x';
                li.setAttribute( 'data-id', x.id );
                li.tabIndex = -1;
                if ( prepend ) { el.log.insertBefore( li, el.log.firstChild ); } else { el.log.appendChild( li ); }
            }
            li.innerHTML = exchangeHtml( x );
            if ( app.oldest === null || x.id < app.oldest ) { app.oldest = x.id; }
            return li;
        }

        function scrollDown() {
            var box = q( '.exp-ai-v-scroll' );
            if ( box ) { box.scrollTop = box.scrollHeight; }
        }

        function renderFollowups() {
            el.queue.hidden = !app.followups.length;
            el.queueList.innerHTML = app.followups.map( function ( f ) {
                return '<li data-id="' + f.id + '"><span>' + esc( f.body ) + '</span> <button type="button" class="exp-ai-v-link" data-drop="' + f.id + '" aria-label="'
                     + esc( T( 'queue_remove' ) ) + '">×</button></li>';
            } ).join( '' );
        }

        var openSeq = 0;
        function open( uuid, push ) {
            var seq = ++openSeq;
            showError( el.error, null );
            return api( 'thread', { thread: uuid } ).then( function ( data ) {
                if ( seq !== openSeq ) { return; }
                app.lanes = data.lanes || [];
                setHeader( data.thread );
                el.log.innerHTML = '';
                app.oldest = null;
                app.activeItem = -1;
                ( data.history.exchanges || [] ).forEach( function ( x ) { showExchange( x, false ); } );
                el.older.hidden = !data.history.has_older;
                renderDrafts( data.drafts );
                app.followups = data.followups || [];
                renderFollowups();
                app.cursor[uuid] = data.thread.last_event_id;
                setBusy( false );
                if ( data.pending && canWrite() ) {
                    showError( el.error, { message: T( 'interrupted' ) } );
                }
                if ( push !== false ) {
                    var target = url( 'expai/vector/(thread)/' + uuid );
                    if ( window.location.pathname + window.location.search !== target ) {
                        window.history.pushState( { uuid: uuid }, '', target );
                    }
                }
                var row = app.rows.filter( function ( r ) { return r.uuid === uuid; } )[0];
                if ( row ) { row.unread = false; }
                renderList();
                scrollDown();
                closeDrawers();
                ExpAi.emit( 'vector:open', { thread: data.thread } );
                schedulePoll( 0 );
                connectStream();
            } ).catch( function ( err ) {
                if ( seq !== openSeq ) { return; }
                el.empty.hidden = true;
                el.thread.hidden = false;
                showError( el.error, err.kind === 'server' ? { message: err.message } : err );
            } );
        }

        function newThread( nodeID ) {
            return api( 'thread_save', nodeID ? { node: nodeID } : {} ).then( function ( data ) {
                app.rows.unshift( data.thread );
                return open( data.thread.uuid ).then( function () {
                    if ( !nodeID ) { openScope(); } else { el.input.focus(); }
                } );
            } ).catch( function ( err ) { toast( T( 'error' ) + ' ' + ( err.message || '' ) ); } );
        }

        function save( fields, quiet ) {
            if ( !app.current || !canWrite() ) { return Promise.resolve(); }
            return api( 'thread_save', $.extend( { thread: app.current.uuid }, fields ) ).then( function ( data ) {
                if ( data.lanes ) { app.lanes = data.lanes; }
                setHeader( data.thread );
                ( data.notes || [] ).forEach( function ( n ) { toast( n ); } );
                var i = app.rows.map( function ( r ) { return r.uuid; } ).indexOf( data.thread.uuid );
                if ( i >= 0 ) { app.rows[i] = data.thread; }
                if ( !quiet ) { renderList(); }
                return data;
            } ).catch( function ( err ) {
                toast( T( 'error' ) + ' ' + ( err.message || '' ) );
                if ( app.current ) { setHeader( app.current ); }
            } );
        }
        var $ = window.jQuery;

        /* --------------------------------------------------------------- running */

        function setBusy( on ) {
            app.busy = on;
            root.classList.toggle( 'exp-ai-busy', on );
            el.working.hidden = !on;
            el.stop.hidden = !on;
            el.queueBtn.hidden = !on;
            el.run.hidden = on;
            if ( !on ) { app.stopRequested = false; }
            paintKeys();
        }

        var stepCount = 0;
        function step( params ) {
            return api( 'turn', $.extend( { thread: app.current.uuid }, params ) ).then( function ( data ) {
                if ( data.exchange ) {
                    showExchange( data.exchange, false );
                    scrollDown();
                }
                ( data.steps || [] ).forEach( function ( s ) { announce( s.text ); } );
                if ( data.thread ) { setHeader( data.thread ); }
                if ( data.drafts ) { renderDrafts( data.drafts ); }
                if ( data.status === 'queued' ) {
                    // a slow model: the turn runs in the background; follow its events
                    el.workingText.textContent = T( 'queued_background' );
                    return follow( app.current.uuid );
                }
                ExpAi.emit( 'vector:step', { data: data } );
                stepCount++;
                el.workingText.textContent = T( 'working' ) + ' ' + T( 'working_step', { '%count': stepCount + 1 } );
                if ( data.status === 'continue' && !app.stopRequested ) {
                    return step( { 'continue': '1' } );
                }
                if ( data.status === 'continue' && app.stopRequested ) {
                    return api( 'stop', { thread: app.current.uuid } ).then( function ( s ) {
                        setHeader( s.thread );
                        toast( T( 'stopped' ) );
                    } );
                }
                ExpAi.emit( 'vector:done', { data: data } );
                return data;
            } );
        }

        /**
         * Follows a background turn until the thread is no longer queued or running: its events (polled; the stream
         * handles other threads), the exchange reloaded when steps arrive, the last step as the working text. Stop
         * cancels the job (expaivector::stop); the worker honors it after the current step.
         */
        function follow( uuid ) {
            var stopSent = false;
            var lastError = '';
            var steps = 0;
            return new Promise( function ( resolve ) {
                function tick() {
                    if ( !app.current || app.current.uuid !== uuid ) { resolve( null ); return; }
                    var after = {};
                    after[uuid] = app.cursor[uuid] || 0;
                    var ask = app.stopRequested && !stopSent ? api( 'stop', { thread: uuid } ).then( function () { stopSent = true; } ) : Promise.resolve();
                    ask.then( function () {
                        return api( 'events', { threads: uuid, after: JSON.stringify( after ), open: uuid } );
                    } ).then( function ( data ) {
                        var news = ( data.events || [] ).filter( function ( ev ) { return ev.thread === uuid; } );
                        news.forEach( function ( ev ) {
                            app.cursor[uuid] = Math.max( app.cursor[uuid] || 0, ev.id );
                            if ( ev.kind === 'error' && ev.payload ) { lastError = ev.payload.message || ''; }
                            if ( ev.kind === 'step_end' && ev.payload && ev.payload.text ) {
                                steps++;
                                el.workingText.textContent = T( 'working_background', { '%count': steps } ) + ' ' + ev.payload.text;
                                announce( ev.payload.text );
                            }
                        } );
                        var st = ( data.threads || [] ).filter( function ( t ) { return t.uuid === uuid; } )[0];
                        var done = !st || ( st.state !== 'queued' && st.state !== 'running' );
                        var reload = news.length ? api( 'thread', { thread: uuid } ).then( function ( d ) {
                            ( d.history.exchanges || [] ).forEach( function ( x ) { showExchange( x, false ); } );
                            renderDrafts( d.drafts );
                            setHeader( d.thread );
                            scrollDown();
                        } ) : Promise.resolve();
                        return reload.then( function () {
                            if ( done ) {
                                if ( st && st.state === 'stopped' ) { toast( T( 'stopped' ) ); }
                                if ( st && st.state === 'failed' ) { showError( el.error, { message: lastError || T( 'background_failed' ) } ); }
                                ExpAi.emit( 'vector:done', { data: { status: st ? st.state : 'done' } } );
                                resolve( null );
                            } else {
                                window.setTimeout( tick, Math.min( data.next_ms || 1000, 3000 ) );
                            }
                        } );
                    } ).catch( function () { window.setTimeout( tick, 3000 ); } );
                }
                window.setTimeout( tick, 800 );
            } );
        }

        function run( text ) {
            if ( !app.current || !canWrite() ) { return; }
            var t = app.current;
            if ( !( t.scope.nodes.length || t.scope.subtrees.length ) ) {
                showError( el.error, { message: T( 'empty_scope' ) } );
                openScope();
                return;
            }
            showError( el.error, null );
            stepCount = 0;
            el.workingText.textContent = T( 'working' );
            setBusy( true );
            var uuid = t.uuid;
            step( text === null ? { 'continue': '1' } : { message: text } ).catch( function ( err ) {
                showError( el.error, err );
            } ).then( function () {
                setBusy( false );
                loadList();
                if ( app.current && app.current.uuid === uuid ) {
                    el.input.focus();
                    nextFollowup();
                }
            } );
        }

        function submit() {
            var text = el.input.value.trim();
            if ( !text ) { return; }
            if ( app.busy ) {
                queue( text );
                return;
            }
            el.input.value = '';
            run( text );
        }

        function queue( text ) {
            text = text || el.input.value.trim();
            if ( !text || !app.current ) { return; }
            api( 'followup', { thread: app.current.uuid, op: 'add', body: text } ).then( function ( data ) {
                el.input.value = '';
                app.followups = data.followups || [];
                renderFollowups();
                toast( T( 'queued_one' ) );
            } ).catch( function ( err ) { toast( T( 'error' ) + ' ' + ( err.message || '' ) ); } );
        }

        function nextFollowup() {
            if ( !app.followups.length || app.busy || !app.current ) { return; }
            api( 'followup', { thread: app.current.uuid, op: 'take' } ).then( function ( data ) {
                app.followups = data.followups || [];
                renderFollowups();
                if ( data.taken ) {
                    toast( T( 'sending_queued' ) );
                    run( data.taken.body );
                }
            } ).catch( function () { /* the queue stays as it is */ } );
        }

        function stop() {
            if ( !app.current ) { return; }
            if ( app.busy ) {
                app.stopRequested = true;
                el.workingText.textContent = T( 'stopping' );
                return;
            }
            api( 'stop', { thread: app.current.uuid } ).then( function ( data ) { setHeader( data.thread ); } ).catch( function () {} );
        }

        /* ------------------------------------------------------------- shelves */

        function shelve( shelf ) {
            if ( !app.current || !canWrite() ) { return; }
            var t = app.current;
            var previous = t.shelf;
            if ( shelf === 'pinned' && previous === 'pinned' ) { shelf = 'active'; }
            if ( shelf === 'pinned' || shelf === 'active' ) {
                save( { shelf: shelf } ).then( function () { toast( T( shelf === 'pinned' ? 'pinned' : 'unpinned' ) ); loadList(); } );
                return;
            }
            // settle and archive wait 5 seconds before they are sent: Undo needs no server round trip
            var row = el.list.querySelector( '[data-uuid="' + t.uuid + '"]' );
            if ( row ) { row.hidden = true; }
            var done = false;
            var timer = window.setTimeout( function () {
                done = true;
                api( 'thread_save', { thread: t.uuid, shelf: shelf } ).then( loadList ).catch( function ( err ) { toast( T( 'error' ) + ' ' + ( err.message || '' ) ); loadList(); } );
            }, 5000 );
            toast( T( shelf === 'settled' ? 'settled' : 'archived' ), function () {
                if ( done ) {
                    api( 'thread_save', { thread: t.uuid, shelf: previous } ).then( loadList );
                } else {
                    window.clearTimeout( timer );
                    if ( row ) { row.hidden = false; }
                }
                toast( T( 'restored' ) );
            } );
        }

        /* ---------------------------------------------------------------- scope */

        function openScope() {
            if ( !app.current || !canWrite() || !el.dialog ) { return; }
            var s = app.current.scope;
            app.scopeDraft = { nodes: s.nodes.slice(), subtrees: s.subtrees.slice(),
                               classes: s.classes.map( function ( c ) { return c.identifier; } ), languages: s.languages.map( function ( l ) { return l.code; } ) };
            var choices = app.boot ? app.boot.choices.scope : [ 'node', 'subtree', 'class' ];
            q( '.exp-ai-v-scope-classes', el.dialog ).innerHTML = ( app.boot ? app.boot.classes : [] ).map( function ( c ) {
                return '<label><input type="checkbox" value="' + esc( c.identifier ) + '"' + ( app.scopeDraft.classes.indexOf( c.identifier ) !== -1 ? ' checked' : '' )
                     + ( choices.indexOf( 'class' ) === -1 ? ' disabled' : '' ) + ' /> ' + esc( c.name ) + '</label>';
            } ).join( '' );
            q( '.exp-ai-v-scope-languages', el.dialog ).innerHTML = ( app.boot ? app.boot.languages : [] ).map( function ( l ) {
                return '<label><input type="checkbox" value="' + esc( l.code ) + '"' + ( app.scopeDraft.languages.indexOf( l.code ) !== -1 ? ' checked' : '' ) + ' /> '
                     + esc( l.name ) + ' <code>' + esc( l.code ) + '</code></label>';
            } ).join( '' );
            renderScopeNodes();
            showError( q( '.exp-ai-v-error', el.dialog ), null );
            q( '.exp-ai-v-node-search', el.dialog ).value = '';
            q( '.exp-ai-v-node-results', el.dialog ).hidden = true;
            if ( el.dialog.showModal ) { el.dialog.showModal(); } else { el.dialog.setAttribute( 'open', 'open' ); }
            q( '.exp-ai-v-node-search', el.dialog ).focus();
        }

        function renderScopeNodes() {
            var d = app.scopeDraft;
            var names = {};
            ( app.current.scope.nodes.concat( app.current.scope.subtrees ) ).forEach( function ( n ) { names[n.id] = n.name; } );
            $.extend( names, app.scopeNames || {} );
            var choices = app.boot ? app.boot.choices.scope : [ 'node', 'subtree', 'class' ];
            var item = function ( id, kind ) {
                var name = names[id] || '#' + id;
                return '<li><span class="exp-ai-v-chip exp-ai-v-chip-' + kind + '">' + esc( name ) + '</span>'
                     + '<label class="exp-ai-v-subtree-toggle"><input type="checkbox" data-subtree="' + id + '"' + ( kind === 'subtree' ? ' checked' : '' )
                     + ( choices.indexOf( kind === 'subtree' ? 'node' : 'subtree' ) === -1 ? ' disabled' : '' ) + ' /> ' + esc( T( 'scope_make_subtree' ) ) + '</label>'
                     + '<button type="button" class="exp-ai-v-link" data-remove="' + id + '" aria-label="' + esc( T( 'scope_remove', { '%name': name } ) ) + '">×</button></li>';
            };
            q( '.exp-ai-v-scope-nodes', el.dialog ).innerHTML = d.nodes.map( function ( id ) { return item( id, 'node' ); } ).join( '' )
                                                           + d.subtrees.map( function ( id ) { return item( id, 'subtree' ); } ).join( '' );
            q( '[data-count="classes"]', el.dialog ).textContent = d.classes.length ? '(' + d.classes.length + ')' : '';
            q( '[data-count="languages"]', el.dialog ).textContent = d.languages.length ? '(' + d.languages.length + ')' : '';
        }

        var searchTimer = null, searchSeq = 0, results = [], resultActive = -1;
        function searchNodes( text ) {
            var box = q( '.exp-ai-v-node-results', el.dialog );
            var input = q( '.exp-ai-v-node-search', el.dialog );
            window.clearTimeout( searchTimer );
            if ( text.length < 2 && !/^\d+$/.test( text ) ) {
                box.hidden = true;
                input.setAttribute( 'aria-expanded', 'false' );
                return;
            }
            var seq = ++searchSeq;
            searchTimer = window.setTimeout( function () {
                api( 'nodes', { q: text } ).then( function ( data ) {
                    if ( seq !== searchSeq ) { return; }
                    results = data.nodes || [];
                    resultActive = results.length ? 0 : -1;
                    drawResults();
                } ).catch( function () {} );
            }, 200 );
        }

        function drawResults() {
            var box = q( '.exp-ai-v-node-results', el.dialog );
            var input = q( '.exp-ai-v-node-search', el.dialog );
            box.innerHTML = results.length ? results.map( function ( n, i ) {
                return '<li role="option" id="exp-ai-v-node-' + i + '" data-index="' + i + '" aria-selected="' + ( i === resultActive ? 'true' : 'false' ) + '"'
                     + ( i === resultActive ? ' class="exp-ai-active"' : '' ) + '><strong>' + esc( n.name ) + '</strong> <span>' + esc( ( n.parent ? n.parent + ' · ' : '' ) + n['class'] ) + '</span></li>';
            } ).join( '' ) : '<li class="exp-ai-v-muted" role="presentation">' + esc( T( 'no_nodes' ) ) + '</li>';
            box.hidden = false;
            input.setAttribute( 'aria-expanded', 'true' );
            if ( resultActive >= 0 ) { input.setAttribute( 'aria-activedescendant', 'exp-ai-v-node-' + resultActive ); }
        }

        function addNode( n ) {
            var d = app.scopeDraft;
            if ( d.nodes.indexOf( n.id ) === -1 && d.subtrees.indexOf( n.id ) === -1 ) {
                var choices = app.boot ? app.boot.choices.scope : [ 'node' ];
                ( choices.indexOf( 'node' ) !== -1 ? d.nodes : d.subtrees ).push( n.id );
            }
            app.scopeNames = app.scopeNames || {};
            app.scopeNames[n.id] = n.name;
            renderScopeNodes();
            var input = q( '.exp-ai-v-node-search', el.dialog );
            input.value = '';
            q( '.exp-ai-v-node-results', el.dialog ).hidden = true;
            input.setAttribute( 'aria-expanded', 'false' );
            input.focus();
        }

        function saveScope() {
            var d = app.scopeDraft;
            d.classes = qa( '.exp-ai-v-scope-classes input:checked', el.dialog ).map( function ( i ) { return i.value; } );
            d.languages = qa( '.exp-ai-v-scope-languages input:checked', el.dialog ).map( function ( i ) { return i.value; } );
            api( 'thread_save', { thread: app.current.uuid, scope: JSON.stringify( d ) } ).then( function ( data ) {
                if ( data.lanes ) { app.lanes = data.lanes; }
                setHeader( data.thread );
                ( data.notes || [] ).forEach( function ( n ) { toast( n ); } );
                el.dialog.close ? el.dialog.close() : el.dialog.removeAttribute( 'open' );
                loadList();
                el.input.focus();
            } ).catch( function ( err ) { showError( q( '.exp-ai-v-error', el.dialog ), { message: err.message } ); } );
        }

        function bindScopeDialog() {
            if ( !el.dialog ) { return; }
            var input = q( '.exp-ai-v-node-search', el.dialog );
            input.addEventListener( 'input', function () { searchNodes( input.value.trim() ); } );
            input.addEventListener( 'keydown', function ( e ) {
                var box = q( '.exp-ai-v-node-results', el.dialog );
                if ( box.hidden || !results.length ) {
                    if ( e.key === 'Enter' ) { e.preventDefault(); }
                    return;
                }
                if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
                    e.preventDefault();
                    resultActive = ( resultActive + ( e.key === 'ArrowDown' ? 1 : -1 ) + results.length ) % results.length;
                    drawResults();
                } else if ( e.key === 'Enter' ) {
                    e.preventDefault();
                    if ( results[resultActive] ) { addNode( results[resultActive] ); }
                } else if ( e.key === 'Escape' ) {
                    e.preventDefault();
                    e.stopPropagation();
                    box.hidden = true;
                }
            } );
            el.dialog.addEventListener( 'click', function ( e ) {
                var r = e.target.closest( '.exp-ai-v-node-results [data-index]' );
                var rm = e.target.closest( '[data-remove]' );
                if ( r ) { addNode( results[parseInt( r.getAttribute( 'data-index' ), 10 )] ); }
                if ( rm ) {
                    var id = parseInt( rm.getAttribute( 'data-remove' ), 10 );
                    app.scopeDraft.nodes = app.scopeDraft.nodes.filter( function ( n ) { return n !== id; } );
                    app.scopeDraft.subtrees = app.scopeDraft.subtrees.filter( function ( n ) { return n !== id; } );
                    renderScopeNodes();
                }
                if ( e.target.closest( '[data-dialog="save"]' ) ) { saveScope(); }
                if ( e.target.closest( '[data-dialog="cancel"]' ) ) { el.dialog.close ? el.dialog.close() : el.dialog.removeAttribute( 'open' ); }
            } );
            el.dialog.addEventListener( 'change', function ( e ) {
                var t = e.target.closest( '[data-subtree]' );
                if ( !t ) { return; }
                var id = parseInt( t.getAttribute( 'data-subtree' ), 10 );
                var d = app.scopeDraft;
                d.nodes = d.nodes.filter( function ( n ) { return n !== id; } );
                d.subtrees = d.subtrees.filter( function ( n ) { return n !== id; } );
                ( t.checked ? d.subtrees : d.nodes ).push( id );
                renderScopeNodes();
            } );
        }

        /* ------------------------------------------------------- events: poll, SSE */

        function visible() { return document.visibilityState !== 'hidden'; }

        function schedulePoll( ms ) {
            window.clearTimeout( app.pollTimer );
            if ( !visible() ) { return; }
            app.pollTimer = window.setTimeout( poll, ms );
        }

        function poll() {
            if ( !visible() ) { return; }
            var watched = app.rows.filter( function ( r ) { return r.state === 'queued' || r.state === 'running'; } ).map( function ( r ) { return r.uuid; } );
            if ( app.current && watched.indexOf( app.current.uuid ) === -1 && !app.stream ) { watched.unshift( app.current.uuid ); }
            if ( !watched.length ) {
                schedulePoll( app.boot ? app.boot.poll.idle : 15000 );
                return;
            }
            var after = {};
            watched.forEach( function ( u ) { after[u] = app.cursor[u] || 0; } );
            api( 'events', { threads: watched.join( ',' ), after: JSON.stringify( after ), open: app.current ? app.current.uuid : '' } ).then( function ( data ) {
                handleEvents( data.events || [], data.threads || [] );
                schedulePoll( data.next_ms || 15000 );
            } ).catch( function () { schedulePoll( 15000 ); } );
        }

        var reloadTimer = null;
        function handleEvents( events, states ) {
            var changed = false;
            states.forEach( function ( s ) {
                app.rows.forEach( function ( r ) {
                    if ( r.uuid === s.uuid && r.state !== s.state ) { r.state = s.state; changed = true; }
                } );
            } );
            events.forEach( function ( ev ) {
                app.cursor[ev.thread] = Math.max( app.cursor[ev.thread] || 0, ev.id );
                // a thread that works elsewhere (the panel, another window): show its new steps when it is open here
                if ( app.current && ev.thread === app.current.uuid && !app.busy ) {
                    window.clearTimeout( reloadTimer );
                    reloadTimer = window.setTimeout( function () { open( app.current.uuid, false ); }, 400 );
                }
            } );
            if ( changed ) { renderList(); }
        }

        function connectStream() {
            if ( app.stream ) { app.stream.close(); app.stream = null; }
            var usable = app.boot && app.boot.transport === 'sse' && window.EventSource && app.current;
            try { usable = usable && window.sessionStorage.getItem( 'exp-ai-v-sse' ) !== 'off'; } catch ( e ) { /* storage may be off */ }
            if ( !usable ) { return; }
            var uuid = app.current.uuid;
            var source = new window.EventSource( url( 'expai/stream/(thread)/' + uuid + '/(after)/' + ( app.cursor[uuid] || 0 ) ), { withCredentials: true } );
            var first = false;
            // no first line within 2 seconds: this server does not stream; poll for the rest of the session
            var guard = window.setTimeout( function () {
                if ( !first ) {
                    source.close();
                    app.stream = null;
                    try { window.sessionStorage.setItem( 'exp-ai-v-sse', 'off' ); } catch ( e ) { /* storage may be off */ }
                    schedulePoll( 0 );
                }
            }, 2000 );
            source.onopen = function () { first = true; window.clearTimeout( guard ); };
            source.addEventListener( 'thread', function ( m ) {
                try { handleEvents( [ JSON.parse( m.data ) ], [] ); } catch ( e ) { /* a broken event is skipped */ }
            } );
            source.onerror = function () {
                if ( !first ) { return; }
                // the browser reconnects with Last-Event-ID; a 204 (all streams taken) ends it: poll
                if ( source.readyState === 2 ) { app.stream = null; schedulePoll( 0 ); }
            };
            app.stream = source;
        }

        /* ------------------------------------------------------------- keyboard */

        var escAt = 0;
        function items() { return qa( '.exp-ai-v-x', el.log ); }
        function moveItem( delta ) {
            var list = items();
            if ( !list.length ) { return; }
            app.activeItem = Math.max( 0, Math.min( list.length - 1, ( app.activeItem < 0 ? list.length : app.activeItem ) + delta ) );
            list.forEach( function ( li, i ) { li.classList.toggle( 'exp-ai-v-x-active', i === app.activeItem ); } );
            list[app.activeItem].focus();
            list[app.activeItem].scrollIntoView( { block: 'nearest' } );
        }
        function switchThread( delta ) {
            var order = orderedRows();
            if ( !order.length ) { return; }
            var i = app.current ? order.indexOf( app.current.uuid ) : -1;
            var next = order[Math.max( 0, Math.min( order.length - 1, i + delta ) )];
            if ( next && ( !app.current || next !== app.current.uuid ) ) { open( next ); }
        }
        function showTab( name ) {
            qa( '.exp-ai-v-tabs [role="tab"]' ).forEach( function ( b ) {
                var on = b.getAttribute( 'data-tab' ) === name;
                b.setAttribute( 'aria-selected', on ? 'true' : 'false' );
                b.tabIndex = on ? 0 : -1;
            } );
            qa( '.exp-ai-v-pane' ).forEach( function ( p ) { p.hidden = p.getAttribute( 'data-pane' ) !== name; } );
            try { window.localStorage.setItem( 'exp-ai-v-tab', name ); } catch ( e ) { /* storage may be off */ }
        }
        function closeDrawers() {
            root.classList.remove( 'exp-ai-v-threads-open', 'exp-ai-v-side-open' );
            qa( '.exp-ai-v-threads-toggle, .exp-ai-v-side-toggle' ).forEach( function ( b ) { b.setAttribute( 'aria-expanded', 'false' ); } );
        }
        function toggleDrawer( cls, button ) {
            var on = !root.classList.contains( cls );
            closeDrawers();
            root.classList.toggle( cls, on );
            if ( button ) { button.setAttribute( 'aria-expanded', on ? 'true' : 'false' ); }
        }
        function placeholder() { toast( T( 'item_placeholder' ) ); }

        var actions = {
            palette: function () { if ( window.ExpAiPalette ) { window.ExpAiPalette.open(); } },
            help: function () { if ( window.ExpAiPalette ) { window.ExpAiPalette.help(); } },
            run: function () { if ( app.busy ) { queue(); } else { submit(); } },
            queue: function () { if ( app.busy ) { queue(); } else { submit(); } },
            stop: stop,
            next: function () { moveItem( 1 ); },
            previous: function () { moveItem( -1 ); },
            accept: placeholder,
            reject: placeholder,
            thread_next: function () { switchThread( 1 ); },
            thread_previous: function () { switchThread( -1 ); },
            new_thread: function () { newThread( 0 ); },
            new_thread_here: function () { newThread( parseInt( root.getAttribute( 'data-node' ), 10 ) || 0 ); },
            mode: function () { if ( app.current && canWrite() ) { save( { run_mode: app.current.run_mode === 'plan' ? 'write' : 'plan' } ); } },
            model: function () { el.model.focus(); },
            lane: function () { el.lane.focus(); },
            scope: openScope,
            tab_plan: function () { showTab( 'plan' ); },
            tab_diff: function () { showTab( 'diff' ); },
            tab_cost: function () { showTab( 'cost' ); },
            pin: function () { shelve( 'pinned' ); },
            settle: function () { shelve( 'settled' ); },
            archive: function () { shelve( 'archived' ); },
            toggle_threads: function ( b ) { toggleDrawer( 'exp-ai-v-threads-open', b ); },
            toggle_side: function ( b ) { toggleDrawer( 'exp-ai-v-side-open', b ); }
        };

        document.addEventListener( 'keydown', function ( e ) {
            if ( e.defaultPrevented || ( window.ExpAiPalette && window.ExpAiPalette.isOpen() ) || ( el.dialog && el.dialog.open ) ) { return; }
            var ids = Object.keys( app.bindings );
            for ( var i = 0; i < ids.length; i++ ) {
                var id = ids[i], b = app.bindings[id];
                if ( !actions[id] || !Keys.allowed( e, b.when, b.keys, root ) ) { continue; }
                if ( Keys.matches( e, b.keys ) ) {
                    e.preventDefault();
                    if ( id === 'stop' ) { escAt = 0; }
                    actions[id]();
                    return;
                }
                if ( id === 'stop' && Keys.half( b.keys ) && app.busy && Date.now() - escAt > 700 ) {
                    escAt = Date.now();
                    toast( T( 'stop_again' ) );
                }
            }
        } );

        /* --------------------------------------------------------------- wiring */

        root.addEventListener( 'click', function ( e ) {
            var cmd = e.target.closest( '[data-command]' );
            if ( cmd && actions[cmd.getAttribute( 'data-command' )] && !cmd.disabled ) {
                e.preventDefault();
                actions[cmd.getAttribute( 'data-command' )]( cmd );
                return;
            }
            var row = e.target.closest( '.exp-ai-v-row' );
            if ( row ) { open( row.getAttribute( 'data-uuid' ) ); return; }
            var mode = e.target.closest( '.exp-ai-v-seg [data-mode]' );
            if ( mode && !mode.disabled && app.current && mode.getAttribute( 'data-mode' ) !== app.current.run_mode ) { save( { run_mode: mode.getAttribute( 'data-mode' ) } ); return; }
            var tab = e.target.closest( '.exp-ai-v-tabs [role="tab"]' );
            if ( tab ) { showTab( tab.getAttribute( 'data-tab' ) ); return; }
            var drop = e.target.closest( '[data-drop]' );
            if ( drop && app.current ) {
                api( 'followup', { thread: app.current.uuid, op: 'drop', id: drop.getAttribute( 'data-drop' ) } ).then( function ( data ) {
                    app.followups = data.followups || [];
                    renderFollowups();
                } );
                return;
            }
            var shelfLink = e.target.closest( '.exp-ai-v-shelves a' );
            if ( shelfLink ) {
                e.preventDefault();
                app.shelf = shelfLink.getAttribute( 'data-shelf' ) || '';
                window.history.pushState( {}, '', shelfLink.href );
                loadList();
            }
        } );
        qa( '.exp-ai-v-tabs [role="tab"]' ).forEach( function ( tab, i, all ) {
            tab.addEventListener( 'keydown', function ( e ) {
                if ( e.key === 'ArrowRight' || e.key === 'ArrowLeft' ) {
                    e.preventDefault();
                    var next = all[( i + ( e.key === 'ArrowRight' ? 1 : -1 ) + all.length ) % all.length];
                    showTab( next.getAttribute( 'data-tab' ) );
                    next.focus();
                }
            } );
        } );
        el.list.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
                e.preventDefault();
                switchThread( e.key === 'ArrowDown' ? 1 : -1 );
            }
        } );
        q( '.exp-ai-v-undo' ).addEventListener( 'click', function () {
            if ( toastUndo ) { var u = toastUndo; toastUndo = null; u(); }
        } );
        q( '.exp-ai-v-composer' ).addEventListener( 'submit', function ( e ) { e.preventDefault(); submit(); } );
        el.older.addEventListener( 'click', function () {
            if ( !app.current ) { return; }
            api( 'history', { thread: app.current.uuid, before: app.oldest } ).then( function ( page ) {
                ( page.exchanges || [] ).slice().reverse().forEach( function ( x ) { showExchange( x, true ); } );
                el.older.hidden = !page.has_older;
            } );
        } );
        var titleTimer = null;
        el.title.addEventListener( 'input', function () {
            window.clearTimeout( titleTimer );
            titleTimer = window.setTimeout( function () { save( { title: el.title.value }, false ); }, 700 );
        } );
        el.title.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); el.input.focus(); } } );
        el.model.addEventListener( 'change', function () { save( { model_alias: el.model.value } ); } );
        el.lane.addEventListener( 'change', function () { save( { lane: el.lane.value } ); } );
        el.review.addEventListener( 'change', function () { save( { review_policy: el.review.value } ); } );
        var filterTimer = null;
        el.filter.addEventListener( 'input', function () {
            window.clearTimeout( filterTimer );
            filterTimer = window.setTimeout( loadList, 250 );
        } );
        bindScopeDialog();
        window.addEventListener( 'popstate', function () {
            var m = window.location.pathname.match( /\(thread\)\/([0-9a-f-]{36})/ );
            if ( m ) { open( m[1], false ); }
        } );
        document.addEventListener( 'visibilitychange', function () {
            if ( visible() ) {
                schedulePoll( 0 );
                if ( app.current && !app.stream ) { connectStream(); }
            } else {
                window.clearTimeout( app.pollTimer );
                if ( app.stream ) { app.stream.close(); app.stream = null; }
            }
        } );

        // the palette knows Vector's commands
        function label( id ) { return function () { return ( window.ExpAiPalette ? window.ExpAiPalette.config().labels[id] : '' ) || id; }; }
        [ 'new_thread', 'run', 'stop', 'mode', 'scope', 'lane', 'thread_next', 'thread_previous', 'tab_plan', 'tab_diff', 'tab_cost' ].forEach( function ( id ) {
            ExpAi.commands.register( 'vector_' + id, { label: label( id ), binding: id, run: function () { window.setTimeout( function () { actions[id](); }, 0 ); },
                available: function () { return id === 'new_thread' || !!app.current; } } );
        } );

        window.ExpAiVectorApp = {
            open: function ( uuid ) { open( uuid ); },
            setModel: function ( alias ) {
                if ( !app.current || !canWrite() ) { return false; }
                save( { model_alias: alias } );
                return true;
            },
            toast: function ( text ) { toast( text ); },
            bindingsChanged: function ( bindings ) { app.bindings = bindings; paintKeys(); }
        };

        /* --------------------------------------------------------------- start */

        Promise.all( [ api( 'bootstrap', {} ), api( 'palette', {} ).catch( function () { return null; } ) ] ).then( function ( r ) {
            app.boot = r[0];
            app.bindings = app.boot.bindings || {};
            if ( r[1] && window.ExpAiPalette ) { window.ExpAiPalette.configure( r[1] ); }
            paintKeys();
            var tab = root.getAttribute( 'data-tab' );
            try { tab = tab || window.localStorage.getItem( 'exp-ai-v-tab' ); } catch ( e ) { /* storage may be off */ }
            showTab( tab && q( '[data-pane="' + tab + '"]' ) ? tab : ( qa( '.exp-ai-v-pane' )[0] || { getAttribute: function () { return ''; } } ).getAttribute( 'data-pane' ) );
            return loadList();
        } ).then( function () {
            var uuid = root.getAttribute( 'data-thread' );
            var node = parseInt( root.getAttribute( 'data-node' ), 10 );
            if ( uuid ) {
                open( uuid, false );
            } else if ( node ) {
                // (node)/<id>: the editor's newest active thread on that node, else a new one there
                var existing = app.rows.filter( function ( r ) { return r.node_id === node && r.origin === 'vector' && r.shelf !== 'archived'; } )[0];
                if ( existing ) { open( existing.uuid ); } else { newThread( node ); }
            }
            schedulePoll( app.boot ? app.boot.poll.idle : 15000 );
        } ).catch( function ( err ) {
            el.list.innerHTML = '<p class="exp-ai-v-error" role="alert">' + esc( T( 'error' ) + ' ' + ( err.message || '' ) ) + '</p>';
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
})( window, document );
