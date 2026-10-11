{* Vector pane "diff" ([VectorPane_diff]): staged changes to review (doc/review.md) and the drafts the thread wrote.
   Filled by expai_vector.js (expaivector::changes, change_decide, change_edit, change_restage, rebase, apply). *}
<div class="exp-ai-v-pane-body" data-pane-body="diff">
    <div class="exp-ai-v-changes">
        <div class="exp-ai-v-review-bar" hidden="hidden">
            <span class="exp-ai-v-review-summary" aria-live="polite"></span>
            <span class="exp-ai-v-review-all">
                <button type="button" class="exp-ai-v-btn exp-ai-v-btn-small" data-review="accept_all">{'Accept all'|i18n( 'extension/expai/vector' )}</button>
                <button type="button" class="exp-ai-v-btn exp-ai-v-btn-small" data-review="reject_all">{'Reject all'|i18n( 'extension/expai/vector' )}</button>
            </span>
        </div>
        <div class="exp-ai-v-review-list"></div>
        <p class="exp-ai-v-placeholder" data-placeholder="review">{'No staged changes. With staged review, what Vector proposes appears here to accept or reject, field by field.'|i18n( 'extension/expai/vector' )}</p>
        <div class="exp-ai-v-review-foot" hidden="hidden">
            <button type="button" class="exp-ai-v-btn exp-ai-v-btn-primary" data-review="apply">{'Write accepted changes to drafts'|i18n( 'extension/expai/vector' )}</button>
            <span class="exp-ai-v-hint">{'Drafts are never published: you publish or send them for approval.'|i18n( 'extension/expai/vector' )}</span>
        </div>
        <p class="exp-ai-v-error" role="alert" hidden="hidden"></p>
    </div>
    <h3 class="exp-ai-v-pane-title">{'Drafts written'|i18n( 'extension/expai/vector' )}</h3>
    <ul class="exp-ai-v-drafts" role="listbox" aria-label="{'Drafts written'|i18n( 'extension/expai/vector' )}"></ul>
    <p class="exp-ai-v-placeholder" data-placeholder="drafts">{'No drafts yet.'|i18n( 'extension/expai/vector' )}</p>
</div>
