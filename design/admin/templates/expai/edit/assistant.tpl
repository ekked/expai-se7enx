{* Edit view section "assistant": fill the fields of this draft from a brief. *}
<div class="exp-ai-section exp-ai-fill">
    <label class="exp-ai-label" for="exp-ai-brief">{'Describe what to write, and Exponential Intelligence fills in the fields of this form for you to review.'|i18n( 'extension/expai/edit' )}</label>
    <textarea id="exp-ai-brief" class="exp-ai-input box no-autosave" rows="3" maxlength="{ezini( 'AssistantSettings', 'MaxMessageLength', 'expai.ini' )|wash}"
              placeholder="{'For example: A short article about the opening of our new office, friendly tone, about 200 words.'|i18n( 'extension/expai/edit' )|wash}"></textarea>
    <div class="exp-ai-actions">
        <input type="button" class="defaultbutton exp-ai-btn exp-ai-fill-go" value="{'Fill the fields'|i18n( 'extension/expai/edit' )}" />
        <input type="button" class="button exp-ai-btn exp-ai-fill-undo" hidden="hidden" value="{'Undo'|i18n( 'extension/expai/edit' )}" />
    </div>
    <p class="exp-ai-working" hidden="hidden"><span class="exp-ai-spinner" aria-hidden="true"></span> <span>{'Exponential Intelligence is working...'|i18n( 'extension/expai/edit' )}</span></p>
    <p class="exp-ai-note" aria-live="polite"></p>
    <p>{include uri='design:expai/parts/model_select.tpl' compact=true()}</p>
    <p class="exp-ai-hint">{'Per field: use the Exponential Intelligence buttons next to the fields (write, rewrite, shorten, translate, suggest). Nothing is saved until you store or publish the draft as usual.'|i18n( 'extension/expai/edit' )}</p>
</div>
