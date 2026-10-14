{* expai/log: the requests to the model (usage) and the versions written (drafts). *}
<div class="exp-ai exp-ai-log-page">
    <header class="exp-ai-head">
        <span class="exp-ai-mark" aria-hidden="true"></span>
        <div>
            <h1 class="context-title">{'Exponential Intelligence'|i18n( 'extension/expai/log' )}</h1>
            <p class="exp-ai-sub">{'Powered by Claude from Anthropic'|i18n( 'extension/expai/log' )}</p>
        </div>
    </header>
    <ul class="exp-ai-tabs">
        <li{if eq( $view, 'usage' )} class="exp-ai-current"{/if}><a href={concat( 'expai/log', $filter_path, $sort_path )|ezurl}>{'Requests and usage'|i18n( 'extension/expai/log' )}</a></li>
        <li{if eq( $view, 'drafts' )} class="exp-ai-current"{/if}><a href={'expai/log/(view)/drafts'|ezurl}>{'Versions written'|i18n( 'extension/expai/log' )}</a></li>
    </ul>
    <p class="exp-ai-intro">{if $can_see_all}{'Everyone\'s activity.'|i18n( 'extension/expai/log' )}{else}{'Your own activity.'|i18n( 'extension/expai/log' )}{/if}</p>

{if eq( $view, 'usage' )}
    <form class="exp-ai-card exp-ai-filters" method="post" action={'expai/log'|ezurl}>
        <label>{'From'|i18n( 'extension/expai/log' )} <input type="date" name="ExpAiFilter_from" value="{$query.from|wash}" /></label>
        <label>{'To'|i18n( 'extension/expai/log' )} <input type="date" name="ExpAiFilter_to" value="{$query.to|wash}" /></label>
{if $can_see_all}
        <label>{'User ID'|i18n( 'extension/expai/log' )} <input type="number" min="0" name="ExpAiFilter_user" value="{$query.user|wash}" /></label>
{/if}
        <label>{'Node ID'|i18n( 'extension/expai/log' )} <input type="number" min="0" name="ExpAiFilter_node" value="{$query.node|wash}" /></label>
        <label>{'Action'|i18n( 'extension/expai/log' )} <select name="ExpAiFilter_action"><option value="">{'All'|i18n( 'extension/expai/log' )}</option>{foreach $actions as $a}<option value="{$a|wash}"{if eq( $query.action, $a )} selected="selected"{/if}>{$a|wash}</option>{/foreach}</select></label>
        <label>{'Model'|i18n( 'extension/expai/log' )} <input type="text" name="ExpAiFilter_model" value="{$query.model|wash}" size="18" /></label>
{if gt( $providers|count, 1 )}
        <label>{'Provider'|i18n( 'extension/expai/log' )} <select name="ExpAiFilter_provider"><option value="">{'All'|i18n( 'extension/expai/log' )}</option>{foreach $providers as $pv}<option value="{$pv|wash}"{if eq( $query.provider, $pv )} selected="selected"{/if}>{$pv|wash}</option>{/foreach}</select></label>
        <label>{'Region'|i18n( 'extension/expai/log' )} <select name="ExpAiFilter_region"><option value="">{'All'|i18n( 'extension/expai/log' )}</option>{foreach array( 'us', 'eu', 'local' ) as $rg}<option value="{$rg}"{if eq( $query.region, $rg )} selected="selected"{/if}>{$rg|upcase}</option>{/foreach}</select></label>
{/if}
        <label class="exp-ai-check-label"><input type="checkbox" name="ExpAiFilter_errors" value="1"{if $query.errors} checked="checked"{/if} /> {'Errors only'|i18n( 'extension/expai/log' )}</label>
        <span class="exp-ai-actions">
            <input type="submit" class="defaultbutton exp-ai-btn" name="ExpAiFilterButton" value="{'Filter'|i18n( 'extension/expai/log' )}" />
            <a class="button exp-ai-btn" href={'expai/log'|ezurl}>{'Reset'|i18n( 'extension/expai/log' )}</a>
            <a class="button exp-ai-btn" href={concat( 'expai/log/(export)/csv', $filter_path, $sort_path )|ezurl}>{'Export CSV'|i18n( 'extension/expai/log' )}</a>
        </span>
    </form>
{if $query.unpriced}
    <p class="exp-ai-notice" role="status">{'Showing the requests without a price only.'|i18n( 'extension/expai/usage' )} <a href={'expai/log'|ezurl}>{'Show all'|i18n( 'extension/expai/log' )}</a></p>
{/if}
{if or( $query.request, $query.thread )}
    <p class="exp-ai-notice" role="status">{if $query.request}{'Showing request %id only.'|i18n( 'extension/expai/log',, hash( '%id', $query.request ) )|wash}{else}{'Showing the requests of thread %id only.'|i18n( 'extension/expai/log',, hash( '%id', $query.thread ) )|wash}{/if} <a href={'expai/log'|ezurl}>{'Show all'|i18n( 'extension/expai/log' )}</a></p>
{/if}

    {def $t = $stats.totals}
    <section class="exp-ai-card" aria-labelledby="exp-ai-totals-title">
        <h2 id="exp-ai-totals-title">{'Totals of the filtered requests'|i18n( 'extension/expai/log' )}</h2>
        <dl class="exp-ai-figures">
            <div><dt>{'Requests'|i18n( 'extension/expai/log' )}</dt><dd>{$t.requests}</dd></div>
            <div><dt>{'Input tokens'|i18n( 'extension/expai/log' )}</dt><dd>{$t.input}</dd></div>
            <div><dt>{'Output tokens'|i18n( 'extension/expai/log' )}</dt><dd>{$t.output}</dd></div>
            <div><dt>{'Cache write / read'|i18n( 'extension/expai/log' )}</dt><dd>{$t.cache_write} / {$t.cache_read}</dd></div>
            <div><dt>{'Estimated cost'|i18n( 'extension/expai/log' )}</dt><dd>{if $cost_enabled}{$t.cost|expai_money( $t.cost_approx )} {include uri='design:expai/parts/unpriced_note.tpl' count=$t.unpriced filter_path=$filter_path}{else}{'not configured'|i18n( 'extension/expai/log' )}{/if}</dd></div>
            <div><dt>{'Average latency'|i18n( 'extension/expai/log' )}</dt><dd>{$t.avg_latency} ms</dd></div>
            <div><dt>{'Error rate'|i18n( 'extension/expai/log' )}</dt><dd>{$t.error_rate} % ({$t.errors})</dd></div>
            <div><dt>{'Tool calls'|i18n( 'extension/expai/log' )}</dt><dd>{$t.tools}</dd></div>
            <div><dt>{'Drafts written'|i18n( 'extension/expai/log' )}</dt><dd>{$t.drafts}</dd></div>
        </dl>
        {if $stats.truncated}<p class="exp-ai-hint">{'Only the newest 20000 requests are counted; narrow the filter.'|i18n( 'extension/expai/log' )}</p>{/if}
    </section>
    {undef $t}

    <details class="exp-ai-card">
        <summary><h2>{'Per day'|i18n( 'extension/expai/log' )}</h2></summary>
        <table class="list exp-ai-draft-table" cellspacing="0">
            <thead><tr><th>{'Day'|i18n( 'extension/expai/log' )}</th><th>{'Requests'|i18n( 'extension/expai/log' )}</th><th>{'Input tokens'|i18n( 'extension/expai/log' )}</th><th>{'Output tokens'|i18n( 'extension/expai/log' )}</th><th>{'Cache write / read'|i18n( 'extension/expai/log' )}</th><th>{'Estimated cost'|i18n( 'extension/expai/log' )}</th><th>{'Errors'|i18n( 'extension/expai/log' )}</th></tr></thead>
            <tbody>
{foreach $stats.per_day as $d}
                <tr><td>{$d.day|wash}</td><td>{$d.requests}</td><td>{$d.input}</td><td>{$d.output}</td><td>{$d.cache_write} / {$d.cache_read}</td><td>{if $cost_enabled}{$d.cost|expai_money} {include uri='design:expai/parts/unpriced_note.tpl' count=$d.unpriced filter_path=concat( $filter_path, '/(from)/', $d.day, '/(to)/', $d.day ) short=true()}{else}-{/if}</td><td>{$d.errors}</td></tr>
{/foreach}
            </tbody>
        </table>
    </details>

