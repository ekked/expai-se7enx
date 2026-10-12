{* expai/budgets: the site budget, job groups, held jobs, lanes per model and provider batches. Variables: status
   (expAiBudget::status()), lanes, batches, held_jobs, can_manage, notice, queue, reserve_factor. *}
<div class="exp-ai exp-ai-budgets-page">
    <header class="exp-ai-head">
        <span class="exp-ai-mark" aria-hidden="true"></span>
        <div>
            <h1 class="context-title">{'EI: Budgets'|i18n( 'extension/expai/budget' )}</h1>
            <p class="exp-ai-sub">{'Exponential Intelligence'|i18n( 'extension/expai/budget' )}</p>
        </div>
    </header>
{if $notice}<p class="exp-ai-notice" role="status">{$notice|wash}</p>{/if}
{if $queue|not}<p class="exp-ai-muted">{'The job queue (extension expqueue) is not active: requests run only in the panel and the edit form, and nothing is reserved.'|i18n( 'extension/expai/budget' )}</p>{/if}

    {include uri='design:expai/parts/budget_summary.tpl' status=$status heading=true()}

{if $status.groups|count}
    <section class="exp-ai-card" aria-labelledby="exp-ai-budget-groups">
        <h2 id="exp-ai-budget-groups">{'Job groups'|i18n( 'extension/expai/budget' )}</h2>
        <table class="list exp-ai-draft-table" cellspacing="0">
            <thead><tr><th>{'Group'|i18n( 'extension/expai/budget' )}</th><th>{'State'|i18n( 'extension/expai/budget' )}</th><th>{'Jobs'|i18n( 'extension/expai/budget' )}</th><th>{'Budget'|i18n( 'extension/expai/budget' )}</th><th>{'Spent'|i18n( 'extension/expai/budget' )}</th><th>{'Reserved'|i18n( 'extension/expai/budget' )}</th><th>{'Held'|i18n( 'extension/expai/budget' )}</th></tr></thead>
            <tbody>
{foreach $status.groups as $g}
                <tr><td><a href={concat( 'expqueue/group/', $g.uuid )|ezurl}>{$g.label|wash}</a></td><td>{$g.state|wash}</td><td>{$g.jobs|wash}</td>
                    <td>{if $g.cap}{include uri='design:expai/parts/bar.tpl' value=$g.spent|sum( $g.reserved ) max=$g.cap level=cond( ge( $g.percent, 100 ), 'over', ge( $g.percent, 90 ), 'high', ge( $g.percent, 75 ), 'warn', 'ok' ) label=$g.uuid text=$g.cap_money}{else}<span class="exp-ai-muted">{'none'|i18n( 'extension/expai/budget' )}</span>{/if}</td>
                    <td>{$g.spent_money|wash}</td><td>{$g.reserved_money|wash}</td><td>{$g.held|wash}</td></tr>
{/foreach}
            </tbody>
        </table>
    </section>
{/if}

