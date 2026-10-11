{* Dashboard card "Chains" (expai/dashboard, doc/chains.md): the current user's latest chain runs, and how many
   wait for an editor. fetch( 'expai', 'chain_runs' ) lists only the user's own runs. *}
{def $exp_ai_runs = fetch( 'expai', 'chain_runs', hash( 'limit', 5 ) )}
{if $exp_ai_runs.rows|count}
<section class="exp-ai-card exp-ai-chain-card" aria-labelledby="exp-ai-chain-card-title">
    <div class="exp-ai-card-head">
        <h2 id="exp-ai-chain-card-title">{'Chains'|i18n( 'extension/expai/chain' )}</h2>
{if $exp_ai_runs.waiting}
        <span class="exp-ai-pill exp-ai-pill-warn">{'%count wait for you'|i18n( 'extension/expai/chain',, hash( '%count', $exp_ai_runs.waiting ) )|wash}</span>
{/if}
        <a class="exp-ai-card-link" href={'expai/chain'|ezurl}>{'All runs'|i18n( 'extension/expai/chain' )}</a>
    </div>
    <ul class="exp-ai-thread-rows">
{foreach $exp_ai_runs.rows as $exp_ai_run}
        <li class="exp-ai-thread-row exp-ai-chain-state-{$exp_ai_run.state|wash}">
            <span class="exp-ai-thread-dot" aria-hidden="true"></span>
            <a href={concat( 'expai/chain/(run)/', $exp_ai_run.id )|ezurl}>{$exp_ai_run.label|wash}</a>
            <span class="exp-ai-thread-meta">{$exp_ai_run.state_label|wash}{if $exp_ai_run.current_step} &middot; {$exp_ai_run.current_step|wash}{/if} &middot; {$exp_ai_run.cost|wash} &middot; {$exp_ai_run.modified|l10n( 'shortdatetime' )}</span>
        </li>
{/foreach}
    </ul>
</section>
{/if}
{undef $exp_ai_runs}
