{* expai/models: providers, models, probe results, MCP and the local model server (policy expai/model_manage). Never shows a key. *}
{ezscript_require( array( 'expai_models.js' ) )}
<div class="exp-ai exp-ai-models-page" data-exp-ai-models="1" data-poll="{$poll_ms|wash}">
    <script type="application/json" class="exp-ai-models-strings">{hash(
        'starting', 'Queueing…'|i18n( 'extension/expai/modeltest' ),
        'failed', 'The request failed: %error'|i18n( 'extension/expai/modeltest' ) )|expai_json}</script>
    <header class="exp-ai-head">
        <span class="exp-ai-mark" aria-hidden="true"></span>
        <div>
            <h1 class="context-title">{'Models and providers'|i18n( 'extension/expai/models' )}</h1>
            <p class="exp-ai-sub">{'Exponential Intelligence'|i18n( 'extension/expai/models' )}</p>
        </div>
    </header>
{if $notice}<p class="exp-ai-notice" role="status">{$notice|wash}</p>{/if}
{if $error}<p class="exp-ai-error" role="alert">{$error|wash}</p>{/if}
<p class="exp-ai-models-live exp-ai-notice" role="status" hidden></p>
{if $key_problems|count}
    <section class="exp-ai-card exp-ai-warning" aria-labelledby="exp-ai-keys-title">
        <h2 id="exp-ai-keys-title">{'Keys in the wrong place'|i18n( 'extension/expai/models' )}</h2>
        <ul>{foreach $key_problems as $problem}<li>{$problem|wash}</li>{/foreach}</ul>
    </section>
{/if}