{if $held_jobs|count}
    <section class="exp-ai-card" aria-labelledby="exp-ai-budget-held">
        <h2 id="exp-ai-budget-held">{'Held jobs'|i18n( 'extension/expai/budget' )}</h2>
        <ul class="exp-ai-compact-list">
{foreach $held_jobs as $j}
            <li><a href={concat( 'expqueue/job/', $j.uuid )|ezurl}><code>{$j.uuid|shorten( 13 )|wash}</code></a> {$j.type|wash} {$j.alias|wash} {$j.lane|wash} &middot; {$j.error|wash}</li>
{/foreach}
        </ul>
{if $can_manage}
        <form method="post" action={'expai/budgets'|ezurl}>
            <input type="submit" class="button exp-ai-btn" name="ExpAiResumeHeldButton" value="{'Resume held jobs that fit the budget'|i18n( 'extension/expai/budget' )}" />
        </form>
{/if}
    </section>
{/if}

    <section class="exp-ai-card" aria-labelledby="exp-ai-budget-lanes">
        <h2 id="exp-ai-budget-lanes">{'Lanes'|i18n( 'extension/expai/budget' )}</h2>
        <p class="exp-ai-muted">{'A lane is a way of sending a request: standard, flex (slower, cheaper), batch (submitted with others, answered within hours, at half price), fast (quicker, at a premium). Queued requests reserve their estimate times %factor.'|i18n( 'extension/expai/budget',, hash( '%factor', $reserve_factor ) )}</p>
        <table class="list exp-ai-draft-table" cellspacing="0">
            <thead><tr><th>{'Model'|i18n( 'extension/expai/budget' )}</th><th>{'Lane'|i18n( 'extension/expai/budget' )}</th><th>{'Price'|i18n( 'extension/expai/budget' )}</th><th>{'Typical wait'|i18n( 'extension/expai/budget' )}</th><th>{'Retention'|i18n( 'extension/expai/budget' )}</th><th>{'State'|i18n( 'extension/expai/budget' )}</th></tr></thead>
            <tbody>
{foreach $lanes as $m}
{foreach $m.lanes as $n => $l}
                <tr>
                    <td>{if eq( $n, 0 )}{$m.label|wash}<br /><code>{$m.alias|wash}</code>{/if}</td>
                    <td>{$l.name|wash}{if eq( $l.type, 'batch' )} <span class="exp-ai-badge">{'batch'|i18n( 'extension/expai/budget' )}</span>{/if}</td>
                    <td>{if $l.factor}&times;{$l.factor|wash}{else}<span class="exp-ai-muted">{'not confirmed'|i18n( 'extension/expai/budget' )}</span>{/if}</td>
                    <td>{if ge( $l.latency, 3600 )}{'%h h'|i18n( 'extension/expai/budget',, hash( '%h', $l.latency|div( 3600 )|round ) )}{elseif ge( $l.latency, 60 )}{'%m min'|i18n( 'extension/expai/budget',, hash( '%m', $l.latency|div( 60 )|round ) )}{else}{'%s s'|i18n( 'extension/expai/budget',, hash( '%s', $l.latency ) )}{/if}</td>
                    <td>{$l.retention|wash}</td>
                    <td>{if $l.usable}{'in use'|i18n( 'extension/expai/budget' )}{elseif $l.enabled|not}{'disabled'|i18n( 'extension/expai/budget' )}{else}{'no confirmed price'|i18n( 'extension/expai/budget' )}{/if}{if $l.verified}<br /><small>{'checked %date'|i18n( 'extension/expai/budget',, hash( '%date', $l.verified ) )}</small>{/if}</td>
                </tr>
{/foreach}
{/foreach}
            </tbody>
        </table>
    </section>

{if $batches|count}
    <section class="exp-ai-card" aria-labelledby="exp-ai-budget-batches">
        <h2 id="exp-ai-budget-batches">{'Provider batches'|i18n( 'extension/expai/budget' )}</h2>
        <table class="list exp-ai-draft-table" cellspacing="0">
            <thead><tr><th>#</th><th>{'Provider'|i18n( 'extension/expai/budget' )}</th><th>{'Model'|i18n( 'extension/expai/budget' )}</th><th>{'State'|i18n( 'extension/expai/budget' )}</th><th>{'Requests'|i18n( 'extension/expai/budget' )}</th><th>{'Submitted'|i18n( 'extension/expai/budget' )}</th><th>{'Deleted at the provider'|i18n( 'extension/expai/budget' )}</th></tr></thead>
            <tbody>
{foreach $batches as $b}
                <tr><td>{$b.id|wash}</td><td>{$b.provider|wash} <small>({$b.batch_api|wash})</small></td><td><code>{$b.model_alias|wash}</code></td>
                    <td>{$b.state|wash}{if $b.remote_status} <small>({$b.remote_status|wash})</small>{/if}{if $b.last_error}<br /><small class="exp-ai-step-failed">{$b.last_error|wash}</small>{/if}</td>
                    <td>{$b.request_count|wash}: {'%ok answered, %err failed, %exp expired'|i18n( 'extension/expai/budget',, hash( '%ok', $b.succeeded, '%err', $b.errored, '%exp', $b.expired ) )}</td>
                    <td>{$b.submitted|l10n( 'shortdatetime' )}</td>
                    <td>{if $b.deleted}{$b.deleted|l10n( 'shortdatetime' )}{else}{'no'|i18n( 'extension/expai/budget' )}{/if}</td></tr>
{/foreach}
            </tbody>
        </table>
    </section>
{/if}
</div>
