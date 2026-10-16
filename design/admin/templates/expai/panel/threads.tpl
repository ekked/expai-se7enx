{* Panel section "threads" ([PanelSection_threads]): your threads on this node, and Vector. Filled by expai.js
   (expaivector::threads), so nothing personal is part of the cached view. *}
<div class="exp-ai-section exp-ai-threads" data-node-id="{$node.node_id}">
    <h3>{'Threads on this page'|i18n( 'extension/expai/threads' )}</h3>
    <ul class="exp-ai-thread-list"></ul>
    <p class="exp-ai-hint exp-ai-thread-empty" hidden="hidden">{'Your conversations here are threads too; they appear in Vector.'|i18n( 'extension/expai/threads' )}</p>
    <p class="exp-ai-hint exp-ai-thread-links" hidden="hidden">
        <a class="exp-ai-thread-new" href={concat( 'expai/vector/(node)/', $node.node_id )|ezurl}>{'New thread in Vector for this page'|i18n( 'extension/expai/threads' )}</a>
        <span aria-hidden="true">&middot;</span>
        <a href={'expai/vector'|ezurl}>{'All threads'|i18n( 'extension/expai/threads' )}</a>
    </p>
    <script type="application/json" class="exp-ai-thread-strings">{hash(
        'open', 'Open in Vector'|i18n( 'extension/expai/threads' ),
        'this_conversation', 'This conversation'|i18n( 'extension/expai/threads' ),
        'state_idle', 'Idle'|i18n( 'extension/expai/threads' ),
        'state_queued', 'Queued'|i18n( 'extension/expai/threads' ),
        'state_running', 'Working'|i18n( 'extension/expai/threads' ),
        'state_waiting_input', 'Waits for you'|i18n( 'extension/expai/threads' ),
        'state_review', 'To review'|i18n( 'extension/expai/threads' ),
        'state_done', 'Done'|i18n( 'extension/expai/threads' ),
        'state_failed', 'Failed'|i18n( 'extension/expai/threads' ),
        'state_stopped', 'Stopped'|i18n( 'extension/expai/threads' ),
        'origin_panel', 'Intelligence tab'|i18n( 'extension/expai/threads' ),
        'origin_vector', 'Vector'|i18n( 'extension/expai/threads' )
    )|expai_json}</script>
</div>
