{* expai/dashboard: Exponential Intelligence on the Dashboard tab.
   Header with period and scope, a status line, four KPI cards, "Start writing" with the limits beside it, one chart,
   the Models card (expAiModelStats), the recent drafts. Every figure comes from expAiUsage (the same numbers as
   expai/log); costs from expAiCost. "Start writing" shows the state of the request sent last ("Watch this run",
   expAiRunWatch). *}
{ezcss_require( array( 'expai_dashboard.css' ) )}
{ezscript_require( array( 'expai_watch.js' ) )}
{def $c = $summary.current
     $p = $summary.previous
     $ch = $summary.change
     $tokens_total = $c.tokens
     $prices_note = 'Estimated at prices as of %date; Anthropic\'s invoice is authoritative.'|i18n( 'extension/expai/usage',, hash( '%date', $pricing.as_of ) )}
<div class="exp-ai exp-ai-dash" id="exp-ai-dashboard">

    <header class="exp-ai-dash-head">
        <div class="exp-ai-dash-title">
            <span class="exp-ai-mark" aria-hidden="true"></span>
            <div>
                <h1>{'Exponential Intelligence'|i18n( 'extension/expai/dashboard' )}</h1>
                <p class="exp-ai-sub">{'Powered by Claude from Anthropic'|i18n( 'extension/expai/dashboard' )}</p>
            </div>
            {include uri='design:expai/parts/help_link.tpl' page='dashboard' label='the dashboard'|i18n( 'extension/expai/help' ) text='?'}
        </div>
        <div class="exp-ai-dash-controls">
            <nav class="exp-ai-segmented" aria-label="{'Period'|i18n( 'extension/expai/usage' )}">
{foreach hash( 'today', 'Today'|i18n( 'extension/expai/usage' ), '7d', '7d'|i18n( 'extension/expai/usage' ), '30d', '30d'|i18n( 'extension/expai/usage' ), 'month', 'Month'|i18n( 'extension/expai/usage' ) ) as $key => $label}
                <a href={concat( 'expai/dashboard/(period)/', $key )|ezurl}{if eq( $period, $key )} aria-current="true"{/if}>{$label|wash}</a>
{/foreach}
            </nav>
{if $can_see_site}
            <nav class="exp-ai-segmented" aria-label="{'Scope'|i18n( 'extension/expai/usage' )}">
                <a href={'expai/dashboard/(scope)/me'|ezurl}{if eq( $scope, 'me' )} aria-current="true"{/if}>{'Mine'|i18n( 'extension/expai/usage' )}</a>
                <a href={'expai/dashboard/(scope)/site'|ezurl}{if eq( $scope, 'site' )} aria-current="true"{/if}>{'Site'|i18n( 'extension/expai/usage' )}</a>
            </nav>
{/if}
        </div>
    </header>

    <div class="exp-ai-statusline">
        {if and( $status.enabled, $status.configured )}<span class="exp-ai-pill exp-ai-pill-ok"><span class="exp-ai-dot" aria-hidden="true"></span>{'Ready'|i18n( 'extension/expai/dashboard' )}</span>{elseif $status.enabled}<span class="exp-ai-pill exp-ai-pill-warn"><span class="exp-ai-dot" aria-hidden="true"></span>{'Not configured'|i18n( 'extension/expai/dashboard' )}</span>{else}<span class="exp-ai-pill"><span class="exp-ai-dot" aria-hidden="true"></span>{'Switched off'|i18n( 'extension/expai/dashboard' )}</span>{/if}
        <span class="exp-ai-pill">{$model_label|wash}</span>
        <span class="exp-ai-pill">{if $status.publish_note}{'Approval workflow on'|i18n( 'extension/expai/dashboard' )}{else}{'No approval workflow'|i18n( 'extension/expai/dashboard' )}{/if}</span>
        <details class="exp-ai-details">
            <summary>{'Details'|i18n( 'extension/expai/dashboard' )}</summary>
            <dl>
                <div><dt>{'Model'|i18n( 'extension/expai/dashboard' )}</dt><dd>{include uri='design:expai/parts/model_select.tpl' reset=true()}</dd></div>
                <div><dt>{'You may create content'|i18n( 'extension/expai/dashboard' )}</dt><dd>{switch match=$status.create}{case match='yes'}{'everywhere'|i18n( 'extension/expai/dashboard' )}{/case}{case match='limited'}{'in some places (your role limits it)'|i18n( 'extension/expai/dashboard' )}{/case}{case}{'nowhere'|i18n( 'extension/expai/dashboard' )}{/case}{/switch}</dd></div>
                <div><dt>{'You may edit content'|i18n( 'extension/expai/dashboard' )}</dt><dd>{switch match=$status.edit}{case match='yes'}{'everywhere'|i18n( 'extension/expai/dashboard' )}{/case}{case match='limited'}{'in some places (your role limits it)'|i18n( 'extension/expai/dashboard' )}{/case}{case}{'nowhere'|i18n( 'extension/expai/dashboard' )}{/case}{/switch}</dd></div>
{if $status.placement}
                <div><dt>{'Site default model'|i18n( 'extension/expai/dashboard' )}</dt><dd>{'%model, set in expai.ini (%placement: %file); change it there.'|i18n( 'extension/expai/dashboard',, hash( '%model', concat( $status.model_label, ' (', $status.model, ')' ), '%placement', $status.placement.placement, '%file', $status.placement.file ) )|wash}</dd></div>
{/if}
{if $status.can_manage}
                <div><dt>{'Models and providers'|i18n( 'extension/expai/models' )}</dt><dd><a href={'expai/models'|ezurl}>{'Providers, regions, probe results and the local model'|i18n( 'extension/expai/models' )}</a></dd></div>
{/if}
{if fetch( 'user', 'has_access_to', hash( 'module', 'expai', 'function', 'summary' ) )}
                <div><dt>{'EI: Summary'|i18n( 'extension/expai/summary' )}</dt><dd><a href={'expai/summary'|ezurl}>{'What goes where, kept how long, used by whom, at what cost (printable)'|i18n( 'extension/expai/summary' )}</a></dd></div>
{/if}
                <div class="exp-ai-help-row"><dt>{'Missing alt text'|i18n( 'extension/expai/alttext' )}</dt><dd><a href={'expai/alttext'|ezurl}>{'Images without alt text, and suggestions as drafts'|i18n( 'extension/expai/help' )}</a></dd></div>
                <div class="exp-ai-help-row"><dt>{'Help'|i18n( 'extension/expai/help' )}</dt><dd><a href={'expai/help'|ezurl}>{'The documentation of Exponential Intelligence'|i18n( 'extension/expai/help' )}</a></dd></div>
{if $cost_enabled}
                <div><dt>{'Prices'|i18n( 'extension/expai/dashboard' )}</dt><dd>{$prices_note|wash}</dd></div>
{/if}
{if $rate_limits|not}
                <div><dt>{'API rate limits'|i18n( 'extension/expai/limits' )}</dt><dd>{'No request has reported its rate limits yet.'|i18n( 'extension/expai/limits' )}</dd></div>
{/if}
                <div><dt>{'Spend limit'|i18n( 'extension/expai/limits' )}</dt><dd>{'The organization\'s monthly spend limit is not available through an API key; see it in the Anthropic Console.'|i18n( 'extension/expai/limits' )} <a href="https://console.anthropic.com/settings/limits" rel="noopener noreferrer" target="_blank">console.anthropic.com</a></dd></div>
            </dl>
        </details>
    </div>