{if $can_see_all}
    <details class="exp-ai-card">
        <summary><h2>{'Per user'|i18n( 'extension/expai/log' )}</h2></summary>
        <table class="list exp-ai-draft-table" cellspacing="0">
            <thead><tr><th>{'User ID'|i18n( 'extension/expai/log' )}</th><th>{'Requests'|i18n( 'extension/expai/log' )}</th><th>{'Input tokens'|i18n( 'extension/expai/log' )}</th><th>{'Output tokens'|i18n( 'extension/expai/log' )}</th><th>{'Cache write / read'|i18n( 'extension/expai/log' )}</th><th>{'Estimated cost'|i18n( 'extension/expai/log' )}</th><th>{'Errors'|i18n( 'extension/expai/log' )}</th></tr></thead>
            <tbody>
{foreach $stats.per_user as $u}
                <tr><td><a href={concat( 'expai/log/(user)/', $u.user_id )|ezurl}>{$u.user_id}</a></td><td>{$u.requests}</td><td>{$u.input}</td><td>{$u.output}</td><td>{$u.cache_write} / {$u.cache_read}</td><td>{if $cost_enabled}{$u.cost|expai_money} {include uri='design:expai/parts/unpriced_note.tpl' count=$u.unpriced filter_path=concat( '/(user)/', $u.user_id ) short=true()}{else}-{/if}</td><td>{$u.errors}</td></tr>
{/foreach}
            </tbody>
        </table>
    </details>
{/if}

    <section class="exp-ai-card" aria-labelledby="exp-ai-turns-title">
        <h2 id="exp-ai-turns-title">{'Turns'|i18n( 'extension/expai/log' )} ({$total})</h2>
        <p class="exp-ai-hint">{'A turn is one message in the panel, one field action or one "Fill the fields", with every request it led to. Open a turn for its requests and tool calls.'|i18n( 'extension/expai/log' )}
            {'Sort'|i18n( 'extension/expai/log' )}:
            {foreach hash( 'created', 'Time'|i18n( 'extension/expai/log' ), 'latency_ms', 'Latency'|i18n( 'extension/expai/log' ), 'input_tokens', 'Input tokens'|i18n( 'extension/expai/log' ), 'output_tokens', 'Output tokens'|i18n( 'extension/expai/log' ) ) as $key => $label}
                <a href={concat( 'expai/log', $filter_path, '/(sort)/', $key, '/(dir)/', cond( and( eq( $sort, $key ), eq( $dir, 'desc' ) ), 'asc', 'desc' ) )|ezurl}{if eq( $sort, $key )} aria-current="true"{/if}>{$label|wash}{if eq( $sort, $key )} {cond( eq( $dir, 'desc' ), '&darr;', '&uarr;' )}{/if}</a>
            {/foreach}</p>
{if $turns|count}
        <ol class="exp-ai-turns">
{foreach $turns as $turn}
            <li class="exp-ai-x">
                <details>
                    <summary class="exp-ai-turn-head">
                        <span class="exp-ai-x-time">{$turn.created|l10n( 'shortdatetime' )}</span>
                        <span><strong>{$turn.action|wash}</strong>{if $turn.provider} &middot; <span class="exp-ai-badge" title="{'Provider and region'|i18n( 'extension/expai/log' )}">{$turn.provider|wash}{if $turn.region} {$turn.region|upcase|wash}{/if}</span>{/if}{if $turn.node_id} &middot; {'node'|i18n( 'extension/expai/log' )} {$turn.node_id}{/if}{if $can_see_all} &middot; {'user'|i18n( 'extension/expai/log' )} {$turn.user_id}{/if}</span>
                        <span>{'%count request(s)'|i18n( 'extension/expai/log',, hash( '%count', $turn.requests ) )}</span>
                        <span>{'%in in / %out out'|i18n( 'extension/expai/log',, hash( '%in', $turn.input, '%out', $turn.output ) )}{if or( $turn.cache_write, $turn.cache_read )} &middot; {'cache %w / %r'|i18n( 'extension/expai/log',, hash( '%w', $turn.cache_write, '%r', $turn.cache_read ) )}{/if}</span>
                        <span>{$turn.latency} ms</span>
                        <span>{if $turn.cost_known}{$turn.cost|mul( 10000 )|round|div( 10000 )} {$currency|wash}{if $turn.cost_reported} ({'reported'|i18n( 'extension/expai/log' )}){/if}{elseif $cost_enabled}{'no price'|i18n( 'extension/expai/usage' )}{else}{'cost not configured'|i18n( 'extension/expai/log' )}{/if}</span>
                        {if $turn.reasoning}<span>{'%count reasoning tokens'|i18n( 'extension/expai/log',, hash( '%count', $turn.reasoning ) )}</span>{/if}
                        {if $turn.drafts}<span class="exp-ai-status-published">{'%count draft(s)'|i18n( 'extension/expai/log',, hash( '%count', $turn.drafts ) )}</span>{/if}
                        {include uri='design:expai/parts/links.tpl' links=$turn.links}
                        {if $turn.errors}<span class="exp-ai-step-failed">{'%count error(s)'|i18n( 'extension/expai/log',, hash( '%count', $turn.errors ) )}</span>{/if}
                    </summary>
                    <table class="list exp-ai-draft-table" cellspacing="0">
                        <thead><tr><th>#</th><th>{'Time'|i18n( 'extension/expai/log' )}</th><th>{'Model'|i18n( 'extension/expai/log' )}</th><th>{'Region'|i18n( 'extension/expai/log' )}</th><th>{'Tokens in / out / cache write / read'|i18n( 'extension/expai/log' )}</th><th>{'Stop'|i18n( 'extension/expai/log' )}</th><th>HTTP</th><th>{'Latency'|i18n( 'extension/expai/log' )}</th><th>{'Tools'|i18n( 'extension/expai/log' )}</th><th>{'Error'|i18n( 'extension/expai/log' )}</th></tr></thead>
                        <tbody>
{foreach $turn.rows as $r}
                            <tr>
                                <td>{$r.iteration}</td>
                                <td>{$r.created|l10n( 'shorttime' )}</td>
                                <td>{$r.model|wash}{if $r.model_alias}<br /><small>{$r.model_alias|wash}{if and( $r.tool_mode, ne( $r.tool_mode, 'native' ) )} &middot; {$r.tool_mode|wash}{/if}</small>{/if}</td>
                                <td>{if $r.region}{$r.region|upcase|wash}{if $r.retention} <small>({$r.retention|wash})</small>{/if}{else}-{/if}</td>
                                <td>{$r.input_tokens} / {$r.output_tokens} / {$r.cache_write_tokens} / {$r.cache_read_tokens}</td>
                                <td>{$r.stop_reason|wash}{if $r.retries} ({'%count retry'|i18n( 'extension/expai/log',, hash( '%count', $r.retries ) )}){/if}</td>
                                <td>{$r.http_status}</td>
                                <td>{$r.latency_ms} ms</td>
                                <td>{foreach $r.tools as $tool}<span class="exp-ai-tool{if $tool.ok|not} exp-ai-step-failed{/if}">{$tool.name|wash} ({$tool.ms} ms)</span> {/foreach}</td>
                                <td>{$r.error_class|wash} {$r.error_message|wash}</td>
                            </tr>
{/foreach}
                        </tbody>
                    </table>
                </details>
            </li>
{/foreach}
        </ol>
        {include name=navigator uri='design:navigator/google.tpl' page_uri=concat( 'expai/log', $filter_path, $sort_path ) item_count=$total view_parameters=hash( 'offset', $offset ) item_limit=$limit}
{else}
        <p class="exp-ai-empty">{'No requests match.'|i18n( 'extension/expai/log' )}</p>
{/if}
    </section>
{else}
{if $rows|count}
    <table class="list exp-ai-draft-table" cellspacing="0">
        <thead>
            <tr>
                <th>{'Content'|i18n( 'extension/expai/log' )}</th>
                <th>{'Version'|i18n( 'extension/expai/log' )}</th>
                <th>{'Written'|i18n( 'extension/expai/log' )}</th>
                <th>{'Where'|i18n( 'extension/expai/log' )}</th>
                <th>{'Status'|i18n( 'extension/expai/log' )}</th>
                {if $can_see_all}<th>{'User ID'|i18n( 'extension/expai/log' )}</th>{/if}
                <th>{'Request'|i18n( 'extension/expai/log' )}</th>
                <th>{'Model'|i18n( 'extension/expai/log' )}</th>
                <th><span class="exp-ai-visually-hidden">{'Links'|i18n( 'extension/expai/log' )}</span></th>
            </tr>
        </thead>
        <tbody>
{foreach $rows as $row}
            <tr>
                <td>{if and( $row.exists, $row.can_read, $row.version_exists )}<a href={concat( 'content/versionview/', $row.log.contentobject_id, '/', $row.log.version, '/', $row.log.language_code )|ezurl}>{$row.name|wash}</a>{elseif and( $row.exists, $row.can_read )}{$row.name|wash}{elseif $row.exists}{'(no access)'|i18n( 'extension/expai/log' )}{else}{'(removed)'|i18n( 'extension/expai/log' )}{/if}</td>
                <td>{$row.log.version}</td>
                <td>{$row.log.created|l10n( 'shortdatetime' )}</td>
                <td>{switch match=$row.log.source}{case match='edit'}{'Edit form'|i18n( 'extension/expai/log' )}{/case}{case}{'Panel'|i18n( 'extension/expai/log' )}{/case}{/switch}</td>
                <td>{switch match=$row.status}
                    {case match='deleted'}{'deleted'|i18n( 'extension/expai/links' )}{/case}
                    {case match='kept'}{'Saved as draft'|i18n( 'extension/expai/log' )}{/case}
                    {case match='pending'}{'Waiting for approval'|i18n( 'extension/expai/log' )}{/case}
                    {case match='published'}{'Published'|i18n( 'extension/expai/log' )}{/case}
                    {case match='discarded'}{'Discarded'|i18n( 'extension/expai/log' )}{/case}
                    {case}{'Draft'|i18n( 'extension/expai/log' )}{/case}
                {/switch}</td>
                {if $can_see_all}<td>{$row.log.user_id}</td>{/if}
                <td>{$row.log.prompt_summary|wash}</td>
                <td>{$row.log.model|wash}</td>
                <td>{include uri='design:expai/parts/links.tpl' links=$row.links}</td>
            </tr>
{/foreach}
        </tbody>
    </table>
    {include name=navigator uri='design:navigator/google.tpl' page_uri='expai/log/(view)/drafts' item_count=$total view_parameters=hash( 'offset', $offset ) item_limit=$limit}
{else}
    <p class="exp-ai-empty">{'Nothing written with Exponential Intelligence yet.'|i18n( 'extension/expai/log' )}</p>
{/if}
{/if}
</div>
