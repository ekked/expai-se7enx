{* Dashboard card "Models" (expai/dashboard, doc/dashboard.md "Models"): the track record of each model in the period
   and scope of the dashboard (expAiModelStats::forPeriod() as $stats). Requests and success rate, errors by kind, how
   often the fallback replaced the model, latency per request (median, 95th percentile) and per day, output speed and
   time per turn, cost, the newest test prompt and probe. Every model links to its requests in the log ($details_path:
   the period and user as view parameters of expai/log), and its errors to the failed ones only. *}
{def $exp_ai_queue = fetch( 'user', 'has_access_to', hash( 'module', 'expqueue', 'function', 'view' ) )
     $exp_ai_kinds = hash( 'timeout', 'timeout'|i18n( 'extension/expai/modelstats' ), 'unreachable', 'unreachable'|i18n( 'extension/expai/modelstats' ),
                           'http', 'HTTP error'|i18n( 'extension/expai/modelstats' ), 'veto', 'refused'|i18n( 'extension/expai/modelstats' ),
                           'limit', 'limit'|i18n( 'extension/expai/modelstats' ), 'budget', 'budget'|i18n( 'extension/expai/modelstats' ),
                           'stopped', 'stopped'|i18n( 'extension/expai/modelstats' ), 'other', 'other'|i18n( 'extension/expai/modelstats' ) )}