{foreach $providers as $p}
    <section class="exp-ai-card" aria-labelledby="exp-ai-provider-{$p.id|wash}">
        <h2 id="exp-ai-provider-{$p.id|wash}">{$p.label|wash} <small>({$p.id|wash})</small></h2>
        <dl class="exp-ai-figures">
            <div><dt>{'Type'|i18n( 'extension/expai/models' )}</dt><dd>{$p.type|wash}{if $p.flavor} / {$p.flavor|wash}{/if}</dd></div>
            <div><dt>{'Host'|i18n( 'extension/expai/models' )}</dt><dd>{$p.host|wash}</dd></div>
            <div><dt>{'Region'|i18n( 'extension/expai/models' )}</dt><dd>{if $p.region}{$p.region|upcase|wash}{else}{'unknown'|i18n( 'extension/expai/models' )}{/if}</dd></div>
            <div><dt>{'Retention'|i18n( 'extension/expai/models' )}</dt><dd>{$p.retention|expai_retention|wash}</dd></div>
            <div><dt>{'Terms checked'|i18n( 'extension/expai/models' )}</dt><dd>{if $p.terms_checked_at}{$p.terms_checked_at|wash}{else}{'not checked'|i18n( 'extension/expai/models' )}{/if}</dd></div>
{if $p.exchange}
            <div><dt>{'Prices'|i18n( 'extension/expai/models' )}</dt><dd>{if $p.exchange.rate}{'1 %currency = %rate %site'|i18n( 'extension/expai/models',, hash( '%currency', $p.exchange.currency, '%rate', $p.exchange.rate, '%site', $p.exchange.site ) )|wash}<br /><small>{if eq( $p.exchange.source, 'ecb' )}{'ECB reference rate of %date'|i18n( 'extension/expai/models',, hash( '%date', $p.exchange.date ) )|wash}{else}{'rate from the settings (%date)'|i18n( 'extension/expai/models',, hash( '%date', $p.exchange.date ) )|wash}{/if}</small>{else}<span class="exp-ai-step-failed">{'%currency: no exchange rate, cost unknown'|i18n( 'extension/expai/models',, hash( '%currency', $p.exchange.currency ) )|wash}</span>{/if}</dd></div>
{/if}
            <div><dt>{'Key'|i18n( 'extension/expai/models' )}</dt><dd>{if $p.key_present}{'present'|i18n( 'extension/expai/models' )}{elseif $p.ready}{'not needed'|i18n( 'extension/expai/models' )}{else}<span class="exp-ai-step-failed">{'missing'|i18n( 'extension/expai/models' )}</span>{/if}</dd></div>
        </dl>
        <table class="list exp-ai-draft-table" cellspacing="0">
            <thead><tr><th>{'Model'|i18n( 'extension/expai/models' )}</th><th>{'Id'|i18n( 'extension/expai/models' )}</th><th>{'Tools'|i18n( 'extension/expai/models' )}</th><th>{'Drafts'|i18n( 'extension/expai/models' )}</th><th>{'Probe'|i18n( 'extension/expai/models' )}</th><th>{'Cost'|i18n( 'extension/expai/models' )}</th><th><span class="exp-ai-visually-hidden">{'Test'|i18n( 'extension/expai/models' )}</span></th></tr></thead>
            <tbody>
{foreach $p.models as $m}
                <tr>
                    <td>{$m.label|wash}{if eq( $m.alias, $default_alias )} <em>{'(default)'|i18n( 'extension/expai/model' )}</em>{/if}{if $m.slow} <span class="exp-ai-badge">{'slow'|i18n( 'extension/expai/models' )}</span>{/if}{if eq( $m.prompt_profile, 'compact' )} <span class="exp-ai-badge">{'compact prompt'|i18n( 'extension/expai/models' )}</span>{/if}<br /><code>{$m.alias|wash}</code></td>
                    <td><code>{$m.id|wash}</code>{if $m.context}<br /><small>{'context %tokens tokens'|i18n( 'extension/expai/models',, hash( '%tokens', $m.context ) )|wash}</small>{/if}</td>
                    <td>{switch match=$m.tool_use}{case match='prompted'}{'prompted JSON'|i18n( 'extension/expai/models' )}{/case}{case match='none'}{'none'|i18n( 'extension/expai/models' )}{/case}{case}{'native'|i18n( 'extension/expai/models' )}{/case}{/switch}</td>
                    <td>{if $m.writes}{'may write'|i18n( 'extension/expai/models' )}{if eq( $m.write_gate, 'trusted' )} ({'trusted'|i18n( 'extension/expai/models' )}){/if}{else}{'read only'|i18n( 'extension/expai/models' )}<br /><small>{$m.write_refusal|wash}</small>{/if}</td>
                    <td>{if $m.probe_live}{'live %rate% (%passed/%tasks)'|i18n( 'extension/expai/models',, hash( '%rate', $m.probe_live.pass_rate, '%passed', $m.probe_live.passed, '%tasks', $m.probe_live.tasks ) )}<br /><small>{$m.probe_live.created|l10n( 'shortdatetime' )}</small>{else}{'no live probe'|i18n( 'extension/expai/models' )}{/if}{if $m.probe_mock}<br /><small>{'mock %rate%'|i18n( 'extension/expai/models',, hash( '%rate', $m.probe_mock.pass_rate ) )}</small>{/if}</td>
                    <td>{$m.cost|wash}</td>
                    <td>{if $p.ready}<form class="exp-ai-model-actions" method="post" action={'expai/models'|ezurl} data-alias="{$m.alias|wash}" data-active="{if $m.run.active}1{else}0{/if}"><input type="hidden" name="ExpAiTestModel" value="{$m.alias|wash}" /><input type="submit" class="button exp-ai-btn" name="ExpAiTestPromptButton" value="{'Test prompt'|i18n( 'extension/expai/models' )}"{if $m.run.active} disabled="disabled"{/if} />{if and( $m.run.managed, $m.run.queue )} <input type="submit" class="button exp-ai-btn" name="ExpAiLoadModelButton" value="{'Load model'|i18n( 'extension/expai/modeltest' )}" title="{'Loads the model into memory, so the next request does not wait for it'|i18n( 'extension/expai/modeltest' )|wash}"{if $m.run.active} disabled="disabled"{/if} />{/if}</form>
                        <div class="exp-ai-model-state" data-alias="{$m.alias|wash}">{include uri='design:expai/parts/model_test.tpl' t=$m.run part='state'}</div>{/if}</td>
                </tr>
{def $has_result = and( $p.ready, or( and( $m.run.test, $m.run.test.active|not ), and( $m.run.load, $m.run.load.active|not ) ) )}
                <tr class="exp-ai-test-row" data-alias="{$m.alias|wash}"{if $has_result|not} hidden="hidden"{/if}><td colspan="7" class="exp-ai-test-result" aria-live="polite">{if $has_result}{include uri='design:expai/parts/model_test.tpl' t=$m.run part='result'}{/if}</td></tr>
{undef $has_result}
{/foreach}
            </tbody>
        </table>
    </section>
{/foreach}
    <p class="exp-ai-hint">{'A model writes drafts only with native tool calling and a live probe pass rate of at least %threshold% (bin/php/expaiprobe.php --model=<alias> --live), unless it is marked trusted.'|i18n( 'extension/expai/models',, hash( '%threshold', $threshold ) )|wash}
        {if $policy_active}{'A data policy is active: content of some sections or classes only goes to providers in the regions it allows.'|i18n( 'extension/expai/models' )}{else}{'No data policy rule is set: content may go to every active provider.'|i18n( 'extension/expai/models' )}{/if}</p>

