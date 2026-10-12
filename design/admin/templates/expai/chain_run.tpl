{* expai/chain/(run)/<id>: one run of an AI chain. Variables: run (expAiChainRun), run_row, chain (id, label, max_cost,
   changed), planned (the steps of Steps[] with done/current), steps (every step row: label, type, iteration, state,
   job_state, model, lane, verdict, score, issue_count, tokens, cost, note, summary, issues, checklist), document
   (identifier, html: the text so far, cleaned), review, seo, links, flags, note, translate, can_act, tokens, group,
   duration, error. *}
<div class="exp-ai exp-ai-chain-page exp-ai-chain-run">
    <header class="exp-ai-head">
        <span class="exp-ai-mark" aria-hidden="true"></span>
        <div>
            <h1 class="context-title">{$run_row.label|wash}</h1>
            <p class="exp-ai-sub">{'Chain "%chain", run %id'|i18n( 'extension/expai/chain',, hash( '%chain', $chain.label, '%id', $run_row.id ) )|wash}</p>
        </div>
        {include uri='design:expai/parts/help_link.tpl' page='chains' label='AI chains'|i18n( 'extension/expai/help' ) text='?'}
    </header>
{if $error}<p class="exp-ai-error" role="alert">{$error|wash}</p>{/if}

    <section class="exp-ai-card exp-ai-chain-status" aria-labelledby="exp-ai-chain-status">
        <h2 id="exp-ai-chain-status" class="exp-ai-visually-hidden">{'State'|i18n( 'extension/expai/chain' )}</h2>
        <dl class="exp-ai-status-list">
            <div><dt>{'State'|i18n( 'extension/expai/chain' )}</dt><dd><span class="exp-ai-pill exp-ai-chain-pill-{$run_row.state|wash}">{$run_row.state_label|wash}</span>{if $run.last_error} <span class="exp-ai-muted">{$run.last_error|wash}</span>{/if}</dd></div>
            <div><dt>{'Cost so far'|i18n( 'extension/expai/chain' )}</dt><dd>{$run_row.cost|wash}{if $chain.max_cost} <span class="exp-ai-muted">{'of at most %max per run'|i18n( 'extension/expai/chain',, hash( '%max', $chain.max_cost ) )|wash}</span>{/if}</dd></div>
            <div><dt>{'Tokens'|i18n( 'extension/expai/chain' )}</dt><dd>{$tokens|wash}</dd></div>
            <div><dt>{'Time'|i18n( 'extension/expai/chain' )}</dt><dd>{if ge( $duration, 60 )}{'%m min %s s'|i18n( 'extension/expai/chain',, hash( '%m', $duration|div( 60 )|floor, '%s', $duration|mod( 60 ) ) )}{else}{'%s s'|i18n( 'extension/expai/chain',, hash( '%s', $duration ) )}{/if}</dd></div>
            <div><dt>{'Location'|i18n( 'extension/expai/chain' )}</dt><dd>{if $run.node}<a href={concat( 'content/view/full/', $run.node_id )|ezurl}>{$run.node.name|wash}</a>{else}{$run.node_id|wash}{/if}</dd></div>
            <div><dt>{'Draft'|i18n( 'extension/expai/chain' )}</dt><dd>{if $run.object_id}<a href={concat( 'content/versionview/', $run.object_id, '/', $run.version, '/', $run.language )|ezurl}>{'Version %version'|i18n( 'extension/expai/chain',, hash( '%version', $run.version ) )}</a> &middot; <a href={concat( 'content/edit/', $run.object_id, '/', $run.version, '/', $run.language )|ezurl}>{'Edit the draft'|i18n( 'extension/expai/chain' )}</a> <span class="exp-ai-label-chip">{'Exponential Intelligence'|i18n( 'extension/expai/label' )}</span>{else}<span class="exp-ai-muted">{'written at the checkpoint'|i18n( 'extension/expai/chain' )}</span>{/if}</dd></div>
{if $group}            <div><dt>{'Job group'|i18n( 'extension/expai/chain' )}</dt><dd><a href={concat( 'expqueue/group/', $group.uuid )|ezurl}>{'%spent spent'|i18n( 'extension/expai/chain',, hash( '%spent', $group.spent ) )|wash}{if $group.budget} / {$group.budget|wash}{/if}</a></dd></div>{/if}
        </dl>
        <p class="exp-ai-muted">{'Brief'|i18n( 'extension/expai/chain' )}: {$run.brief|wash}</p>
{if $chain.changed}        <p class="exp-ai-hint">{'The settings of this chain changed since the run started; the run keeps the steps and prompts it started with.'|i18n( 'extension/expai/chain' )}</p>{/if}
        <ol class="exp-ai-chain-plan">
{foreach $planned as $p}
            <li class="{if $p.done}exp-ai-chain-done{/if}{if $p.current} exp-ai-chain-current{/if}"><span class="exp-ai-chain-step-name">{$p.label|wash}</span>{if $p.model} <small>{$p.model|wash}</small>{/if}{if eq( $p.type, 'human' )} <small>{'editor'|i18n( 'extension/expai/chain' )}</small>{/if}</li>
{/foreach}
        </ol>
    </section>

