{* Vector pane "plan" ([VectorPane_plan]): the proposed or approved plan of the thread with its estimate
   (doc/plan-mode.md). Filled by expai_vector.js (expaivector::plan, plan_estimate, plan_decide). *}
<div class="exp-ai-v-pane-body" data-pane-body="plan">
    <div class="exp-ai-v-plan" hidden="hidden">
        <p class="exp-ai-v-plan-head"><strong class="exp-ai-v-plan-title"></strong> <span class="exp-ai-v-plan-state"></span></p>
        <div class="exp-ai-v-plan-items"></div>
        <dl class="exp-ai-v-plan-estimate"></dl>
        <ul class="exp-ai-v-plan-limits"></ul>
        <div class="exp-ai-v-plan-meta"></div>
        <p class="exp-ai-v-error" role="alert" hidden="hidden"></p>
        <div class="exp-ai-v-plan-actions" hidden="hidden">
            <button type="button" class="exp-ai-v-btn exp-ai-v-btn-primary" data-plan="approve">{'Approve and run'|i18n( 'extension/expai/vector' )}</button>
            <button type="button" class="exp-ai-v-btn" data-plan="ask">{'Ask for changes'|i18n( 'extension/expai/vector' )}</button>
            <button type="button" class="exp-ai-v-btn" data-plan="reject">{'Reject'|i18n( 'extension/expai/vector' )}</button>
        </div>
        <p class="exp-ai-v-hint exp-ai-v-plan-note">{'Approved items run one after the other; every change is staged for your review in the Changes tab, new objects too.'|i18n( 'extension/expai/vector' )}</p>
    </div>
    <p class="exp-ai-v-placeholder" data-placeholder="plan">{'No plan yet. In plan mode Vector reads first and proposes a plan with an estimate; bulk work always starts here.'|i18n( 'extension/expai/vector' )}</p>
</div>
