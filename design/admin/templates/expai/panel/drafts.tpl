{* Panel section "drafts": what the assistant wrote here for you, and what to do with it. *}
<div class="exp-ai-section exp-ai-drafts">
    <h3>{'Drafts written with Exponential Intelligence'|i18n( 'extension/expai/panel' )}</h3>
    <div class="exp-ai-draft-list"></div>
    <p class="exp-ai-act-note" aria-live="polite"></p>
    <p class="exp-ai-send-summary" aria-live="polite"></p>
    <div class="exp-ai-actions exp-ai-draft-actions">
        <input type="button" class="defaultbutton exp-ai-btn exp-ai-keep" value="{'Save as drafts'|i18n( 'extension/expai/panel' )}" />
        <input type="button" class="button exp-ai-btn exp-ai-send-approval" value="{'Send for approval'|i18n( 'extension/expai/panel' )}" />
    </div>
    <p class="exp-ai-hint">{'"Save as drafts" keeps the selected drafts; you find them under My drafts and can edit them in the normal edit form. "Send for approval" publishes them the normal way: where an approval workflow applies they wait for the approver, otherwise they are published at once. Each draft says which applies.'|i18n( 'extension/expai/panel' )}</p>
    <p class="exp-ai-hint"><a href={'expai/log'|ezurl}>{'All drafts written with Exponential Intelligence'|i18n( 'extension/expai/panel' )}</a></p>
</div>
