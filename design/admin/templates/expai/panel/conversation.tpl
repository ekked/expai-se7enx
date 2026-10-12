{* Panel section "conversation": the messages, the steps of the assistant, the message form. *}
<div class="exp-ai-section exp-ai-conversation">
    <div class="exp-ai-history-bar" hidden="hidden">
        <input type="button" class="button exp-ai-btn exp-ai-older" hidden="hidden" value="{'Show older'|i18n( 'extension/expai/panel' )}" />
        <span class="exp-ai-history-all">
            <button type="button" class="exp-ai-link exp-ai-expand-all">{'Expand all'|i18n( 'extension/expai/panel' )}</button>
            <button type="button" class="exp-ai-link exp-ai-collapse-all">{'Collapse all'|i18n( 'extension/expai/panel' )}</button>
        </span>
    </div>
    <ol class="exp-ai-log" aria-live="polite" aria-label="{'Conversation'|i18n( 'extension/expai/panel' )}"></ol>
    <p class="exp-ai-working" hidden="hidden"><span class="exp-ai-spinner" aria-hidden="true"></span> <span class="exp-ai-working-text">{'Exponential Intelligence is working...'|i18n( 'extension/expai/panel' )}</span></p>
    {* "Watch this run": the live state of the turn just sent, with links to its requests, its job, the thread (expAiTurnRecord) *}
    <p class="exp-ai-watch" hidden="hidden" aria-live="polite"></p>
    {* a turn on a slow model: its model, time per step and the switch to the default model (expAiSlowModel) *}
    <p class="exp-ai-slow" hidden="hidden" aria-live="polite"><span class="exp-ai-slow-text"></span> <span class="exp-ai-slow-time"></span>
        <button type="button" class="button exp-ai-btn exp-ai-slow-switch" hidden="hidden"></button></p>
    <p class="exp-ai-error" role="alert" hidden="hidden"></p>
    <form class="exp-ai-form" method="post" action={'expai/log'|ezurl} onsubmit="return false;">
        <label class="exp-ai-label" for="exp-ai-message">{'Your request'|i18n( 'extension/expai/panel' )}</label>
        <textarea id="exp-ai-message" class="exp-ai-input box" rows="3" maxlength="{ezini( 'AssistantSettings', 'MaxMessageLength', 'expai.ini' )|wash}"
                  placeholder="{'For example: Write three short articles about our spring events under this folder.'|i18n( 'extension/expai/panel' )|wash}"></textarea>
        <div class="exp-ai-actions">
            <input type="submit" class="defaultbutton exp-ai-btn exp-ai-send" value="{'Send'|i18n( 'extension/expai/panel' )}" />
            <input type="button" class="button exp-ai-btn exp-ai-continue" hidden="hidden" value="{'Continue'|i18n( 'extension/expai/panel' )}" />
            <input type="button" class="button exp-ai-btn exp-ai-reset" value="{'New conversation'|i18n( 'extension/expai/panel' )}" />
            {include uri='design:expai/parts/model_select.tpl' compact=true() cached=true()}
        </div>
    </form>
</div>
