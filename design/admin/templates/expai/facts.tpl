{* expai/summary (policy expai/facts): the "EI: Summary" page for a data protection officer (doc/summary.md). Everything is
   read from the settings, the roles, the probe results and the usage log as they are now. Never shows a key: only
   where it comes from. Printable: expai_facts.css hides the admin chrome for print (the browser's "Save as PDF"). *}
{ezcss_require( array( 'expai_facts.css' ) )}
{def $yes = 'on'|i18n( 'extension/expai/facts' )
     $no = 'off'|i18n( 'extension/expai/facts' )}
<div class="exp-ai exp-ai-facts" id="exp-ai-facts">
    <header class="exp-ai-head">
        <span class="exp-ai-mark" aria-hidden="true"></span>
        <div>
            <h1 class="context-title">{'EI: Summary'|i18n( 'extension/expai/facts' )}</h1>
            <p class="exp-ai-sub">{'What Exponential Intelligence sends where, how long it is kept, who may use it and what it costs. As of %date, %site, expai %version.'|i18n( 'extension/expai/facts',, hash( '%date', $facts.generated|l10n( 'shortdatetime' ), '%site', $facts.site, '%version', $facts.version ) )|wash}</p>
{if $facts.note}            <p class="exp-ai-facts-note">{$facts.note|wash}</p>{/if}
{if $facts.contact}            <p class="exp-ai-facts-note">{'Contact for questions about this processing: %contact'|i18n( 'extension/expai/facts',, hash( '%contact', $facts.contact ) )|wash}</p>{/if}
        </div>
        {include uri='design:expai/parts/help_link.tpl' page='summary' label='EI: Summary'|i18n( 'extension/expai/facts' ) text='?'}
        <p class="exp-ai-facts-noprint"><input type="button" class="button exp-ai-btn" onclick="window.print();" value="{'Print or save as PDF'|i18n( 'extension/expai/facts' )}" /></p>
    </header>

    <section class="exp-ai-card exp-ai-facts-section" id="exp-ai-facts-switch" aria-labelledby="exp-ai-facts-switch-title">
        <h2 id="exp-ai-facts-switch-title">{'Master switch and surfaces'|i18n( 'extension/expai/facts' )}</h2>
        <dl class="exp-ai-figures">
            <div><dt>{'Exponential Intelligence ([AISettings] Enabled)'|i18n( 'extension/expai/facts' )}</dt><dd class="exp-ai-facts-state-{if $facts.switches.master}on{else}off{/if}">{if $facts.switches.master}{$yes}{else}{$no}{/if}{if and( $facts.switches.master, $facts.switches.configured|not )} ({'not configured'|i18n( 'extension/expai/facts' )}){/if}</dd></div>
            <div><dt>{'Edit form controls'|i18n( 'extension/expai/facts' )}</dt><dd>{if $facts.switches.edit_form}{$yes}{else}{$no}{/if}</dd></div>
            <div><dt>{'Editor presets and Improve menu'|i18n( 'extension/expai/facts' )}</dt><dd>{if $facts.switches.presets}{$yes}{else}{$no}{/if}</dd></div>
            <div><dt>{'Vector workspace'|i18n( 'extension/expai/facts' )}</dt><dd>{if $facts.switches.vector}{$yes}{else}{$no}{/if}</dd></div>
            <div><dt>{'Translation sync'|i18n( 'extension/expai/facts' )}</dt><dd>{if $facts.switches.translation_sync}{$yes}{else}{$no}{/if}</dd></div>
            <div><dt>{'Users may choose a model'|i18n( 'extension/expai/facts' )}</dt><dd>{if $facts.switches.user_model_choice}{$yes}{else}{$no}{/if}</dd></div>
{if is_set( $facts.switches.service_tools )}
            <div><dt>{'Service tools'|i18n( 'extension/expai/facts' )}</dt><dd>{if $facts.switches.service_tools}{$yes}{else}{$no}{/if}</dd></div>
{/if}
        </dl>
        <p class="exp-ai-hint">{'Every request runs as the editor, within the editor\'s own permissions, and writes drafts only; nothing is published without the usual publish or approval.'|i18n( 'extension/expai/facts' )}</p>
    </section>

    <section class="exp-ai-card exp-ai-facts-section" id="exp-ai-facts-providers" aria-labelledby="exp-ai-facts-providers-title">
        <h2 id="exp-ai-facts-providers-title">{'Providers'|i18n( 'extension/expai/facts' )}</h2>
{foreach $facts.providers as $p}
        <div class="exp-ai-facts-provider" data-provider="{$p.id|wash}">
            <h3>{$p.label|wash} <small>({$p.id|wash}{if $p.host}, {$p.host|wash}{/if})</small></h3>
            <dl class="exp-ai-figures">
                <div><dt>{'Region'|i18n( 'extension/expai/facts' )}</dt><dd>{$p.region_label|wash}</dd></div>
                <div><dt>{'Retention'|i18n( 'extension/expai/facts' )}</dt><dd>{$p.retention|wash}</dd></div>
                <div><dt>{'Terms checked'|i18n( 'extension/expai/facts' )}</dt><dd>{if $p.terms_checked_at}{$p.terms_checked_at|wash}{else}{'not checked'|i18n( 'extension/expai/facts' )}{/if}</dd></div>
                <div><dt>{'Key'|i18n( 'extension/expai/facts' )}</dt><dd>{if $p.key.set}{'From %file (%placement)'|i18n( 'extension/expai/facts',, hash( '%file', $p.key.file, '%placement', $p.key.placement ) )|wash}{elseif $p.key.needed|not}{'No key needed'|i18n( 'extension/expai/facts' )}{else}<span class="exp-ai-step-failed">{'No key'|i18n( 'extension/expai/facts' )}</span>{/if}</dd></div>
                <div><dt>{'Who may use it'|i18n( 'extension/expai/facts' )}</dt><dd>{if $p.everyone}{'every user with expai/write'|i18n( 'extension/expai/facts' )}{else}{'users with expai/model_use for this provider'|i18n( 'extension/expai/facts' )}{/if}{if $p.roles|count}: {$p.roles|implode( ', ' )|wash}{/if}</dd></div>
                <div><dt>{'This month'|i18n( 'extension/expai/facts' )}</dt><dd>{'%requests requests, %tokens tokens, %cost'|i18n( 'extension/expai/facts',, hash( '%requests', $p.usage.requests, '%tokens', $p.usage.tokens, '%cost', $p.usage.cost ) )|wash}{foreach $p.limits as $l} &middot; {'limit %max per month'|i18n( 'extension/expai/facts',, hash( '%max', $l.max ) )|wash} ({$l.unit|wash}){/foreach}</dd></div>
            </dl>
            <table class="list exp-ai-facts-table" cellspacing="0">
                <thead><tr><th>{'Model'|i18n( 'extension/expai/facts' )}</th><th>{'Tools'|i18n( 'extension/expai/facts' )}</th><th>{'Write gate'|i18n( 'extension/expai/facts' )}</th><th>{'Probe'|i18n( 'extension/expai/facts' )}</th><th>{'Cost'|i18n( 'extension/expai/facts' )}</th></tr></thead>
                <tbody>
{foreach $p.models as $m}
                    <tr>
                        <td>{$m.label|wash}{if $m.default} <em>{'(site default)'|i18n( 'extension/expai/facts' )}</em>{/if}<br /><code>{$m.id|wash}</code></td>
                        <td>{$m.tool_use|wash}</td>
                        <td>{if $m.writes}{'may write drafts'|i18n( 'extension/expai/facts' )}{if eq( $m.write_gate, 'trusted' )} ({'trusted'|i18n( 'extension/expai/facts' )}){/if}{else}{'read only'|i18n( 'extension/expai/facts' )}<br /><small>{$m.write_refusal|wash}</small>{/if}</td>
                        <td>{if $m.probe}{'%rate% on %date'|i18n( 'extension/expai/facts',, hash( '%rate', $m.probe.pass_rate, '%date', $m.probe.created|l10n( 'shortdate' ) ) )|wash}{else}{'no live probe'|i18n( 'extension/expai/facts' )}{/if}</td>
                        <td>{$m.cost_class|wash}</td>
                    </tr>
{/foreach}
                </tbody>
            </table>
        </div>
{/foreach}
        <p class="exp-ai-hint">{'A model writes drafts only with native tool calling and a live probe pass rate of at least %threshold%, unless it is marked trusted.'|i18n( 'extension/expai/facts',, hash( '%threshold', first_set( $facts.providers.0.threshold, 90 ) ) )|wash}</p>
    </section>

    <section class="exp-ai-card exp-ai-facts-section" id="exp-ai-facts-lanes" aria-labelledby="exp-ai-facts-lanes-title">
        <h2 id="exp-ai-facts-lanes-title">{'Lanes and their retention'|i18n( 'extension/expai/facts' )}</h2>
        <table class="list exp-ai-facts-table" cellspacing="0">
            <thead><tr><th>{'Provider'|i18n( 'extension/expai/facts' )}</th><th>{'Lane'|i18n( 'extension/expai/facts' )}</th><th>{'Kind'|i18n( 'extension/expai/facts' )}</th><th>{'Retention'|i18n( 'extension/expai/facts' )}</th><th>{'Price'|i18n( 'extension/expai/facts' )}</th><th>{'In use'|i18n( 'extension/expai/facts' )}</th></tr></thead>
            <tbody>
{foreach $facts.providers as $p}{foreach $p.lanes as $l}
                <tr>
                    <td>{$p.label|wash}</td>
                    <td>{$l.name|wash}</td>
                    <td>{if $l.batch}{'batch'|i18n( 'extension/expai/facts' )}{else}{'direct'|i18n( 'extension/expai/facts' )}{/if}</td>
                    <td>{$l.retention|wash}{if $l.zero_retention} ({'nothing kept'|i18n( 'extension/expai/facts' )}){/if}{if $l.delete_after_fetch}<br /><small>{'deleted by expai after the results are fetched'|i18n( 'extension/expai/facts' )}</small>{/if}</td>
                    <td>{$l.factor}%</td>
                    <td>{if $l.usable}{$yes}{else}{$no}{/if}</td>
                </tr>
{/foreach}{/foreach}
            </tbody>
        </table>
    </section>

    <section class="exp-ai-card exp-ai-facts-section" id="exp-ai-facts-policy" aria-labelledby="exp-ai-facts-policy-title">
        <h2 id="exp-ai-facts-policy-title">{'Data policy'|i18n( 'extension/expai/facts' )}</h2>
{if $facts.policy.active|not}
        <p>{'No data policy rule is set: content may go to every active provider above.'|i18n( 'extension/expai/facts' )}</p>
{else}
        <dl class="exp-ai-figures">
            <div><dt>{'All content'|i18n( 'extension/expai/facts' )}</dt><dd>{if $facts.policy.default_regions|count}{$facts.policy.default_regions|implode( ', ' )|wash}{else}{'every region'|i18n( 'extension/expai/facts' )}{/if}</dd></div>
            <div><dt>{'Zero retention for all content'|i18n( 'extension/expai/facts' )}</dt><dd>{if $facts.policy.zero_retention}{$yes}{else}{$no}{/if}</dd></div>
        </dl>
{if or( $facts.policy.sections|count, $facts.policy.classes|count )}
        <table class="list exp-ai-facts-table" cellspacing="0">
            <thead><tr><th>{'Content'|i18n( 'extension/expai/facts' )}</th><th>{'May go to the regions'|i18n( 'extension/expai/facts' )}</th></tr></thead>
            <tbody>
{foreach $facts.policy.sections as $r}<tr><td>{'Section %name'|i18n( 'extension/expai/facts',, hash( '%name', $r.what ) )|wash}</td><td>{$r.regions|wash}</td></tr>{/foreach}
{foreach $facts.policy.classes as $r}<tr><td>{'Class %name'|i18n( 'extension/expai/facts',, hash( '%name', $r.what ) )|wash}</td><td>{$r.regions|wash}</td></tr>{/foreach}
            </tbody>
        </table>
{/if}
{if or( $facts.policy.zero_retention_sections|count, $facts.policy.zero_retention_classes|count )}
        <p>{'Zero retention is required for: %list'|i18n( 'extension/expai/facts',, hash( '%list', $facts.policy.zero_retention_sections|merge( $facts.policy.zero_retention_classes )|implode( ', ' ) ) )|wash}</p>
{/if}
{/if}
        <p class="exp-ai-hint">{'Images, files and relations are never sent; only the text fields the editor works on and their context.'|i18n( 'extension/expai/facts' )}</p>
    </section>

    <section class="exp-ai-card exp-ai-facts-section" id="exp-ai-facts-limits" aria-labelledby="exp-ai-facts-limits-title">
        <h2 id="exp-ai-facts-limits-title">{'Limits, budget and usage this month'|i18n( 'extension/expai/facts' )}</h2>
        <p>{'All users this month: %requests requests, %tokens tokens, %cost (estimated from list prices; the provider\'s invoice is authoritative).'|i18n( 'extension/expai/facts',, hash( '%requests', $facts.limits.month.requests, '%tokens', $facts.limits.month.tokens, '%cost', $facts.limits.month.cost ) )|wash}</p>
{if $facts.limits.budget}
        <p>{'Monthly budget of queued requests: %budget, spent %spent.'|i18n( 'extension/expai/facts',, hash( '%budget', $facts.limits.budget, '%spent', $facts.limits.budget_spent ) )|wash}</p>
{/if}
{if $facts.limits.rows|count}
        <table class="list exp-ai-facts-table" cellspacing="0">
            <thead><tr><th>{'Limit'|i18n( 'extension/expai/facts' )}</th><th>{'For'|i18n( 'extension/expai/facts' )}</th><th>{'Maximum'|i18n( 'extension/expai/facts' )}</th><th>{'Used'|i18n( 'extension/expai/facts' )}</th></tr></thead>
            <tbody>
{foreach $facts.limits.rows as $r}
                <tr><td><code>{$r.setting|wash}{if $r.provider}[{$r.provider|wash}]{/if}</code></td><td>{if eq( $r.scope, 'site' )}{'the whole site'|i18n( 'extension/expai/facts' )}{else}{'each user'|i18n( 'extension/expai/facts' )}{/if}, {$r.period|wash}</td><td>{$r.max|wash}</td><td>{if $r.used}{$r.used|wash} ({$r.percent}%){else}-{/if}</td></tr>
{/foreach}
            </tbody>
        </table>
{else}
        <p>{'No limit is set ([Limits] in expai.ini).'|i18n( 'extension/expai/facts' )}</p>
{/if}
    </section>

    <section class="exp-ai-card exp-ai-facts-section" id="exp-ai-facts-tools" aria-labelledby="exp-ai-facts-tools-title">
        <h2 id="exp-ai-facts-tools-title">{'Tools that reach outside the site'|i18n( 'extension/expai/facts' )}</h2>
        <dl class="exp-ai-figures">
            <div><dt>web_fetch</dt><dd>{if $facts.tools.web_fetch.configured}{'on, for: %domains'|i18n( 'extension/expai/facts',, hash( '%domains', $facts.tools.web_fetch.domains|implode( ', ' ) ) )|wash}{if $facts.tools.web_fetch.robots} ({'respects robots.txt'|i18n( 'extension/expai/facts' )}){/if}{else}{$no}{/if}</dd></div>
            <div><dt>web_search</dt><dd>{if $facts.tools.web_search.configured}{'on: %provider, region %region, retention %retention'|i18n( 'extension/expai/facts',, hash( '%provider', $facts.tools.web_search.provider, '%region', $facts.tools.web_search.region_label, '%retention', $facts.tools.web_search.retention ) )|wash}{if $facts.tools.web_search.key.set}; {'key from %file'|i18n( 'extension/expai/facts',, hash( '%file', $facts.tools.web_search.key.file ) )|wash}{/if}{else}{$no}{/if}</dd></div>
            <div><dt>project_history</dt><dd>{if $facts.tools.project_history.configured}{'on, %count repositories'|i18n( 'extension/expai/facts',, hash( '%count', $facts.tools.project_history.repositories ) )|wash}{else}{$no}{/if}</dd></div>
            <div><dt>{'MCP bridge (site tools in process)'|i18n( 'extension/expai/facts' )}</dt><dd>{if $facts.tools.mcp_bridge.enabled}{'on: %tools'|i18n( 'extension/expai/facts',, hash( '%tools', $facts.tools.mcp_bridge.tools|implode( ', ' ) ) )|wash}{else}{$no}{/if}</dd></div>
            <div><dt>{'External MCP servers'|i18n( 'extension/expai/facts' )}</dt><dd>{if $facts.tools.mcp_servers|count}{foreach $facts.tools.mcp_servers as $s}{$s.label|wash} ({$s.host|wash}, {$s.region_label|wash}{if $s.token.set}, {'token from %file'|i18n( 'extension/expai/facts',, hash( '%file', $s.token.file ) )|wash}{/if}){delimiter}; {/delimiter}{/foreach}{else}{'none'|i18n( 'extension/expai/facts' )}{/if}</dd></div>
        </dl>
    </section>

    <section class="exp-ai-card exp-ai-facts-section" id="exp-ai-facts-retention" aria-labelledby="exp-ai-facts-retention-title">
        <h2 id="exp-ai-facts-retention-title">{'What this site keeps'|i18n( 'extension/expai/facts' )}</h2>
        <dl class="exp-ai-figures">
            <div><dt>{'Usage log (tokens, cost, model; user ids, no names)'|i18n( 'extension/expai/facts' )}</dt><dd>{if $facts.retention.usage_days}{'%days days'|i18n( 'extension/expai/facts',, hash( '%days', $facts.retention.usage_days ) )|wash}{else}{'kept'|i18n( 'extension/expai/facts' )}{/if}</dd></div>
            <div><dt>{'Full requests in the log'|i18n( 'extension/expai/facts' )}</dt><dd>{if $facts.retention.log_requests}<span class="exp-ai-step-failed">{$yes}</span>{else}{$no}{/if}</dd></div>
            <div><dt>{'Summary of the editor\'s request in the draft log'|i18n( 'extension/expai/facts' )}</dt><dd>{'at most %chars characters'|i18n( 'extension/expai/facts',, hash( '%chars', $facts.retention.prompt_summary ) )|wash}</dd></div>
            <div><dt>{'Thread events, proposals, checkpoints'|i18n( 'extension/expai/facts' )}</dt><dd>{'%events / %proposals / %checkpoints days'|i18n( 'extension/expai/facts',, hash( '%events', $facts.retention.thread_events, '%proposals', $facts.retention.thread_proposals, '%checkpoints', $facts.retention.thread_checkpoints ) )|wash}</dd></div>
        </dl>
    </section>

    <section class="exp-ai-card exp-ai-facts-section" id="exp-ai-facts-roles" aria-labelledby="exp-ai-facts-roles-title">
        <h2 id="exp-ai-facts-roles-title">{'Roles with Exponential Intelligence policies'|i18n( 'extension/expai/facts' )}</h2>
{if $facts.roles|count}
        <table class="list exp-ai-facts-table" cellspacing="0">
            <thead><tr><th>{'Role'|i18n( 'extension/expai/facts' )}</th><th>{'Policies'|i18n( 'extension/expai/facts' )}</th></tr></thead>
            <tbody>
{foreach $facts.roles as $r}
                <tr><td>{$r.name|wash}</td><td>{foreach $r.policies as $pol}<code>expai/{$pol.function|wash}</code>{if $pol.limitations|count} ({foreach $pol.limitations as $id => $values}{$id|wash}: {$values|implode( ', ' )|wash}{delimiter}; {/delimiter}{/foreach}){/if}{delimiter}, {/delimiter}{/foreach}</td></tr>
{/foreach}
            </tbody>
        </table>
{else}
        <p>{'No role has a policy of Exponential Intelligence.'|i18n( 'extension/expai/facts' )}</p>
{/if}
    </section>
</div>
{undef $yes $no}