{if and( eq( $run_row.state, 'waiting_human' ), $can_act )}
    <section class="exp-ai-card exp-ai-chain-checkpoint" aria-labelledby="exp-ai-chain-checkpoint">
        <h2 id="exp-ai-chain-checkpoint">{'Your decision'|i18n( 'extension/expai/chain' )}</h2>
        <p>{'Read the draft (and change it in the edit form if you like), then continue, ask for changes or stop. Continuing hands your version on to the next step.'|i18n( 'extension/expai/chain' )}</p>
{if $flags|count}
        <ul class="exp-ai-chain-flags">
{foreach $flags as $f}            <li>{$f|wash}</li>
{/foreach}
        </ul>
{/if}
        <form method="post" action={'expai/chain'|ezurl}>
            <input type="hidden" name="ExpAiRunID" value="{$run_row.id}" />
            <label class="exp-ai-label" for="exp-ai-chain-note">{'Note (required to ask for changes)'|i18n( 'extension/expai/chain' )}</label>
            <textarea id="exp-ai-chain-note" name="ExpAiNote" class="exp-ai-input box" rows="3" maxlength="4000"></textarea>
            <div class="exp-ai-actions">
                <input type="submit" class="defaultbutton exp-ai-btn" name="ExpAiChainApproveButton" value="{'Approve and continue'|i18n( 'extension/expai/chain' )}" />
                <input type="submit" class="button exp-ai-btn" name="ExpAiChainChangesButton" value="{'Ask for changes'|i18n( 'extension/expai/chain' )}" />
                <input type="submit" class="button exp-ai-btn" name="ExpAiChainStopButton" value="{'Stop the run'|i18n( 'extension/expai/chain' )}" />
            </div>
        </form>
    </section>
{elseif and( $can_act, $run.is_final|not )}
    <form method="post" action={'expai/chain'|ezurl} class="exp-ai-actions">
        <input type="hidden" name="ExpAiRunID" value="{$run_row.id}" />
{if eq( $run_row.state, 'held' )}        <input type="submit" class="defaultbutton exp-ai-btn" name="ExpAiChainResumeButton" value="{'Continue with a higher run budget'|i18n( 'extension/expai/chain' )}" />{/if}
        <input type="submit" class="button exp-ai-btn" name="ExpAiChainStopButton" value="{'Stop the run'|i18n( 'extension/expai/chain' )}" />
    </form>
{/if}

    <section class="exp-ai-card" aria-labelledby="exp-ai-chain-steps">
        <h2 id="exp-ai-chain-steps">{'Steps'|i18n( 'extension/expai/chain' )}</h2>
        <table class="list exp-ai-draft-table exp-ai-chain-table" cellspacing="0">
            <thead><tr><th>{'Step'|i18n( 'extension/expai/chain' )}</th><th>{'State'|i18n( 'extension/expai/chain' )}</th><th>{'Model and lane'|i18n( 'extension/expai/chain' )}</th><th>{'Score'|i18n( 'extension/expai/chain' )}</th><th>{'Tokens'|i18n( 'extension/expai/chain' )}</th><th>{'Cost'|i18n( 'extension/expai/chain' )}</th></tr></thead>
            <tbody>
{foreach $steps as $s}
                <tr class="exp-ai-chain-step-{$s.state|wash}">
                    <td>{$s.label|wash}{if $s.iteration} <small>({'round %n'|i18n( 'extension/expai/chain',, hash( '%n', $s.iteration|inc ) )})</small>{/if}
                        {if $s.summary}<br /><small>{$s.summary|wash}</small>{/if}
                        {if $s.note}<br /><small class="exp-ai-muted">{$s.note|wash}</small>{/if}
{if $s.issues|count}
                        <details><summary>{'%count issue(s)'|i18n( 'extension/expai/chain',, hash( '%count', $s.issues|count ) )}</summary><ul class="exp-ai-compact-list">
{foreach $s.issues as $i}                            <li><span class="exp-ai-badge exp-ai-sev-{$i.severity|wash}">{$i.severity|wash}</span> {if $i.field}<code>{$i.field|wash}</code> {/if}{$i.text|wash}</li>
{/foreach}
                        </ul></details>
{/if}
{if $s.checklist|count}
                        <details><summary>{'Checklist'|i18n( 'extension/expai/chain' )}</summary><ul class="exp-ai-compact-list">
{foreach $s.checklist as $c}                            <li>{if $c.ok}&#10003;{else}&#10007;{/if} {$c.item|wash}</li>
{/foreach}
                        </ul></details>
{/if}
                    </td>
                    <td>{$s.state|wash}{if $s.verdict} <span class="exp-ai-pill exp-ai-chain-verdict-{$s.verdict|wash}">{$s.verdict|wash}</span>{/if}{if and( $s.job_state, eq( $s.state, 'queued' ) )}<br /><small>{'job: %state'|i18n( 'extension/expai/chain',, hash( '%state', $s.job_state ) )|wash}</small>{/if}</td>
                    <td>{if $s.model}<code>{$s.model|wash}</code>{if $s.lane} <small>{$s.lane|wash}</small>{/if}{if $s.job_uuid}<br /><a href={concat( 'expqueue/job/', $s.job_uuid )|ezurl}><small>{'job'|i18n( 'extension/expai/chain' )}</small></a>{/if}{/if}</td>
                    <td>{if ge( $s.score, 0 )}{$s.score|wash}{/if}</td>
                    <td>{if $s.tokens_in}{$s.tokens_in|wash} / {$s.tokens_out|wash}{/if}</td>
                    <td>{if $s.cost_micros}{$s.cost|wash}{/if}</td>
                </tr>
{/foreach}
            </tbody>
        </table>
    </section>

