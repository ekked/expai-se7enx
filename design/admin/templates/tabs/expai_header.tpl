{* The "Intelligence" tab (Exponential Intelligence) of the content full view (admininterface.ini [AdditionalTab_expai]).
   Drawn only for users with the policy expai/write while expai is switched on (the master switch, doc/switch-and-veto.md). *}
{if and( fetch( 'expai', 'switched_on' ),
         fetch( 'user', 'has_access_to', hash( 'module', 'expai', 'function', 'write' ) ) )}
<li id="node-tab-{$tab}" class="exp-ai-tab {if $last}last{else}middle{/if}{if $node_tab_index|eq( $tab )} selected{/if}">
    {if $tabs_disabled}
        <span class="disabled" title="{'Tab is disabled, enable with toggler to the left of these tabs.'|i18n( 'design/admin/node/view/full' )}">{'Intelligence'|i18n( 'extension/expai/tab' )}</span>
    {else}
        <a href={concat( $node_url_alias, '/(tab)/', $tab )|ezurl} title="{'Exponential Intelligence (Powered by Claude from Anthropic)'|i18n( 'extension/expai/panel' )}">{'Intelligence'|i18n( 'extension/expai/tab' )}</a>
    {/if}
</li>
{/if}