{if $error}<p class="exp-ai-error" role="alert">{$error|wash}</p>{/if}
    <p class="exp-ai-error exp-ai-dash-note" role="alert"></p>

    {* KPI strip *}
    <section class="exp-ai-kpis" aria-label="{if eq( $scope, 'site' )}{'Usage of the site'|i18n( 'extension/expai/usage' )}{else}{'Your usage'|i18n( 'extension/expai/usage' )}{/if}">
{if $cost_enabled}
        <div class="exp-ai-kpi" title="{$prices_note|wash}">
            <span class="exp-ai-kpi-label">{'Estimated cost'|i18n( 'extension/expai/usage' )}</span>
            <span class="exp-ai-kpi-value">{$c.cost|expai_money( $c.cost_approx )}</span>
            {include uri='design:expai/parts/trend.tpl' change=$ch.cost before=$p.cost}
        </div>
{/if}
        <div class="exp-ai-kpi">
            <span class="exp-ai-kpi-label">{'Tokens'|i18n( 'extension/expai/usage' )}</span>
            <span class="exp-ai-kpi-value" title="{$tokens_total|expai_number}">{$tokens_total|expai_tokens}</span>
            <span class="exp-ai-kpi-small" title="{'in'|i18n( 'extension/expai/usage' )} {$c.input|expai_number} &middot; {'out'|i18n( 'extension/expai/usage' )} {$c.output|expai_number} &middot; {'cached'|i18n( 'extension/expai/usage' )} {$c.cached|expai_number}">{$c.input|expai_tokens} {'in'|i18n( 'extension/expai/usage' )} &middot; {$c.output|expai_tokens} {'out'|i18n( 'extension/expai/usage' )} &middot; {$c.cached|expai_tokens} {'cached'|i18n( 'extension/expai/usage' )}</span>
            {include uri='design:expai/parts/trend.tpl' change=$ch.tokens before=$p.tokens}
        </div>
        <div class="exp-ai-kpi">
            <span class="exp-ai-kpi-label">{'Requests'|i18n( 'extension/expai/usage' )}</span>
            <span class="exp-ai-kpi-value">{$c.requests|expai_number}</span>
            <span class="exp-ai-kpi-small">{if $c.requests}{'average %time s'|i18n( 'extension/expai/usage',, hash( '%time', $c.avg_latency|div( 1000 )|expai_number( 1 ) ) )}{if $c.errors} &middot; {cond( eq( $c.errors, 1 ), '%count error'|i18n( 'extension/expai/usage',, hash( '%count', $c.errors ) ), '%count errors'|i18n( 'extension/expai/usage',, hash( '%count', $c.errors ) ) )}{/if}{else}&nbsp;{/if}</span>
            {include uri='design:expai/parts/trend.tpl' change=$ch.requests before=$p.requests}
        </div>
        <div class="exp-ai-kpi">
            <span class="exp-ai-kpi-label">{'Drafts'|i18n( 'extension/expai/usage' )}</span>
            <span class="exp-ai-kpi-value">{$c.written|expai_number}</span>
            <span class="exp-ai-kpi-small">{'%count sent or published'|i18n( 'extension/expai/usage',, hash( '%count', $c.sent ) )}</span>
            {include uri='design:expai/parts/trend.tpl' change=$ch.written before=$p.written}
        </div>
    </section>

