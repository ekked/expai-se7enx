{* The test prompt and "Load model" of one model on expai/models (expAiModelTest::forModel() as $t). $part=state: the
   line under the buttons (running job, loaded until); $part=result: the result row. Rendered by expai/models and by
   expaimodel::status, which expai_models.js asks every few seconds while a job waits or runs. *}
{if eq( $part, 'state' )}
{def $job = cond( and( $t.load, $t.load.active ), $t.load, and( $t.test, $t.test.active ), $t.test, false() )}
{if $job}
    <span class="exp-ai-working exp-ai-model-busy" role="status"><span class="exp-ai-spinner" aria-hidden="true"></span>
    {if eq( $job.action, 'load' )}{'Loading the model…'|i18n( 'extension/expai/modeltest' )}{elseif $t.managed}{'Testing… (loading the model can take a minute)'|i18n( 'extension/expai/modeltest' )}{else}{'Testing…'|i18n( 'extension/expai/modeltest' )}{/if}
    {if eq( $job.phase, 'waiting' )}{if $job.held}{'held: %reason'|i18n( 'extension/expai/modeltest',, hash( '%reason', $job.held ) )|wash}{else}{'waiting for a worker, %seconds s'|i18n( 'extension/expai/modeltest',, hash( '%seconds', $job.seconds ) )}{/if}{else}{'%seconds s'|i18n( 'extension/expai/modeltest',, hash( '%seconds', $job.seconds ) )}{/if}</span>
    {if fetch( 'user', 'has_access_to', hash( 'module', 'expqueue', 'function', 'view' ) )}<a class="exp-ai-test-links" href={concat( 'expqueue/job/', $job.uuid )|ezurl}>{'Job'|i18n( 'extension/expai/modeltest' )}</a>{/if}
{/if}
{if $t.loaded}
    <small class="exp-ai-model-loaded">{if $t.loaded.loaded}{if $t.loaded.until}{'loaded until %time'|i18n( 'extension/expai/modeltest',, hash( '%time', $t.loaded.until|l10n( 'shorttime' ) ) )}{else}{'loaded'|i18n( 'extension/expai/modeltest' )}{/if}{elseif $t.loaded.reachable}{'not loaded'|i18n( 'extension/expai/modeltest' )}{else}{'server not reachable'|i18n( 'extension/expai/modeltest' )}{/if}</small>
{/if}
{undef $job}
{else}
{foreach array( $t.test, $t.load ) as $d}
{if and( $d, $d.active|not )}
    <p class="exp-ai-test-line">
{if eq( $d.phase, 'failed' )}
        <span class="exp-ai-step-failed">{if eq( $d.action, 'load' )}{'Loading the model failed: %error'|i18n( 'extension/expai/modeltest',, hash( '%error', $d.error ) )|wash}{else}{'The test failed: %error'|i18n( 'extension/expai/modeltest',, hash( '%error', $d.error ) )|wash}{/if}</span>
        <small>({$d.finished|l10n( 'shorttime' )}, {'%seconds s'|i18n( 'extension/expai/modeltest',, hash( '%seconds', $d.seconds ) )})</small>
{elseif eq( $d.action, 'load' )}
        <strong>{'Model loaded at %time'|i18n( 'extension/expai/modeltest',, hash( '%time', $d.finished|l10n( 'shorttime' ) ) )}</strong>:
        {if $d.result.load_s}{'loading took %seconds s'|i18n( 'extension/expai/modeltest',, hash( '%seconds', $d.result.load_s ) )}{else}{'it was already in memory'|i18n( 'extension/expai/modeltest' )}{/if}{if $d.result.until} &middot; {'kept until %time'|i18n( 'extension/expai/modeltest',, hash( '%time', $d.result.until|l10n( 'shorttime' ) ) )}{/if}
{else}
        <strong>{'Test at %time'|i18n( 'extension/expai/modeltest',, hash( '%time', $d.finished|l10n( 'shorttime' ) ) )}</strong>:
        {'time to first token'|i18n( 'extension/expai/modeltest' )} {if $d.result.ttft_s|is_null|not}{$d.result.ttft_s|l10n( 'number' )} s{if $d.result.load_s} ({'model load %seconds s'|i18n( 'extension/expai/modeltest',, hash( '%seconds', $d.result.load_s|l10n( 'number' ) ) )}){/if}{else}<span title="{'The provider answers in one piece, so the first token is not measured.'|i18n( 'extension/expai/modeltest' )|wash}">{'not measured'|i18n( 'extension/expai/modeltest' )}</span>{/if}
        &middot; {'in: %count tokens'|i18n( 'extension/expai/modeltest',, hash( '%count', $d.result.input ) )}{if $d.result.in_tps|is_null|not}, {'%rate tokens/s'|i18n( 'extension/expai/modeltest',, hash( '%rate', $d.result.in_tps|l10n( 'number' ) ) )}{/if}
        &middot; {'out: %count tokens'|i18n( 'extension/expai/modeltest',, hash( '%count', $d.result.output ) )}{if $d.result.out_tps|is_null|not}, {'%rate tokens/s'|i18n( 'extension/expai/modeltest',, hash( '%rate', $d.result.out_tps|l10n( 'number' ) ) )}{/if}
        &middot; {'total %seconds s'|i18n( 'extension/expai/modeltest',, hash( '%seconds', $d.result.total_s|l10n( 'number' ) ) )}
        &middot; {'cost'|i18n( 'extension/expai/modeltest' )} {if $d.result.cost}{$d.result.cost|wash}{else}{'unknown'|i18n( 'extension/expai/modeltest' )}{/if}
        {if $d.result.model}&middot; <code>{$d.result.model|wash}</code>{/if}
        <br /><q class="exp-ai-test-answer">{$d.result.text|wash}</q>
{/if}
        {* the live record: the request in the usage log, the job of the job queue *}
        <span class="exp-ai-test-links">{if and( is_set( $d.result.usage_log_id ), $d.result.usage_log_id )}<a href={concat( 'expai/log/(request)/', $d.result.usage_log_id )|ezurl}>{'Request in the log'|i18n( 'extension/expai/modeltest' )}</a>{/if}
        {if fetch( 'user', 'has_access_to', hash( 'module', 'expqueue', 'function', 'view' ) )}<a href={concat( 'expqueue/job/', $d.uuid )|ezurl}>{'Job'|i18n( 'extension/expai/modeltest' )}</a>{/if}</span>
    </p>
{/if}
{/foreach}
{/if}