<section class="exp-ai-card exp-ai-models-card" aria-labelledby="exp-ai-models-title">
    <div class="exp-ai-card-head">
        <h2 id="exp-ai-models-title">{'Models'|i18n( 'extension/expai/modelstats' )}</h2>
{if fetch( 'user', 'has_access_to', hash( 'module', 'expai', 'function', 'model_manage' ) )}
        <a class="exp-ai-card-link" href={'expai/models'|ezurl}>{'Test and manage models'|i18n( 'extension/expai/modelstats' )}</a>
{/if}
    </div>
{if $stats.models|count}
    <div class="exp-ai-ms-scroll">
    <table class="exp-ai-ms-table">
        <thead><tr>
            <th scope="col">{'Model'|i18n( 'extension/expai/modelstats' )}</th>
            <th scope="col">{'Requests'|i18n( 'extension/expai/modelstats' )}</th>
            <th scope="col">{'Latency (median)'|i18n( 'extension/expai/modelstats' )}</th>
            <th scope="col">{'Speed (median)'|i18n( 'extension/expai/modelstats' )}</th>
{if $cost_enabled}            <th scope="col">{'Cost'|i18n( 'extension/expai/modelstats' )}</th>{/if}
            <th scope="col">{'Per day'|i18n( 'extension/expai/modelstats' )}</th>
            <th scope="col">{'Test and probe'|i18n( 'extension/expai/modelstats' )}</th>
        </tr></thead>
        <tbody>
{foreach $stats.models as $m}
            <tr{if $m.slow} class="exp-ai-ms-slow"{/if}>
                <th scope="row" class="exp-ai-ms-model">
                    {if $m.log_key}<a href={concat( 'expai/log/(model)/', $m.log_key, $details_path )|ezurl}>{$m.label|wash}</a>{else}{$m.label|wash}{/if}
                    <span class="exp-ai-ms-badges">{if $m.provider}<span class="exp-ai-ms-badge">{$m.provider|wash}</span>{/if}{if and( $m.region, ne( $m.region, $m.provider ) )}<span class="exp-ai-ms-badge exp-ai-ms-region">{$m.region|upcase|wash}</span>{/if}{if $m.slow}<span class="exp-ai-ms-badge exp-ai-ms-flag">{'slow: ~%seconds s per step'|i18n( 'extension/expai/modelstats',, hash( '%seconds', $m.slow_seconds ) )|wash}</span>{/if}</span>
                    {if $m.known}<code class="exp-ai-ms-alias">{$m.key|wash}</code>{/if}
                </th>
                <td>
                    <span class="exp-ai-ms-big">{$m.requests|expai_number}</span>
{if $m.requests}
                    <span class="exp-ai-ms-small{if lt( $m.success_rate, 90 )} exp-ai-ms-bad{/if}">{'%rate % successful'|i18n( 'extension/expai/modelstats',, hash( '%rate', cond( eq( $m.ok, 0 ), '0', $m.errors, $m.success_rate|expai_number( 1 ), '100' ) ) )}</span>
{/if}
{if $m.errors}
                    <span class="exp-ai-ms-small">{if $m.log_key}<a href={concat( 'expai/log/(model)/', $m.log_key, $details_path, '/(errors)/1' )|ezurl}>{cond( eq( $m.errors, 1 ), '1 error'|i18n( 'extension/expai/modelstats' ), '%count errors'|i18n( 'extension/expai/modelstats',, hash( '%count', $m.errors ) ) )}</a>{else}{cond( eq( $m.errors, 1 ), '1 error'|i18n( 'extension/expai/modelstats' ), '%count errors'|i18n( 'extension/expai/modelstats',, hash( '%count', $m.errors ) ) )}{/if}:
                        {foreach $m.kinds as $kind => $count}{delimiter}, {/delimiter}{$count} {$exp_ai_kinds[$kind]|wash}{/foreach}</span>
{/if}
{if $m.replaced}
                    <span class="exp-ai-ms-small">{'replaced by the fallback %count times'|i18n( 'extension/expai/modelstats',, hash( '%count', $m.replaced ) )}: {foreach $m.replaced_reasons as $reason => $count}{delimiter}, {/delimiter}{$count} {first_set( $exp_ai_kinds[$reason], $reason )|wash}{/foreach}</span>
{/if}
{if $m.stood_in}
                    <span class="exp-ai-ms-small">{'answered %count for another model'|i18n( 'extension/expai/modelstats',, hash( '%count', $m.stood_in ) )}</span>
{/if}
                </td>
                <td>
{if $m.latency_median_ms|is_null|not}
                    <span class="exp-ai-ms-big">{$m.latency_median_ms|div( 1000 )|expai_number( 1 )} s</span>
                    <span class="exp-ai-ms-small">{'p95 %seconds s'|i18n( 'extension/expai/modelstats',, hash( '%seconds', $m.latency_p95_ms|div( 1000 )|expai_number( 1 ) ) )}</span>
{if $m.prompt_median|is_null|not}
                    <span class="exp-ai-ms-small">{'prompt %tokens tokens (median)'|i18n( 'extension/expai/modelstats',, hash( '%tokens', $m.prompt_median|expai_number ) )}{if gt( $m.cache_percent, 0 )}, {'%percent % from the cache'|i18n( 'extension/expai/modelstats',, hash( '%percent', $m.cache_percent ) )}{/if}</span>
{/if}
{else}<span class="exp-ai-muted">&ndash;</span>{/if}
                </td>
                <td>
{if $m.tps_median|is_null|not}<span class="exp-ai-ms-big">{'%rate tokens/s'|i18n( 'extension/expai/modelstats',, hash( '%rate', $m.tps_median|expai_number( 1 ) ) )}</span>{else}<span class="exp-ai-muted">&ndash;</span>{/if}
{if $m.turn_median_ms|is_null|not}
                    <span class="exp-ai-ms-small">{'%seconds s per turn'|i18n( 'extension/expai/modelstats',, hash( '%seconds', $m.turn_median_ms|div( 1000 )|expai_number( 1 ) ) )}</span>
{/if}
                </td>
{if $cost_enabled}
                <td>{if $m.cost_known}{$m.cost|expai_money}{else}<span class="exp-ai-muted">{'unknown'|i18n( 'extension/expai/modelstats' )}</span>{/if}</td>
{/if}
                <td>
{if gt( $m.per_day|count, 1 )}
                    <ol class="exp-ai-ms-spark" aria-hidden="true">{foreach $m.per_day as $d}<li title="{$d.day|wash}: {if $d.ms|is_null}{'no successful request'|i18n( 'extension/expai/modelstats' )}{else}{$d.ms|div( 1000 )|expai_number( 1 )} s{/if}"><span style="height: {cond( $d.ms|is_null, 0, max( $d.percent, 6 ) )}%"></span></li>{/foreach}</ol>
                    <span class="exp-ai-visually-hidden">{'Median latency per day:'|i18n( 'extension/expai/modelstats' )} {foreach $m.per_day as $d}{delimiter}; {/delimiter}{$d.day|wash} {if $d.ms|is_null}&ndash;{else}{$d.ms|div( 1000 )|expai_number( 1 )} s{/if}{/foreach}</span>
{else}<span class="exp-ai-muted">&ndash;</span>{/if}
                </td>
                <td class="exp-ai-ms-checks">
{if $m.test}
                    <span class="exp-ai-ms-small">{if $m.test.ok}{'Test %time:'|i18n( 'extension/expai/modelstats',, hash( '%time', $m.test.finished|l10n( 'shortdatetime' ) ) )}
                        {if $m.test.ttft_s|is_null|not}{'%seconds s to the first token'|i18n( 'extension/expai/modelstats',, hash( '%seconds', $m.test.ttft_s|expai_number( 1 ) ) )}, {/if}{if $m.test.out_tps|is_null|not}{'%rate tokens/s'|i18n( 'extension/expai/modelstats',, hash( '%rate', $m.test.out_tps|expai_number( 1 ) ) )}, {/if}{'%seconds s in all'|i18n( 'extension/expai/modelstats',, hash( '%seconds', $m.test.total_s|expai_number( 1 ) ) )}{else}<span class="exp-ai-ms-bad">{'Test %time failed'|i18n( 'extension/expai/modelstats',, hash( '%time', $m.test.finished|l10n( 'shortdatetime' ) ) )}</span>{/if}
                        {if $m.test.usage_log_id}<a href={concat( 'expai/log/(request)/', $m.test.usage_log_id )|ezurl}>{'log'|i18n( 'extension/expai/modelstats' )}</a>{/if}{if $exp_ai_queue} <a href={concat( 'expqueue/job/', $m.test.uuid )|ezurl}>{'job'|i18n( 'extension/expai/modelstats' )}</a>{/if}</span>
{/if}
{if $m.probe}
                    <span class="exp-ai-ms-small{if lt( $m.probe.pass_rate, 80 )} exp-ai-ms-bad{/if}">{'Probe %date: %rate % (%passed of %tasks)'|i18n( 'extension/expai/modelstats',, hash( '%date', $m.probe.created|l10n( 'shortdate' ), '%rate', $m.probe.pass_rate, '%passed', $m.probe.passed, '%tasks', $m.probe.tasks ) )|wash}</span>
{/if}
{if and( $m.test|not, $m.probe|not )}<span class="exp-ai-muted">&ndash;</span>{/if}
                </td>
            </tr>
{/foreach}
        </tbody>
    </table>
    </div>
    <p class="exp-ai-card-foot exp-ai-hint">{'Latency and speed count successful requests. Slow: the median request takes longer than the %seconds seconds a web request may spend on one model call.'|i18n( 'extension/expai/modelstats',, hash( '%seconds', $stats.slow_seconds ) )}</p>
{else}
    <p class="exp-ai-hint">{'No requests in this period.'|i18n( 'extension/expai/modelstats' )}</p>
{/if}
</section>
{undef $exp_ai_queue $exp_ai_kinds}
