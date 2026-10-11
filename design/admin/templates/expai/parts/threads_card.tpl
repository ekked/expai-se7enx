{* Dashboard card "Threads" (expai/dashboard): the user's latest threads, those that need them, and Vector.
   fetch( 'expai', 'my_threads' ) lists only the current user's own threads. *}
{def $exp_ai_threads = fetch( 'expai', 'my_threads', hash( 'limit', 6 ) )
     $exp_ai_has_vector = fetch( 'user', 'has_access_to', hash( 'module', 'expai', 'function', 'vector' ) )}
<section class="exp-ai-card exp-ai-threads-card" aria-labelledby="exp-ai-threads-title">
    <div class="exp-ai-card-head">
        <h2 id="exp-ai-threads-title">{'Threads'|i18n( 'extension/expai/threads' )}</h2>
{if $exp_ai_threads.needs}
        <span class="exp-ai-pill exp-ai-pill-warn">{'%count need you'|i18n( 'extension/expai/threads',, hash( '%count', $exp_ai_threads.needs ) )|wash}</span>
{/if}
{if $exp_ai_has_vector}
        <a class="exp-ai-card-link" href={'expai/vector'|ezurl}>{'Open Vector'|i18n( 'extension/expai/threads' )}</a>
{/if}
    </div>
{if $exp_ai_threads.rows}
    <ul class="exp-ai-thread-rows">
{foreach $exp_ai_threads.rows as $exp_ai_thread}
        <li class="exp-ai-thread-row exp-ai-thread-{$exp_ai_thread.state|wash}">
            <span class="exp-ai-thread-dot" aria-hidden="true"></span>
            {if $exp_ai_has_vector}<a href={concat( 'expai/vector/(thread)/', $exp_ai_thread.uuid )|ezurl}>{$exp_ai_thread.title|wash}</a>{elseif $exp_ai_thread.node_id}<a href={concat( 'content/view/full/', $exp_ai_thread.node_id )|ezurl}>{$exp_ai_thread.title|wash}</a>{else}{$exp_ai_thread.title|wash}{/if}
            <span class="exp-ai-thread-meta">{$exp_ai_thread.state_label|wash}{if $exp_ai_thread.cost} &middot; {$exp_ai_thread.cost|wash}{/if} &middot; {$exp_ai_thread.modified|l10n( 'shortdatetime' )}</span>
        </li>
{/foreach}
    </ul>
{else}
    <p class="exp-ai-hint">{'No threads yet. A conversation in the Intelligence tab of a page becomes a thread; Vector starts threads of its own.'|i18n( 'extension/expai/threads' )}</p>
{/if}
</section>
{undef $exp_ai_threads $exp_ai_has_vector}
