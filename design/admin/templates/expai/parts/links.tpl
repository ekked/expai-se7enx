{* The icon links of a row (expAiLinks::build()): View, Edit, Prompt again, Intelligence tab, or a "deleted" / "in
   trash" badge. Parameter: links. *}
<span class="exp-ai-links">
{switch match=$links.state}
{case match='deleted'}<span class="exp-ai-muted-badge">{'deleted'|i18n( 'extension/expai/links' )}</span>{/case}
{case match='trash'}<span class="exp-ai-muted-badge">{'in trash'|i18n( 'extension/expai/links' )}</span>{/case}
{case}
    {if $links.view}<a class="exp-ai-icon-link" href={$links.view|ezurl} title="{'View'|i18n( 'extension/expai/links' )}" aria-label="{'View'|i18n( 'extension/expai/links' )}"><img src={'view-fullscreen.png'|ezimage} width="16" height="16" alt="" /></a>{/if}
    {if $links.edit}<a class="exp-ai-icon-link" href={$links.edit|ezurl} title="{'Edit'|i18n( 'extension/expai/links' )}" aria-label="{'Edit'|i18n( 'extension/expai/links' )}"><img src={'edit.gif'|ezimage} width="16" height="16" alt="" /></a>{/if}
    {if $links.prompt}<a class="exp-ai-icon-link" href={$links.prompt|ezurl} title="{'Prompt again'|i18n( 'extension/expai/links' )}" aria-label="{'Prompt again'|i18n( 'extension/expai/links' )}"><img src={'history.png'|ezimage} width="16" height="16" alt="" /></a>{/if}
    {if $links.tab}<a class="exp-ai-icon-link" href={$links.tab|ezurl} title="{'Intelligence tab'|i18n( 'extension/expai/links' )}" aria-label="{'Intelligence tab'|i18n( 'extension/expai/links' )}"><span class="exp-ai-mark" aria-hidden="true"></span></a>{/if}
{/case}
{/switch}
</span>