{if gt( $per_provider|count, 1 )}
    <section class="exp-ai-card exp-ai-providers" aria-labelledby="exp-ai-providers-title">
        <h2 id="exp-ai-providers-title">{'By provider'|i18n( 'extension/expai/usage' )}</h2>
        <table class="list exp-ai-draft-table" cellspacing="0">
            <thead><tr><th>{'Provider'|i18n( 'extension/expai/usage' )}</th><th>{'Region'|i18n( 'extension/expai/usage' )}</th><th>{'Requests'|i18n( 'extension/expai/usage' )}</th><th>{'Tokens'|i18n( 'extension/expai/usage' )}</th>{if $cost_enabled}<th>{'Cost'|i18n( 'extension/expai/usage' )}</th>{/if}<th>{'Errors'|i18n( 'extension/expai/usage' )}</th></tr></thead>
            <tbody>
{foreach $per_provider as $pp}
                <tr><td>{$pp.provider|wash}</td><td>{if $pp.regions}{$pp.regions|upcase|wash}{else}-{/if}</td><td>{$pp.requests|expai_number}</td><td>{sum( $pp.input, $pp.output, $pp.cache_write, $pp.cache_read )|expai_tokens}</td>{if $cost_enabled}<td>{if $pp.cost_known}{$pp.cost|expai_money}{else}{'unknown'|i18n( 'extension/expai/usage' )}{/if}</td>{/if}<td>{$pp.errors}</td></tr>
{/foreach}
            </tbody>
        </table>
    </section>
{/if}

    <div class="exp-ai-dash-grid2">
        {* Start writing: the main action *}
        <section class="exp-ai-card exp-ai-start" aria-labelledby="exp-ai-start-title">
            <h2 id="exp-ai-start-title">{'Start writing'|i18n( 'extension/expai/dashboard' )}</h2>
{if $watch}
            <div class="exp-ai-watch" data-exp-ai-watch="1" data-active="{if $watch.active}1{else}0{/if}" aria-live="polite">{include uri='design:expai/parts/run_watch.tpl' watch=$watch}</div>
{/if}
            <form method="post" action={'expai/dashboard'|ezurl}>
{if $start.usable}
                <input type="hidden" name="ExpAiNodeID" value="{$start.node_id}" />
                <p class="exp-ai-location">
                    <span class="exp-ai-chip-location" title="{'Exponential Intelligence works on "%name" and everything below it: it may look up and write drafts in this location or any location under it, always only where your own permissions allow.'|i18n( 'extension/expai/dashboard',, hash( '%name', $start.name ) )|wash}">{$start.name|wash}</span>
                    <input type="submit" class="exp-ai-link" name="ChangeLocationButton" value="{'change'|i18n( 'extension/expai/dashboard' )}" />
                    <span class="exp-ai-hint">{'and everything below it, within your permissions'|i18n( 'extension/expai/dashboard' )}</span>
                </p>
{else}
                <p class="exp-ai-hint">{if eq( $start.reason, 'create' )}{'You may not create content in the default location. Choose a location for your request.'|i18n( 'extension/expai/dashboard' )}{else}{'The default location is not available to you. Choose a location for your request.'|i18n( 'extension/expai/dashboard' )}{/if}</p>
{/if}
                <label class="exp-ai-visually-hidden" for="exp-ai-prompt">{'What should Exponential Intelligence write?'|i18n( 'extension/expai/dashboard' )}</label>
                <textarea id="exp-ai-prompt" name="ExpAiPrompt" class="exp-ai-input exp-ai-input-large box" rows="5" maxlength="{ezini( 'AssistantSettings', 'MaxMessageLength', 'expai.ini' )|wash}"
                          placeholder="{'What should Exponential Intelligence write? For example: three short articles about our spring events.'|i18n( 'extension/expai/dashboard' )|wash}">{$prompt|wash}</textarea>
                <div class="exp-ai-start-actions">
                    {include uri='design:expai/parts/model_select.tpl' compact=true()}
{if $start.usable}
                    <input type="submit" class="defaultbutton exp-ai-btn exp-ai-btn-big" name="StartWritingButton" value="{'Write'|i18n( 'extension/expai/dashboard' )}" />
{else}
                    <input type="submit" class="defaultbutton exp-ai-btn exp-ai-btn-big" name="ChangeLocationButton" value="{'Choose the location'|i18n( 'extension/expai/dashboard' )}" />
{/if}
                    {* or start an AI chain with the same request and location (doc/chains.md) *}
                    {include uri='design:expai/parts/chain_pick.tpl' start=$start}
                </div>
            </form>
        </section>

        <div class="exp-ai-side">
            <section class="exp-ai-card" aria-labelledby="exp-ai-limits-title">
                <h2 id="exp-ai-limits-title">{'Limits'|i18n( 'extension/expai/limits' )}</h2>
{if $limits|count}
                <ul class="exp-ai-limit-rows">
{foreach $limits as $l}
                    {def $used_text = cond( eq( $l.unit, 'cost' ), $l.used|expai_money, $l.used|expai_number )
                         $max_text = cond( eq( $l.unit, 'cost' ), $l.max|expai_money, $l.max|expai_number )}
                    <li>
                        <span class="exp-ai-limit-name">{if is_set( $l.provider )}{cond( eq( $l.unit, 'cost' ), 'Provider %provider this month, cost'|i18n( 'extension/expai/limits',, hash( '%provider', $l.provider ) ), 'Provider %provider this month, tokens'|i18n( 'extension/expai/limits',, hash( '%provider', $l.provider ) ) )|wash}{else}{switch match=$l.key}
                            {case match='conversation'}{'This conversation'|i18n( 'extension/expai/limits' )}{/case}
                            {case match='session'}{'This session'|i18n( 'extension/expai/limits' )}{/case}
                            {case match='day_tokens'}{'Today, tokens'|i18n( 'extension/expai/limits' )}{/case}
                            {case match='day_cost'}{'Today, cost'|i18n( 'extension/expai/limits' )}{/case}
                            {case match='month_tokens'}{'This month, tokens'|i18n( 'extension/expai/limits' )}{/case}
                            {case match='month_cost'}{'This month, cost'|i18n( 'extension/expai/limits' )}{/case}
                            {case match='site_month_tokens'}{'Site this month, tokens'|i18n( 'extension/expai/limits' )}{/case}
                            {case}{'Site this month, cost'|i18n( 'extension/expai/limits' )}{/case}
                        {/switch}{/if}</span>
                        {include uri='design:expai/parts/bar.tpl' value=$l.used max=$l.max level=$l.level label=$l.key
                                 text='%used of %max (%percent %)'|i18n( 'extension/expai/limits',, hash( '%used', $used_text, '%max', $max_text, '%percent', $l.percent ) )}
                        <span class="exp-ai-hint">{switch match=$l.period}
                            {case match='conversation'}{'Resets with a new conversation.'|i18n( 'extension/expai/limits' )}{/case}
                            {case match='session'}{'Resets when you log in again.'|i18n( 'extension/expai/limits' )}{/case}
                            {case}{'Resets %time.'|i18n( 'extension/expai/limits',, hash( '%time', $l.reset|l10n( 'shortdatetime' ) ) )}{/case}
                        {/switch}</span>
                    </li>
                    {undef $used_text $max_text}
{/foreach}
                </ul>
{else}
                <p class="exp-ai-muted">{'No limits configured.'|i18n( 'extension/expai/limits' )}{if $status.placement} <a href="https://github.com/se7enxweb/expai/blob/main/doc/configuration.md#limits-optional-limits">{'How to set limits'|i18n( 'extension/expai/limits' )}</a>{/if}</p>
{/if}
{if $rate_limits}
                <h3 class="exp-ai-h3">{'API rate limits (as of %time)'|i18n( 'extension/expai/limits',, hash( '%time', $rate_limits.time|l10n( 'shorttime' ) ) )}</h3>
                <ul class="exp-ai-limit-rows">
{foreach hash( 'requests', 'Requests per minute'|i18n( 'extension/expai/limits' ), 'input-tokens', 'Input tokens per minute'|i18n( 'extension/expai/limits' ), 'output-tokens', 'Output tokens per minute'|i18n( 'extension/expai/limits' ) ) as $kind => $label}
{def $lim = first_set( $rate_limits.values[concat( 'anthropic-ratelimit-', $kind, '-limit' )], 0 )
     $rem = first_set( $rate_limits.values[concat( 'anthropic-ratelimit-', $kind, '-remaining' )], 0 )}
{if gt( $lim, 0 )}
                    {def $usedr = sub( $lim, $rem )
                         $pct = $usedr|mul( 100 )|div( $lim )|round}
                    <li><span class="exp-ai-limit-name">{$label|wash}</span>
                        {include uri='design:expai/parts/bar.tpl' value=$usedr max=$lim label=$kind level=cond( ge( $pct, 90 ), 'high', ge( $pct, 75 ), 'warn', 'ok' )
                                 text='%remaining of %max left'|i18n( 'extension/expai/limits',, hash( '%remaining', $rem|expai_number, '%max', $lim|expai_number ) )}</li>
                    {undef $usedr $pct}
{/if}
{undef $lim $rem}
{/foreach}
                </ul>
{if is_set( $rate_limits.values['retry-after'] )}<p class="exp-ai-error">{'The last request was rate limited; retry after %s seconds.'|i18n( 'extension/expai/limits',, hash( '%s', $rate_limits.values['retry-after'] ) )|wash}</p>{/if}
{/if}
            </section>

