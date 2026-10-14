{* Dashboard block "Exponential Intelligence: my drafts" (dashboard.ini [DashboardBlock_expai_drafts]). *}
<h2>{'Exponential Intelligence: my drafts'|i18n( 'extension/expai/dashboard' )}</h2>
{def $exp_ai_drafts = fetch( 'expai', 'my_drafts', hash( 'limit', $block.number_of_items ) )}
{if $exp_ai_drafts|count}
<table class="list" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <th>{'Content'|i18n( 'extension/expai/dashboard' )}</th>
        <th>{'Status'|i18n( 'extension/expai/dashboard' )}</th>
        <th>{'Written'|i18n( 'extension/expai/dashboard' )}</th>
    </tr>
{foreach $exp_ai_drafts as $d sequence array( 'bglight', 'bgdark' ) as $style}
    <tr class="{$style}">
        <td>{if $d.urls.view}<a href={$d.urls.view|ezurl}>{$d.name|wash}</a>{else}{'(removed)'|i18n( 'extension/expai/dashboard' )}{/if}</td>
        <td>{switch match=$d.status}{case match='deleted'}{'deleted'|i18n( 'extension/expai/links' )}{/case}{case match='kept'}{'Saved as draft'|i18n( 'extension/expai/panel' )}{/case}{case match='pending'}{'Waiting for approval'|i18n( 'extension/expai/panel' )}{/case}{case match='published'}{'Published'|i18n( 'extension/expai/panel' )}{/case}{case match='discarded'}{'Discarded'|i18n( 'extension/expai/dashboard' )}{/case}{case}{'Draft'|i18n( 'extension/expai/panel' )}{/case}{/switch}</td>
        <td>{$d.created|l10n( 'shortdatetime' )}</td>
    </tr>
{/foreach}
</table>
<p><a href={'expai/dashboard'|ezurl}>{'All my Exponential Intelligence drafts'|i18n( 'extension/expai/dashboard' )}</a></p>
{else}
<p>{'Nothing written with Exponential Intelligence yet.'|i18n( 'extension/expai/dashboard' )}</p>
{/if}
{undef $exp_ai_drafts}
