{* Vector pane "cost" ([VectorPane_cost]): what the thread has cost so far (from the usage log). Filled by expai_vector.js. *}
<div class="exp-ai-v-pane-body" data-pane-body="cost">
    <dl class="exp-ai-v-cost"></dl>
{if $cost_enabled}
    <p class="exp-ai-v-placeholder">{'Estimated from the request log at list prices; the provider\'s invoice is authoritative.'|i18n( 'extension/expai/vector' )}</p>
{/if}
</div>