{* the monthly budget of queued requests (users with expai/log; doc/budgets.md) *}
{def $budget_status = fetch( 'expai', 'budget_status' )}
{if $budget_status}
            {include uri='design:expai/parts/budget_summary.tpl' status=$budget_status}
{/if}
{undef $budget_status}

{if $waiting|count}
            <section class="exp-ai-card" aria-labelledby="exp-ai-waiting-title">
                <h2 id="exp-ai-waiting-title">{'Waiting for my approval'|i18n( 'extension/expai/dashboard' )}</h2>
                <ul class="exp-ai-compact-list">
{foreach $waiting as $item}
                    <li><a href={$item.url|ezurl}>{$item.title|wash}</a> <span class="exp-ai-badge" title="{'Exponential Intelligence (Powered by Claude from Anthropic)'|i18n( 'extension/expai/label' )}"><span class="exp-ai-mark" aria-hidden="true"></span>{'Exponential Intelligence'|i18n( 'extension/expai/label' )}</span></li>
{/foreach}
                </ul>
            </section>
{/if}

{if $top_users|count}
            {def $top_max = 1}
            {foreach $top_users as $u}{set $top_max = max( $top_max, sum( $u.input, $u.output, $u.cache_write, $u.cache_read ) )}{/foreach}
            <section class="exp-ai-card" aria-labelledby="exp-ai-top-title">
                <h2 id="exp-ai-top-title">{'Top users this month'|i18n( 'extension/expai/limits' )}</h2>
                <ul class="exp-ai-limit-rows">
{foreach $top_users as $u}
                    {def $who = fetch( 'content', 'object', hash( 'object_id', $u.user_id ) )
                         $ut = sum( $u.input, $u.output, $u.cache_write, $u.cache_read )}
                    <li><span class="exp-ai-limit-name">{if $who}{$who.name|wash}{else}{'User %id'|i18n( 'extension/expai/limits',, hash( '%id', $u.user_id ) )}{/if}</span>
                        {include uri='design:expai/parts/bar.tpl' value=$ut max=$top_max label=$u.user_id
                                 text=concat( $ut|expai_tokens, ' ', 'tokens'|i18n( 'extension/expai/limits' ), cond( $cost_enabled, concat( ' · ', $u.cost|expai_money ), '' ) )}</li>
                    {undef $who $ut}
{/foreach}
                </ul>
            </section>
            {undef $top_max}
{/if}
        </div>
    </div>

    {* one chart *}
    <section class="exp-ai-card exp-ai-chart-card" aria-labelledby="exp-ai-chart-title">
        <div class="exp-ai-card-head">
            <h2 id="exp-ai-chart-title">{if eq( $series.metric, 'cost' )}{'Estimated cost per day'|i18n( 'extension/expai/usage' )}{else}{'Tokens per day'|i18n( 'extension/expai/usage' )}{/if}</h2>
            <nav class="exp-ai-segmented exp-ai-segmented-small" aria-label="{'Chart'|i18n( 'extension/expai/usage' )}">
                <a href={'expai/dashboard/(chart)/tokens'|ezurl}{if eq( $series.metric, 'tokens' )} aria-current="true"{/if}>{'Tokens'|i18n( 'extension/expai/usage' )}</a>
{if $cost_enabled}
                <a href={'expai/dashboard/(chart)/cost'|ezurl}{if eq( $series.metric, 'cost' )} aria-current="true"{/if}>{'Cost'|i18n( 'extension/expai/usage' )}</a>
{/if}
            </nav>
        </div>
        <div class="exp-ai-vchart" aria-hidden="true">
            <div class="exp-ai-vchart-axis">
                <span>{if eq( $series.metric, 'cost' )}{$series.max|expai_money}{else}{$series.max|expai_tokens}{/if}</span>
                <span>0</span>
            </div>
            <ol class="exp-ai-vchart-bars">
{foreach $series.series as $point}
                <li class="{if $point.today}exp-ai-today{/if}{if eq( $point.value, 0 )} exp-ai-empty-day{/if}" tabindex="0"
                    title="{$point.label|wash}: {if eq( $series.metric, 'cost' )}{$point.value|expai_money}{else}{$point.value|expai_number} {'tokens'|i18n( 'extension/expai/limits' )}{/if}">
                    <span class="exp-ai-vbar" style="height: {max( $point.percent, 2 )}%"></span>
                </li>
{/foreach}
            </ol>
            <div class="exp-ai-vchart-x">
                <span>{$series.series[0].label|wash}</span>
                <span>{'today'|i18n( 'extension/expai/usage' )}</span>
            </div>
        </div>
        <table class="exp-ai-visually-hidden">
            <caption>{if eq( $series.metric, 'cost' )}{'Estimated cost per day'|i18n( 'extension/expai/usage' )}{else}{'Tokens per day'|i18n( 'extension/expai/usage' )}{/if}</caption>
            <tr><th scope="col">{'Day'|i18n( 'extension/expai/log' )}</th><th scope="col">{if eq( $series.metric, 'cost' )}{'Cost'|i18n( 'extension/expai/usage' )}{else}{'Tokens'|i18n( 'extension/expai/usage' )}{/if}</th></tr>
{foreach $series.series as $point}
            <tr><td>{$point.day|wash}</td><td>{if eq( $series.metric, 'cost' )}{$point.value|expai_money}{else}{$point.value|expai_number}{/if}</td></tr>
{/foreach}
        </table>
        <p class="exp-ai-card-foot"><a href={concat( 'expai/log', $details_path )|ezurl}>{'Details in the log'|i18n( 'extension/expai/usage' )}</a></p>
    </section>

    {* models: the track record of each model in the period and scope (doc/dashboard.md "Models") *}
    {include uri='design:expai/parts/models_card.tpl' stats=$model_stats cost_enabled=$cost_enabled details_path=$details_path}

    {* threads: the latest of the user's own threads and Vector (doc/threads.md) *}
    {include uri='design:expai/parts/threads_card.tpl'}

    {* chains: the user's latest chain runs and the checkpoints waiting (doc/chains.md) *}
    {include uri='design:expai/parts/chain_runs.tpl'}

    {* recent drafts *}
    <section class="exp-ai-card" aria-labelledby="exp-ai-recent-title">
        <div class="exp-ai-card-head">
            <h2 id="exp-ai-recent-title">{'Recent drafts'|i18n( 'extension/expai/dashboard' )}</h2>
            <a href={'expai/log/(view)/drafts'|ezurl}>{'All in the log (%count)'|i18n( 'extension/expai/dashboard',, hash( '%count', $draft_count|expai_number ) )}</a>
        </div>
{if $drafts|count}
        <ul class="exp-ai-recent">
{foreach $drafts as $d}
            <li>
                <div class="exp-ai-recent-main">
                    {if $d.urls.view}<a href={$d.urls.view|ezurl}>{$d.name|wash}</a>{else}<span>{$d.name|wash}</span>{/if}
                    <span class="exp-ai-recent-meta">{$d.class_name|wash} &middot; {$d.created|l10n( 'shortdatetime' )}</span>
                </div>
                <span class="exp-ai-state exp-ai-state-{$d.status|wash}">{switch match=$d.status}
                    {case match='kept'}{'Saved as draft'|i18n( 'extension/expai/panel' )}{/case}
                    {case match='pending'}{'Waiting for approval'|i18n( 'extension/expai/panel' )}{/case}
                    {case match='published'}{'Published'|i18n( 'extension/expai/panel' )}{/case}
                    {case match='discarded'}{'Discarded'|i18n( 'extension/expai/dashboard' )}{/case}
                    {case}{'Draft'|i18n( 'extension/expai/panel' )}{/case}{/switch}</span>
                {include uri='design:expai/parts/links.tpl' links=$d.links}
            </li>
{/foreach}
        </ul>
{else}
        <p class="exp-ai-muted">{'Nothing written with Exponential Intelligence yet.'|i18n( 'extension/expai/dashboard' )}</p>
{/if}
    </section>
</div>
{undef $c $p $ch $tokens_total $prices_note}
