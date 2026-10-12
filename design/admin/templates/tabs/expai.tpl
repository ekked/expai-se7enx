{* The "Exponential Intelligence" panel of the content full view (admininterface.ini [AdditionalTab_expai]).
   The sections come from expai.ini [PanelSettings] Sections[]; the conversation and the drafts are loaded by
   expai.js through ezjscore (expai::load), so nothing personal is part of the cached view. *}
{if and( fetch( 'expai', 'switched_on' ),
         fetch( 'user', 'has_access_to', hash( 'module', 'expai', 'function', 'write' ) ) )}
<section class="exp-ai exp-ai-panel" id="exp-ai-panel" data-node-id="{$node.node_id}" aria-labelledby="exp-ai-panel-title">
    <header class="exp-ai-head">
        <span class="exp-ai-mark" aria-hidden="true"></span>
        <div>
            <h2 id="exp-ai-panel-title">{'Exponential Intelligence'|i18n( 'extension/expai/panel' )}</h2>
            <p class="exp-ai-sub">{'Powered by Claude from Anthropic'|i18n( 'extension/expai/panel' )}</p>
        </div>
        {include uri='design:expai/parts/help_link.tpl' page='full-view-panel' label='the Intelligence tab'|i18n( 'extension/expai/help' ) text='?'}
    </header>
    <p class="exp-ai-intro">{'Ask for new content under "%name" or for changes to it. Everything is written as drafts that you own; nothing is published until you decide.'|i18n( 'extension/expai/panel',, hash( '%name', $node.name ) )|wash}</p>
{foreach ezini( 'PanelSettings', 'Sections', 'expai.ini' ) as $exp_ai_section}
    {include uri=concat( 'design:', ezini( concat( 'PanelSection_', $exp_ai_section ), 'Template', 'expai.ini' ) ) node=$node section=$exp_ai_section}
{/foreach}
    <script type="application/json" class="exp-ai-strings">{json_encode( hash(
        'you', 'You'|i18n( 'extension/expai/panel' ),
        'assistant', 'Exponential Intelligence'|i18n( 'extension/expai/panel' ),
        'assistant_short', 'Exponential Intelligence'|i18n( 'extension/expai/panel' ),
        'lookups', '%count lookup(s)'|i18n( 'extension/expai/panel' ),
        'working', 'Exponential Intelligence is working...'|i18n( 'extension/expai/panel' ),
        'error', 'Something went wrong:'|i18n( 'extension/expai/panel' ),
        'timeout', 'The server did not answer in time. Please try again.'|i18n( 'extension/expai/panel' ),
        'continue', 'Continue'|i18n( 'extension/expai/panel' ),
        'interrupted', 'The last answer was interrupted.'|i18n( 'extension/expai/panel' ),
        'reset_confirm', 'Start a new conversation? The drafts stay.'|i18n( 'extension/expai/panel' ),
        'no_drafts', 'No drafts yet.'|i18n( 'extension/expai/panel' ),
        'edit', 'Edit'|i18n( 'extension/expai/panel' ),
        'view', 'View version'|i18n( 'extension/expai/panel' ),
        'what_changed', 'What changed'|i18n( 'extension/expai/revert' ),
        'open', 'Open'|i18n( 'extension/expai/panel' ),
        'discard', 'Discard'|i18n( 'extension/expai/panel' ),
        'discard_confirm', 'Discard the draft "%name"? This cannot be undone.'|i18n( 'extension/expai/panel' ),
        'version', 'Version %version'|i18n( 'extension/expai/panel' ),
        'status_draft', 'Draft'|i18n( 'extension/expai/panel' ),
        'status_kept', 'Saved as draft'|i18n( 'extension/expai/panel' ),
        'status_pending', 'Waiting for approval'|i18n( 'extension/expai/panel' ),
        'status_published', 'Published'|i18n( 'extension/expai/panel' ),
        'approval_approval', 'Waits for approval when sent'|i18n( 'extension/expai/panel' ),
        'approval_possible', 'May wait for approval when sent'|i18n( 'extension/expai/panel' ),
        'approval_direct', 'Published at once when sent (no approval applies)'|i18n( 'extension/expai/panel' ),
        'select_some', 'Select at least one draft.'|i18n( 'extension/expai/panel' ),
        'summary_approval', '%count will wait for approval'|i18n( 'extension/expai/panel' ),
        'summary_possible', '%count may wait for approval'|i18n( 'extension/expai/panel' ),
        'summary_direct', '%count will be published at once (no approval applies)'|i18n( 'extension/expai/panel' ),
        'send_confirm', 'Send %count draft(s)? %summary.'|i18n( 'extension/expai/panel' ),
        'kept', 'Kept as drafts. You find them under My drafts.'|i18n( 'extension/expai/panel' ),
        'sent_published', '"%name" was published.'|i18n( 'extension/expai/panel' ),
        'sent_pending', '"%name" is waiting for approval.'|i18n( 'extension/expai/panel' ),
        'sent_draft', '"%name" was not published: the publishing workflow stopped it. It is still a draft.'|i18n( 'extension/expai/panel' ),
        'label', 'Exponential Intelligence'|i18n( 'extension/expai/label' ),
        'label_full', 'Exponential Intelligence (Powered by Claude from Anthropic)'|i18n( 'extension/expai/label' )
    ) )}</script>
</section>
{/if}
