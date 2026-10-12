{* "Watch this run" of the dashboard's "Start writing" (expAiRunWatch::describe() as $watch): the state of the request
   sent from the Intelligence tab of the chosen location, and links to its live record (the thread in Vector, its
   requests in the log, its job). Rendered by expai/dashboard and by expaiwatch::state, which expai_watch.js asks
   every few seconds while the run waits or works. *}
{if $watch}
<p class="exp-ai-watch-line">
    <span class="exp-ai-watch-chip exp-ai-thread-{$watch.state|wash}"><span class="exp-ai-thread-dot" aria-hidden="true"></span>{switch match=$watch.state}
        {case match='unsent'}{'Not sent yet'|i18n( 'extension/expai/dashboard' )}{/case}
        {case match='queued'}{'Queued'|i18n( 'extension/expai/threads' )}{/case}
        {case match='running'}{'Working'|i18n( 'extension/expai/threads' )}{/case}
        {case match='waiting_input'}{'Waits for you'|i18n( 'extension/expai/threads' )}{/case}
        {case match='review'}{'To review'|i18n( 'extension/expai/threads' )}{/case}
        {case match='done'}{'Done'|i18n( 'extension/expai/threads' )}{/case}
        {case match='failed'}{'Failed'|i18n( 'extension/expai/threads' )}{/case}
        {case match='stopped'}{'Stopped'|i18n( 'extension/expai/threads' )}{/case}
        {case}{'Idle'|i18n( 'extension/expai/threads' )}{/case}{/switch}</span>
    <span class="exp-ai-watch-what">{if $watch.thread}{$watch.thread.title|wash}{else}{'Your request at %name'|i18n( 'extension/expai/dashboard',, hash( '%name', $watch.node_name ) )|wash}{/if}</span>
    <span class="exp-ai-watch-links">
{if $watch.links.vector}
        <a class="exp-ai-watch-main" href={$watch.links.vector|ezurl}>{'Watch this run'|i18n( 'extension/expai/dashboard' )} <span aria-hidden="true">&rarr;</span></a>
{else}
        <a class="exp-ai-watch-main" href={$watch.links.panel|ezurl}>{'Watch this run'|i18n( 'extension/expai/dashboard' )} <span aria-hidden="true">&rarr;</span></a>
{/if}
{if $watch.links.vector}        <a href={$watch.links.panel|ezurl}>{'Intelligence tab'|i18n( 'extension/expai/dashboard' )}</a>{/if}
{if $watch.links.log}        <a href={$watch.links.log|ezurl}>{'Log'|i18n( 'extension/expai/dashboard' )}</a>{/if}
{if $watch.links.job}        <a href={$watch.links.job|ezurl}>{'Job'|i18n( 'extension/expai/dashboard' )}</a>{/if}
    </span>
</p>
{/if}
