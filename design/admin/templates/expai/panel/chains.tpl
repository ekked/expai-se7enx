{* Panel section "chains" ([PanelSection_chains], doc/chains.md): start a chain run under this node. The form posts
   to expai/chain (with the form token); the runs themselves are on expai/chain, so nothing personal is part of the
   cached view. Only users with the policy expai/chain see the chains they may use. *}
{def $exp_ai_chains = fetch( 'expai', 'chains' )}
{if $exp_ai_chains|count}
<div class="exp-ai-section exp-ai-chains" id="exp-ai-chains">
    <h3>{'Write with a chain'|i18n( 'extension/expai/chain' )}</h3>
    <form method="post" action={'expai/chain'|ezurl} class="exp-ai-chain-form">
        <input type="hidden" name="ExpAiNodeID" value="{$node.node_id}" />
        <p class="exp-ai-chain-row">
            <label class="exp-ai-label" for="exp-ai-chain-pick">{'Use chain'|i18n( 'extension/expai/chain' )}</label>
            <select id="exp-ai-chain-pick" name="ExpAiChain" class="exp-ai-select">
{foreach $exp_ai_chains as $exp_ai_chain}
                <option value="{$exp_ai_chain.id|wash}" title="{$exp_ai_chain.steps|implode( ' > ' )|wash}">{$exp_ai_chain.label|wash}</option>
{/foreach}
            </select>
            <label class="exp-ai-label" for="exp-ai-chain-count">{'Items'|i18n( 'extension/expai/chain' )}</label>
            <input id="exp-ai-chain-count" type="number" name="ExpAiCount" class="exp-ai-input exp-ai-chain-count" value="1" min="1" max="{$exp_ai_chains[0].max_items|wash}" />
        </p>
        <label class="exp-ai-label" for="exp-ai-chain-brief">{'Brief'|i18n( 'extension/expai/chain' )}</label>
        <textarea id="exp-ai-chain-brief" name="ExpAiPrompt" class="exp-ai-input box" rows="3" maxlength="{ezini( 'ChainSettings', 'MaxBriefLength', 'expai.ini' )|wash}"
                  placeholder="{'For example: an article for families about our spring events, friendly, about 400 words.'|i18n( 'extension/expai/chain' )|wash}"></textarea>
        <div class="exp-ai-actions">
            <input type="submit" class="button exp-ai-btn" name="ExpAiChainStartButton" value="{'Start the chain'|i18n( 'extension/expai/chain' )}" />
            <a href={'expai/chain'|ezurl}>{'Runs and checkpoints'|i18n( 'extension/expai/chain' )}</a>
        </div>
        <p class="exp-ai-hint">{'Each item is one run in the background: written, reviewed and revised until it passes, then title and summary; the draft waits for you to approve it before anything else happens. Under "%name"; at most %max per run.'|i18n( 'extension/expai/chain',, hash( '%name', $node.name, '%max', $exp_ai_chains[0].max_cost ) )|wash}</p>
    </form>
</div>
{/if}
{undef $exp_ai_chains}