{if $seo}
    <section class="exp-ai-card" aria-labelledby="exp-ai-chain-seo">
        <h2 id="exp-ai-chain-seo">{'Search and sharing'|i18n( 'extension/expai/chain' )}</h2>
        <dl class="exp-ai-status-list">
            <div><dt>{'Meta title'|i18n( 'extension/expai/chain' )}</dt><dd>{$seo.title|wash}</dd></div>
            <div><dt>{'Meta description'|i18n( 'extension/expai/chain' )}</dt><dd>{$seo.description|wash}</dd></div>
            <div><dt>{'Keywords'|i18n( 'extension/expai/chain' )}</dt><dd>{$seo.keywords|implode( ', ' )|wash}</dd></div>
        </dl>
{if $links|count}
        <h3>{'Links'|i18n( 'extension/expai/chain' )}</h3>
        <ul class="exp-ai-compact-list">
{foreach $links as $l}            <li>{if $l.ok}&#10003;{else}&#10007;{/if} <code>{$l.url|wash}</code>{if $l.why} <small>{$l.why|wash}</small>{/if}</li>
{/foreach}
        </ul>
{/if}
    </section>
{/if}

{if $document|count}
    <section class="exp-ai-card exp-ai-chain-document" aria-labelledby="exp-ai-chain-document">
        <h2 id="exp-ai-chain-document">{'The text so far'|i18n( 'extension/expai/chain' )}</h2>
{foreach $document as $d}
        <h3><code>{$d.identifier|wash}</code></h3>
        <div class="exp-ai-chain-field">{$d.html}</div>
{/foreach}
    </section>
{/if}
{if $translate}
    <p class="exp-ai-muted">{'Translation: handed to %handler for %languages.'|i18n( 'extension/expai/chain',, hash( '%handler', $translate.handler, '%languages', $translate.languages|implode( ', ' ) ) )|wash}</p>
{/if}
    <p><a href={'expai/chain'|ezurl}>{'All runs'|i18n( 'extension/expai/chain' )}</a></p>
</div>
