{* expai/chain: the runs of AI chains. Variables: rows (expAiChainFunctionCollection::row()), total, offset, limit,
   all_users (expai/log: everyone's runs), error, notice, ready. *}
<div class="exp-ai exp-ai-chain-page">
    <header class="exp-ai-head">
        <span class="exp-ai-mark" aria-hidden="true"></span>
        <div>
            <h1 class="context-title">{'Chains'|i18n( 'extension/expai/chain' )}</h1>
            <p class="exp-ai-sub">{'Exponential Intelligence'|i18n( 'extension/expai/chain' )}</p>
        </div>
        {include uri='design:expai/parts/help_link.tpl' page='chains' label='AI chains'|i18n( 'extension/expai/help' ) text='?'}
    </header>
{if $error}<p class="exp-ai-error" role="alert">{$error|wash}</p>{/if}
{if $notice}<p class="exp-ai-notice" role="status">{$notice|wash}</p>{/if}
{if $ready|not}<p class="exp-ai-muted">{'Chains need the job queue (extension expqueue) and the chain tables (bin/php/expaischema.php --install).'|i18n( 'extension/expai/chain' )}</p>{/if}
    <p class="exp-ai-muted">{'A chain writes in steps: a model writes, another reviews and the text is revised until it passes, a third prepares the title and summary, then an editor approves before anything else happens. Every step is a queued job with its cost; the result is a draft.'|i18n( 'extension/expai/chain' )}</p>

    <section class="exp-ai-card" aria-labelledby="exp-ai-chain-runs">
        <h2 id="exp-ai-chain-runs">{if $all_users}{'Runs of all users'|i18n( 'extension/expai/chain' )}{else}{'Your runs'|i18n( 'extension/expai/chain' )}{/if}</h2>
{if $rows|count}
        <table class="list exp-ai-draft-table exp-ai-chain-table" cellspacing="0">
            <thead><tr><th>#</th><th>{'Run'|i18n( 'extension/expai/chain' )}</th><th>{'State'|i18n( 'extension/expai/chain' )}</th><th>{'Step'|i18n( 'extension/expai/chain' )}</th><th>{'Cost'|i18n( 'extension/expai/chain' )}</th><th>{'Changed'|i18n( 'extension/expai/chain' )}</th></tr></thead>
            <tbody>
{foreach $rows as $r}
                <tr class="exp-ai-chain-state-{$r.state|wash}">
                    <td>{$r.id|wash}</td>
                    <td><a href={concat( 'expai/chain/(run)/', $r.id )|ezurl}>{$r.label|wash}</a></td>
                    <td><span class="exp-ai-pill exp-ai-chain-pill-{$r.state|wash}">{$r.state_label|wash}</span></td>
                    <td>{$r.current_step|wash}</td>
                    <td>{$r.cost|wash}</td>
                    <td>{$r.modified|l10n( 'shortdatetime' )}</td>
                </tr>
{/foreach}
            </tbody>
        </table>
{include name=navigator uri='design:navigator/google.tpl' page_uri='/expai/chain' item_count=$total view_parameters=hash( 'offset', $offset ) item_limit=$limit}
{else}
        <p class="exp-ai-hint">{'No runs yet. Start one from the Intelligence tab of a page ("Write with a chain") or from Start writing on the dashboard.'|i18n( 'extension/expai/chain' )}</p>
{/if}
    </section>
</div>
