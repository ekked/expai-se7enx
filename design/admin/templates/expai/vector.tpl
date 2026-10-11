{* expai/vector: the Vector workspace (doc/vector.md). The shell only: threads, the conversation and the panes are
   loaded by expai_vector.js through ezjscore (expaivector::*), so nothing personal is part of this page.
   Left: threads. Center: the open thread (scope, mode, model, lane, review; exchanges and steps; queue; composer).
   Right: the panes of [VectorSettings] Panes[] (plan, diff, cost). *}
{ezcss_require( array( 'expai_vector.css' ) )}
{ezscript_require( array( 'expai_palette.js', 'expai_vector.js' ) )}
{def $exp_ai_i18n = 'extension/expai/vector'}
<div class="exp-ai exp-ai-vector" id="exp-ai-vector" data-thread="{$thread_uuid|wash}" data-node="{if $start_node}{$start_node.node_id}{/if}"
     data-tab="{$tab|wash}" data-shelf="{$shelf|wash}">

    <header class="exp-ai-v-head">
        <div class="exp-ai-v-brand">
            <span class="exp-ai-mark" aria-hidden="true"></span>
            <h1>{'Vector'|i18n( $exp_ai_i18n )}</h1>
            <span class="exp-ai-v-brand-sub">{'Exponential Intelligence'|i18n( $exp_ai_i18n )}</span>
        </div>
        <button type="button" class="exp-ai-v-search" data-command="palette" aria-keyshortcuts="Control+K Meta+K">
            <span class="exp-ai-v-search-icon" aria-hidden="true"></span>
            <span class="exp-ai-v-search-text">{'Search threads, commands, nodes...'|i18n( $exp_ai_i18n )}</span>
            <kbd class="exp-ai-v-kbd" data-keys="palette"></kbd>
        </button>
        <div class="exp-ai-v-head-end">
            <button type="button" class="exp-ai-v-icon exp-ai-v-threads-toggle" data-command="toggle_threads" aria-expanded="false" aria-controls="exp-ai-v-threads"
                    title="{'Threads'|i18n( $exp_ai_i18n )}"><span aria-hidden="true">&#9776;</span><span class="exp-ai-sr">{'Threads'|i18n( $exp_ai_i18n )}</span></button>
            <button type="button" class="exp-ai-v-icon exp-ai-v-side-toggle" data-command="toggle_side" aria-expanded="false" aria-controls="exp-ai-v-side"
                    title="{'Plan, changes and cost'|i18n( $exp_ai_i18n )}"><span aria-hidden="true">&#9707;</span><span class="exp-ai-sr">{'Plan, changes and cost'|i18n( $exp_ai_i18n )}</span></button>
            <button type="button" class="exp-ai-v-icon" data-command="help" aria-keyshortcuts="Shift+?" title="{'Keyboard shortcuts'|i18n( $exp_ai_i18n )}">?</button>
        </div>
    </header>

{if or( $vector_enabled|not, $tables_ready|not, $ai_ready|not )}
    <p class="exp-ai-v-notice" role="status">
    {if $vector_enabled|not}{'Vector is switched off on this site.'|i18n( $exp_ai_i18n )}
    {elseif $tables_ready|not}{'The database of Exponential Intelligence needs an update before Vector can be used. Please ask an administrator.'|i18n( $exp_ai_i18n )}
    {else}{'Exponential Intelligence is switched off or not configured: threads can be read, but not run.'|i18n( $exp_ai_i18n )}{/if}
    </p>
{/if}