{foreach $local as $l}
    <section class="exp-ai-card" aria-labelledby="exp-ai-local-{$l.provider|wash}">
        <h2 id="exp-ai-local-{$l.provider|wash}">{'Local model server'|i18n( 'extension/expai/models' )} <small>({$l.provider|wash})</small></h2>
        <dl class="exp-ai-figures">
            <div><dt>{'State'|i18n( 'extension/expai/models' )}</dt><dd>{if $l.running}{'running'|i18n( 'extension/expai/models' )}{if $l.version} ({$l.version|wash}){/if}{else}{'stopped'|i18n( 'extension/expai/models' )}{/if}</dd></div>
            <div><dt>{'Loaded'|i18n( 'extension/expai/models' )}</dt><dd>{if $l.loaded|count}{foreach $l.loaded as $m}{$m.name|wash} ({$m.size_mb} MB) {/foreach}{else}-{/if}</dd></div>
            <div><dt>{'Installed'|i18n( 'extension/expai/models' )}</dt><dd>{if $l.installed|count}{foreach $l.installed as $m}{$m.name|wash} ({$m.size_mb} MB) {/foreach}{elseif $l.running}-{else}{'unknown while stopped'|i18n( 'extension/expai/models' )}{/if}</dd></div>
            <div><dt>{'Helper'|i18n( 'extension/expai/models' )}</dt><dd>{if $l.spool_writable}{'installed'|i18n( 'extension/expai/models' )}{if $l.pending} ({'%count waiting'|i18n( 'extension/expai/models',, hash( '%count', $l.pending ) )}){/if}{else}{'not installed'|i18n( 'extension/expai/models' )}{/if}{if $l.helper} &middot; {'last run %time: %result'|i18n( 'extension/expai/models',, hash( '%time', first_set( $l.helper.time, '' ), '%result', first_set( $l.helper.last, '' ) ) )|wash}{/if}</dd></div>
        </dl>
{if $l.spool_writable}
        <form class="exp-ai-filters" method="post" action={'expai/models'|ezurl}>
            <label>{'Action'|i18n( 'extension/expai/models' )} <select name="ExpAiLocalAction">
                <option value="start">{'Start the server'|i18n( 'extension/expai/models' )}</option>
                <option value="stop">{'Stop the server'|i18n( 'extension/expai/models' )}</option>
                <option value="pull">{'Pull the model'|i18n( 'extension/expai/models' )}</option>
                <option value="remove">{'Remove the model'|i18n( 'extension/expai/models' )}</option>
                <option value="refresh_status">{'Refresh the status'|i18n( 'extension/expai/models' )}</option>
            </select></label>
            <label>{'Model'|i18n( 'extension/expai/models' )} <select name="ExpAiLocalModel"><option value="">-</option>{foreach $l.models as $name}<option value="{$name|wash}">{$name|wash}</option>{/foreach}</select></label>
            <input type="submit" class="defaultbutton exp-ai-btn" name="ExpAiLocalActionButton" value="{'Send to the helper'|i18n( 'extension/expai/models' )}" />
        </form>
{else}
        <p class="exp-ai-hint">{'Install the helper of share/systemd/ (doc/local-models.md) to start, stop and pull models from here.'|i18n( 'extension/expai/models' )}</p>
{/if}
    </section>
{/foreach}

    <section class="exp-ai-card" aria-labelledby="exp-ai-mcp-title">
        <h2 id="exp-ai-mcp-title">{'MCP tools'|i18n( 'extension/expai/models' )}</h2>
        <p>{'Exponential bridge'|i18n( 'extension/expai/models' )}: {if and( $bridge.enabled, $bridge.available )}{'on'|i18n( 'extension/expai/models' )}{elseif $bridge.enabled}{'on, but the extension expmcp is not active'|i18n( 'extension/expai/models' )}{else}{'off'|i18n( 'extension/expai/models' )}{/if}{if $bridge.tools|count} &middot; {foreach $bridge.tools as $t}<code>exp__{$t|wash}</code> {/foreach}{/if}</p>
{if $mcp_servers|count}
        <ul>{foreach $mcp_servers as $s}<li><strong>{$s.label|wash}</strong> ({$s.id|wash}, {$s.host|wash}, {if $s.region}{$s.region|upcase|wash}{else}{'region unknown'|i18n( 'extension/expai/models' )}{/if}, {if $s.token}{'token present'|i18n( 'extension/expai/models' )}{else}{'no token'|i18n( 'extension/expai/models' )}{/if}): {foreach $s.tools as $t}<code>mcp__{$s.id|wash}__{$t|wash}</code> {/foreach}</li>{/foreach}</ul>
{else}
        <p class="exp-ai-hint">{'No external MCP server is configured.'|i18n( 'extension/expai/models' )}</p>
{/if}
    </section>
</div>
