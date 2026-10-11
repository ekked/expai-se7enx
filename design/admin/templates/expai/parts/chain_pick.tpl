{* "Use chain" in the dashboard's Start writing (doc/chains.md): the same request and location start a chain run in
   the background instead of opening the panel. The button posts the form to expai/chain. Variables: start (the
   location of Start writing). *}
{def $exp_ai_chains = fetch( 'expai', 'chains' )}
{if and( $exp_ai_chains|count, $start.usable )}
                    <span class="exp-ai-chain-pick">
                        <label class="exp-ai-visually-hidden" for="exp-ai-dash-chain">{'Use chain'|i18n( 'extension/expai/chain' )}</label>
                        <select id="exp-ai-dash-chain" name="ExpAiChain" class="exp-ai-select">
{foreach $exp_ai_chains as $exp_ai_chain}
                            <option value="{$exp_ai_chain.id|wash}">{'Use chain: %label'|i18n( 'extension/expai/chain',, hash( '%label', $exp_ai_chain.label ) )|wash}</option>
{/foreach}
                        </select>
                        <label class="exp-ai-visually-hidden" for="exp-ai-dash-count">{'Items'|i18n( 'extension/expai/chain' )}</label>
                        <input id="exp-ai-dash-count" type="number" name="ExpAiCount" class="exp-ai-input exp-ai-chain-count" value="1" min="1" max="{$exp_ai_chains[0].max_items|wash}" title="{'Items'|i18n( 'extension/expai/chain' )}" />
                        <input type="submit" class="button exp-ai-btn" name="ExpAiChainStartButton" formaction={'expai/chain'|ezurl} value="{'Start the chain'|i18n( 'extension/expai/chain' )}" />
                    </span>
{/if}
{undef $exp_ai_chains}