{if and( $vector_enabled, $tables_ready )}
    <div class="exp-ai-v-grid">

        <nav class="exp-ai-v-threads" id="exp-ai-v-threads" aria-label="{'Threads'|i18n( $exp_ai_i18n )}">
            <div class="exp-ai-v-threads-top">
                <button type="button" class="exp-ai-v-btn exp-ai-v-btn-primary exp-ai-v-new" data-command="new_thread">
                    <span aria-hidden="true">+</span> {'New thread'|i18n( $exp_ai_i18n )} <kbd class="exp-ai-v-kbd" data-keys="new_thread"></kbd>
                </button>
                <label class="exp-ai-sr" for="exp-ai-v-filter">{'Filter threads'|i18n( $exp_ai_i18n )}</label>
                <input type="search" id="exp-ai-v-filter" class="exp-ai-v-filter" placeholder="{'Filter threads'|i18n( $exp_ai_i18n )|wash}" autocomplete="off" />
            </div>
            <div class="exp-ai-v-list" role="listbox" tabindex="0" aria-label="{'Threads'|i18n( $exp_ai_i18n )}"></div>
            <div class="exp-ai-v-shelves">
                <a href={'expai/vector'|ezurl} data-shelf="">{'Active'|i18n( $exp_ai_i18n )}</a>
                <a href={'expai/vector/(shelf)/settled'|ezurl} data-shelf="settled">{'Settled'|i18n( $exp_ai_i18n )} <span class="exp-ai-v-count" data-count="settled"></span></a>
                <a href={'expai/vector/(shelf)/archived'|ezurl} data-shelf="archived">{'Archived'|i18n( $exp_ai_i18n )} <span class="exp-ai-v-count" data-count="archived"></span></a>
            </div>
        </nav>

        <main class="exp-ai-v-center" aria-label="{'Thread'|i18n( $exp_ai_i18n )}">
            <div class="exp-ai-v-empty">
                <span class="exp-ai-mark exp-ai-v-empty-mark" aria-hidden="true"></span>
                <h2>{'Start a thread'|i18n( $exp_ai_i18n )}</h2>
                <p>{'Choose what it may touch (nodes, subtrees, classes, languages), then say what to do. Bulk work starts with a plan.'|i18n( $exp_ai_i18n )}</p>
                <button type="button" class="exp-ai-v-btn exp-ai-v-btn-primary" data-command="new_thread">{'New thread'|i18n( $exp_ai_i18n )}</button>
{if $start_node}
                <button type="button" class="exp-ai-v-btn" data-command="new_thread_here">{'New thread on "%name"'|i18n( $exp_ai_i18n,, hash( '%name', $start_node.name ) )|wash}</button>
{/if}
            </div>

            <section class="exp-ai-v-thread" hidden="hidden" aria-labelledby="exp-ai-v-title">
                <header class="exp-ai-v-thread-head">
                    <div class="exp-ai-v-title-row">
                        <label class="exp-ai-sr" for="exp-ai-v-title">{'Title'|i18n( $exp_ai_i18n )}</label>
                        <input type="text" id="exp-ai-v-title" class="exp-ai-v-title" maxlength="255" autocomplete="off" />
                        <span class="exp-ai-v-state" aria-live="polite"></span>
                        <span class="exp-ai-v-thread-actions">
                            <button type="button" class="exp-ai-v-icon" data-command="pin" title="{'Pin'|i18n( $exp_ai_i18n )}" aria-pressed="false"><span aria-hidden="true">&#9733;</span><span class="exp-ai-sr">{'Pin'|i18n( $exp_ai_i18n )}</span></button>
                            <button type="button" class="exp-ai-v-icon" data-command="settle" title="{'Settle'|i18n( $exp_ai_i18n )}"><span aria-hidden="true">&#10003;</span><span class="exp-ai-sr">{'Settle'|i18n( $exp_ai_i18n )}</span></button>
                            <button type="button" class="exp-ai-v-icon" data-command="archive" title="{'Archive'|i18n( $exp_ai_i18n )}"><span aria-hidden="true">&#8681;</span><span class="exp-ai-sr">{'Archive'|i18n( $exp_ai_i18n )}</span></button>
                        </span>
                    </div>
                    <p class="exp-ai-v-readonly" hidden="hidden">{'You see this thread because a version it wrote waits for your approval. Only its owner can change it.'|i18n( $exp_ai_i18n )}</p>
                    <div class="exp-ai-v-controls">
                        <div class="exp-ai-v-scope">
                            <span class="exp-ai-v-label">{'Scope'|i18n( $exp_ai_i18n )}</span>
                            <span class="exp-ai-v-chips"></span>
                            <button type="button" class="exp-ai-v-link" data-command="scope">{'Change'|i18n( $exp_ai_i18n )} <kbd class="exp-ai-v-kbd" data-keys="scope"></kbd></button>
                        </div>
                        <div class="exp-ai-v-settings">
                            <div class="exp-ai-v-seg" role="radiogroup" aria-label="{'Mode'|i18n( $exp_ai_i18n )}">
                                <button type="button" role="radio" data-mode="plan" aria-checked="false">{'Plan'|i18n( $exp_ai_i18n )}</button>
                                <button type="button" role="radio" data-mode="write" aria-checked="false">{'Write'|i18n( $exp_ai_i18n )}</button>
                            </div>
                            <label class="exp-ai-v-field"><span>{'Model'|i18n( $exp_ai_i18n )}</span><select class="exp-ai-v-model"></select></label>
                            <label class="exp-ai-v-field"><span>{'Lane'|i18n( $exp_ai_i18n )}</span><select class="exp-ai-v-lane"></select></label>
                            <label class="exp-ai-v-field"><span>{'Review'|i18n( $exp_ai_i18n )}</span><select class="exp-ai-v-review">
                                <option value="staged">{'Staged'|i18n( $exp_ai_i18n )}</option>
                                <option value="direct">{'Direct'|i18n( $exp_ai_i18n )}</option>
                            </select></label>
                        </div>
                        <p class="exp-ai-v-note" aria-live="polite"></p>
                    </div>
                </header>

                <div class="exp-ai-v-scroll">
                    <button type="button" class="exp-ai-v-link exp-ai-v-older" hidden="hidden">{'Show older'|i18n( $exp_ai_i18n )}</button>
                    <ol class="exp-ai-v-log" aria-label="{'Conversation'|i18n( $exp_ai_i18n )}"></ol>
                    <p class="exp-ai-v-working" hidden="hidden"><span class="exp-ai-spinner" aria-hidden="true"></span> <span class="exp-ai-v-working-text"></span></p>
                    <p class="exp-ai-v-error" role="alert" hidden="hidden"></p>
                </div>
                <div class="exp-ai-sr" aria-live="polite" data-live="steps"></div>

                <div class="exp-ai-v-queue" hidden="hidden">
                    <span class="exp-ai-v-label">{'Queued'|i18n( $exp_ai_i18n )}</span>
                    <ol class="exp-ai-v-queue-list"></ol>
                </div>
                <form class="exp-ai-v-composer" onsubmit="return false;">
                    <label class="exp-ai-sr" for="exp-ai-v-message">{'Your request'|i18n( $exp_ai_i18n )}</label>
                    <textarea id="exp-ai-v-message" class="exp-ai-v-input" rows="3" maxlength="{ezini( 'AssistantSettings', 'MaxMessageLength', 'expai.ini' )|wash}"
                              placeholder="{'What should happen in this scope?'|i18n( $exp_ai_i18n )|wash}"></textarea>
                    <div class="exp-ai-v-composer-bar">
                        <span class="exp-ai-v-hint"></span>
                        <button type="button" class="exp-ai-v-btn exp-ai-v-queue-btn" data-command="queue" hidden="hidden">{'Queue'|i18n( $exp_ai_i18n )} <kbd class="exp-ai-v-kbd" data-keys="queue"></kbd></button>
                        <button type="button" class="exp-ai-v-btn exp-ai-v-stop" data-command="stop" hidden="hidden">{'Stop'|i18n( $exp_ai_i18n )} <kbd class="exp-ai-v-kbd" data-keys="stop"></kbd></button>
                        <button type="submit" class="exp-ai-v-btn exp-ai-v-btn-primary exp-ai-v-run" data-command="run">{'Run'|i18n( $exp_ai_i18n )} <kbd class="exp-ai-v-kbd" data-keys="run"></kbd></button>
                    </div>
                </form>
            </section>
        </main>

        <aside class="exp-ai-v-side" id="exp-ai-v-side" aria-label="{'Plan, changes and cost'|i18n( $exp_ai_i18n )}">
            <div class="exp-ai-v-tabs" role="tablist">
{foreach $panes as $exp_ai_pane => $exp_ai_template}
                <button type="button" role="tab" id="exp-ai-v-tab-{$exp_ai_pane}" aria-controls="exp-ai-v-pane-{$exp_ai_pane}" aria-selected="false" data-tab="{$exp_ai_pane}">
                    {switch match=$exp_ai_pane}{case match='plan'}{'Plan'|i18n( $exp_ai_i18n )}{/case}{case match='diff'}{'Changes'|i18n( $exp_ai_i18n )}{/case}{case match='cost'}{'Cost'|i18n( $exp_ai_i18n )}{/case}{case}{$exp_ai_pane|wash}{/case}{/switch}
                    <span class="exp-ai-v-tab-count" data-tab-count="{$exp_ai_pane}"></span>
                </button>
{/foreach}
            </div>
{foreach $panes as $exp_ai_pane => $exp_ai_template}
            <div class="exp-ai-v-pane" role="tabpanel" id="exp-ai-v-pane-{$exp_ai_pane}" aria-labelledby="exp-ai-v-tab-{$exp_ai_pane}" data-pane="{$exp_ai_pane}" hidden="hidden" tabindex="0">
                {include uri=concat( 'design:', $exp_ai_template ) pane=$exp_ai_pane cost_enabled=$cost_enabled}
            </div>
{/foreach}
        </aside>
    </div>

    <dialog class="exp-ai exp-ai-v-dialog exp-ai-v-scope-dialog" aria-labelledby="exp-ai-v-scope-title">
        <form method="dialog" class="exp-ai-v-dialog-body">
            <h2 id="exp-ai-v-scope-title">{'Scope of this thread'|i18n( $exp_ai_i18n )}</h2>
            <p class="exp-ai-v-dialog-intro">{'What the thread may read and change. A node is one object; a subtree is a node and everything below it.'|i18n( $exp_ai_i18n )}</p>
            <label class="exp-ai-v-label" for="exp-ai-v-node-search">{'Add a node'|i18n( $exp_ai_i18n )}</label>
            <input type="search" id="exp-ai-v-node-search" class="exp-ai-v-node-search" autocomplete="off"
                   placeholder="{'Name or node id'|i18n( $exp_ai_i18n )|wash}" role="combobox" aria-expanded="false" aria-controls="exp-ai-v-node-results" aria-autocomplete="list" />
            <ul class="exp-ai-v-node-results" id="exp-ai-v-node-results" role="listbox" hidden="hidden"></ul>
            <ul class="exp-ai-v-scope-nodes" aria-label="{'Nodes and subtrees'|i18n( $exp_ai_i18n )}"></ul>
            <details class="exp-ai-v-scope-more">
                <summary>{'Classes'|i18n( $exp_ai_i18n )} <span class="exp-ai-v-count" data-count="classes"></span></summary>
                <div class="exp-ai-v-checks exp-ai-v-scope-classes"></div>
            </details>
            <details class="exp-ai-v-scope-more">
                <summary>{'Languages'|i18n( $exp_ai_i18n )} <span class="exp-ai-v-count" data-count="languages"></span></summary>
                <div class="exp-ai-v-checks exp-ai-v-scope-languages"></div>
            </details>
            <p class="exp-ai-v-error" role="alert" hidden="hidden"></p>
            <div class="exp-ai-v-dialog-bar">
                <button type="button" class="exp-ai-v-btn" value="cancel" data-dialog="cancel">{'Cancel'|i18n( $exp_ai_i18n )}</button>
                <button type="button" class="exp-ai-v-btn exp-ai-v-btn-primary" data-dialog="save">{'Save scope'|i18n( $exp_ai_i18n )}</button>
            </div>
        </form>
    </dialog>

    <div class="exp-ai-v-toast" role="status" aria-live="polite" hidden="hidden"><span class="exp-ai-v-toast-text"></span> <button type="button" class="exp-ai-v-link exp-ai-v-undo">{'Undo'|i18n( $exp_ai_i18n )}</button></div>
{/if}

    <script type="application/json" class="exp-ai-v-strings">{json_encode( hash(
        'new_thread', 'New thread'|i18n( 'extension/expai/threads' ),
        'section_working', 'Working'|i18n( 'extension/expai/threads' ),
        'section_needs', 'Needs you'|i18n( 'extension/expai/threads' ),
        'section_pinned', 'Pinned'|i18n( 'extension/expai/threads' ),
        'section_recent', 'Recent'|i18n( 'extension/expai/threads' ),
        'section_shared', 'Waiting for your approval'|i18n( 'extension/expai/threads' ),
        'section_settled', 'Settled'|i18n( 'extension/expai/threads' ),
        'section_archived', 'Archived'|i18n( 'extension/expai/threads' ),
        'no_threads', 'No threads yet.'|i18n( 'extension/expai/threads' ),
        'no_match', 'No thread matches.'|i18n( 'extension/expai/threads' ),
        'state_idle', 'Idle'|i18n( 'extension/expai/threads' ),
        'state_queued', 'Queued'|i18n( 'extension/expai/threads' ),
        'state_running', 'Working'|i18n( 'extension/expai/threads' ),
        'state_waiting_input', 'Waits for you'|i18n( 'extension/expai/threads' ),
        'state_review', 'To review'|i18n( 'extension/expai/threads' ),
        'state_done', 'Done'|i18n( 'extension/expai/threads' ),
        'state_failed', 'Failed'|i18n( 'extension/expai/threads' ),
        'state_stopped', 'Stopped'|i18n( 'extension/expai/threads' ),
        'origin_panel', 'Intelligence tab'|i18n( 'extension/expai/threads' ),
        'origin_vector', 'Vector'|i18n( 'extension/expai/threads' ),
        'unread', 'New activity'|i18n( 'extension/expai/threads' ),
        'pinned', 'Pinned.'|i18n( $exp_ai_i18n ),
        'unpinned', 'Unpinned.'|i18n( $exp_ai_i18n ),
        'settled', 'Settled.'|i18n( $exp_ai_i18n ),
        'archived', 'Archived.'|i18n( $exp_ai_i18n ),
        'restored', 'Back in the active list.'|i18n( $exp_ai_i18n ),
        'scope_none', 'Nothing chosen yet'|i18n( $exp_ai_i18n ),
        'scope_node', 'Node'|i18n( $exp_ai_i18n ),
        'scope_subtree', 'Subtree'|i18n( $exp_ai_i18n ),
        'scope_remove', 'Remove %name'|i18n( $exp_ai_i18n ),
        'scope_make_subtree', 'Include everything below'|i18n( $exp_ai_i18n ),
        'scope_kind_denied', 'Your role does not allow this kind of scope.'|i18n( $exp_ai_i18n ),
        'bulk', 'Bulk'|i18n( $exp_ai_i18n ),
        'bulk_note', 'Bulk work starts with a plan: approve it before anything is written.'|i18n( $exp_ai_i18n ),
        'plan_note', 'Plan mode: Vector reads and proposes; nothing is written.'|i18n( $exp_ai_i18n ),
        'staged_note', 'Staged review: what Vector proposes waits in the Changes tab; nothing is written until you accept it and write it to drafts.'|i18n( $exp_ai_i18n ),
        'direct_note', 'Direct: drafts are written at once (never published); you decide in the drafts list.'|i18n( $exp_ai_i18n ),
        'direct_denied', 'Direct review needs one object in one language and a role that allows it.'|i18n( $exp_ai_i18n ),
        'model_default', 'Your default (%model)'|i18n( $exp_ai_i18n ),
        'model_reads_only', 'reads only'|i18n( $exp_ai_i18n ),
        'lane_batch_note', 'batch: approved plans only'|i18n( $exp_ai_i18n ),
        'lane_foreground', 'Runs in this window use the standard lane; the chosen lane applies to background runs.'|i18n( $exp_ai_i18n ),
        'working', 'Vector is working...'|i18n( $exp_ai_i18n ),
        'working_step', 'Step %count'|i18n( $exp_ai_i18n ),
        'stopping', 'Stop requested...'|i18n( $exp_ai_i18n ),
        'stopped', 'Stopped. The steps so far are kept.'|i18n( $exp_ai_i18n ),
        'stop_again', 'Press Esc once more to stop.'|i18n( $exp_ai_i18n ),
        'queued_one', 'Queued: it is sent when the current turn ends.'|i18n( $exp_ai_i18n ),
        'queue_remove', 'Remove from the queue'|i18n( $exp_ai_i18n ),
        'sending_queued', 'Sending the next queued message...'|i18n( $exp_ai_i18n ),
        'hint_idle', '%run to run'|i18n( $exp_ai_i18n ),
        'hint_running', '%queue to queue, %stop to stop'|i18n( $exp_ai_i18n ),
        'you', 'You'|i18n( $exp_ai_i18n ),
        'assistant', 'Vector'|i18n( $exp_ai_i18n ),
        'lookups', '%count lookup(s)'|i18n( $exp_ai_i18n ),
        'drafts_written', 'Drafts written'|i18n( $exp_ai_i18n ),
        'no_reply', '(no answer yet)'|i18n( $exp_ai_i18n ),
        'interrupted', 'The last answer was interrupted.'|i18n( $exp_ai_i18n ),
        'continue', 'Continue'|i18n( $exp_ai_i18n ),
        'error', 'Something went wrong:'|i18n( $exp_ai_i18n ),
        'timeout', 'The server did not answer in time. Please try again.'|i18n( $exp_ai_i18n ),
        'not_found', 'This thread does not exist or you may not open it.'|i18n( $exp_ai_i18n ),
        'empty_scope', 'Choose the scope first.'|i18n( $exp_ai_i18n ),
        'search_min', 'Type at least 2 characters or a node id.'|i18n( $exp_ai_i18n ),
        'no_nodes', 'No readable node found.'|i18n( $exp_ai_i18n ),
        'edit', 'Edit'|i18n( $exp_ai_i18n ),
        'view', 'View version'|i18n( $exp_ai_i18n ),
        'open_panel', 'Open the Intelligence tab'|i18n( $exp_ai_i18n ),
        'status_draft', 'Draft'|i18n( $exp_ai_i18n ),
        'status_kept', 'Saved as draft'|i18n( $exp_ai_i18n ),
        'status_pending', 'Waiting for approval'|i18n( $exp_ai_i18n ),
        'status_published', 'Published'|i18n( $exp_ai_i18n ),
        'cost_spent', 'Spent'|i18n( $exp_ai_i18n ),
        'cost_tokens', 'Tokens'|i18n( $exp_ai_i18n ),
        'cost_requests', 'Requests'|i18n( $exp_ai_i18n ),
        'cost_model', 'Model'|i18n( $exp_ai_i18n ),
        'cost_lane', 'Lane'|i18n( $exp_ai_i18n ),
        'cost_off', 'Costs are not shown on this site.'|i18n( $exp_ai_i18n ),
        'today', 'today'|i18n( $exp_ai_i18n ),
        'item_placeholder', 'Nothing to decide here: open the Changes tab.'|i18n( $exp_ai_i18n ),
        'queued_background', 'This model is slow: the turn runs in the background. You may leave this page; the thread keeps working.'|i18n( $exp_ai_i18n ),
        'working_background', 'Working in the background, step %count:'|i18n( $exp_ai_i18n ),
        'background_failed', 'The turn in the background failed.'|i18n( $exp_ai_i18n ),
        'rv_summary', '%objects object(s), %fields field(s), %pending part(s) to review'|i18n( $exp_ai_i18n ),
        'rv_new', 'New'|i18n( $exp_ai_i18n ),
        'rv_under', 'under %name'|i18n( $exp_ai_i18n ),
        'rv_part', 'Part %n of %count'|i18n( $exp_ai_i18n ),
        'rv_accept', 'Accept'|i18n( $exp_ai_i18n ),
        'rv_reject', 'Reject'|i18n( $exp_ai_i18n ),
        'rv_undo', 'Undecide'|i18n( $exp_ai_i18n ),
        'rv_edit', 'Edit'|i18n( $exp_ai_i18n ),
        'rv_save', 'Save'|i18n( $exp_ai_i18n ),
        'rv_cancel', 'Cancel'|i18n( $exp_ai_i18n ),
        'rv_accept_field', 'Accept field'|i18n( $exp_ai_i18n ),
        'rv_reject_field', 'Reject field'|i18n( $exp_ai_i18n ),
        'rv_edited', 'edited by you'|i18n( $exp_ai_i18n ),
        'rv_not_alone', 'This part can only be accepted together with the rest of its table.'|i18n( $exp_ai_i18n ),
        'rv_decision_pending', 'To review'|i18n( $exp_ai_i18n ),
        'rv_decision_accepted', 'Accepted'|i18n( $exp_ai_i18n ),
        'rv_decision_rejected', 'Rejected'|i18n( $exp_ai_i18n ),
        'rv_decision_partial', 'Partly accepted'|i18n( $exp_ai_i18n ),
        'rv_decision_applied', 'Written to draft %version'|i18n( $exp_ai_i18n ),
        'rv_decision_conflict', 'Conflict'|i18n( $exp_ai_i18n ),
        'rv_decision_stale', 'Replaced'|i18n( $exp_ai_i18n ),
        'rv_conflict', 'The content changed since Vector proposed this. Writing it now could undo a colleague\'s work.'|i18n( $exp_ai_i18n ),
        'rv_keep', 'Keep current'|i18n( $exp_ai_i18n ),
        'rv_restage', 'Compare with the current version'|i18n( $exp_ai_i18n ),
        'rv_rebase', 'Ask the AI to rebase'|i18n( $exp_ai_i18n ),
        'rv_rebasing', 'Vector stages the changes again against the current version...'|i18n( $exp_ai_i18n ),
        'rv_rejected_all', 'All open parts rejected.'|i18n( $exp_ai_i18n ),
        'rv_applied', '%count change(s) written to %drafts draft(s).'|i18n( $exp_ai_i18n ),
        'rv_conflicts', '%count change(s) not written: the content changed meanwhile.'|i18n( $exp_ai_i18n ),
        'rv_missing', 'Required fields still empty: %fields'|i18n( $exp_ai_i18n ),
        'rv_nothing', 'Accept at least one part first.'|i18n( $exp_ai_i18n ),
        'rv_markup', 'Rich text is shown as text; links and formatting are kept.'|i18n( $exp_ai_i18n )
    ) )}</script>
</div>
{undef $exp_ai_i18n}
