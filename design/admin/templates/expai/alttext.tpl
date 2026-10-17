{* expai/alttext: the "Missing alt text" report (doc/alt-text.md). Variables: node, rows (expAiAltText::missing()),
   checked, truncated, max, error, notice, queued, model (the vision model or why there is none), estimate, queue,
   site_cap, max_queue. *}
<div class="exp-ai exp-ai-alttext-page">
    <header class="exp-ai-head">
        <span class="exp-ai-mark" aria-hidden="true"></span>
        <div>
            <h1 class="context-title">{'Missing alt text'|i18n( 'extension/expai/alttext' )}</h1>
            <p class="exp-ai-sub">{'Exponential Intelligence'|i18n( 'extension/expai/alttext' )}</p>
        </div>
        {include uri='design:expai/parts/help_link.tpl' page='alt-text' label='alt text'|i18n( 'extension/expai/help' ) text='?'}
    </header>
{if $error}<p class="exp-ai-error" role="alert">{$error|wash}</p>{/if}
{if $notice}<p class="exp-ai-notice" role="status">{$notice|wash}{if and( $queued, $queued.group, $queued.queued, fetch( 'user', 'has_access_to', hash( 'module', 'expqueue', 'function', 'view' ) ) )}
    <a href={concat( 'expqueue/group/', $queued.group )|ezurl}>{'Watch the jobs'|i18n( 'extension/expai/alttext' )} <span aria-hidden="true">&rarr;</span></a>{/if}</p>{/if}
{if and( $queued, $queued.skipped|count )}
    <ul class="exp-ai-compact-list">
{foreach $queued.skipped as $exp_ai_id => $exp_ai_why}
        <li>{'Object %id skipped: %why'|i18n( 'extension/expai/alttext',, hash( '%id', $exp_ai_id, '%why', $exp_ai_why ) )|wash}</li>
{/foreach}
    </ul>
{/if}
{if and( $queued, $queued.messages|count )}
    <p class="exp-ai-muted">{'Held because:'|i18n( 'extension/expai/alttext' )} {$queued.messages|implode( '; ' )|wash}</p>
{/if}

    <section class="exp-ai-card" aria-labelledby="exp-ai-alttext-where">
        <h2 id="exp-ai-alttext-where">{'Where'|i18n( 'extension/expai/alttext' )}</h2>
        <form method="get" action={'expai/alttext'|ezurl} class="exp-ai-inline-form" data-exp-ai-node-form="1">
            <label class="exp-ai-label">{'Node ID of the subtree'|i18n( 'extension/expai/alttext' )}
                <input type="number" min="1" class="box" value="{if $node}{$node.node_id}{/if}" /></label>
            <input type="submit" class="button exp-ai-btn" value="{'Show'|i18n( 'extension/expai/alttext' )}" />
        </form>
{if $node}
        <p>{'Images without alt text under %name (the node included): %count in %checked objects looked at.'|i18n( 'extension/expai/alttext',, hash( '%name', $node.name, '%count', $rows|count, '%checked', $checked ) )|wash}
        {if $truncated} <strong>{'Only the first %max objects were looked at: choose a smaller subtree.'|i18n( 'extension/expai/alttext',, hash( '%max', $max ) )|wash}</strong>{/if}</p>
{/if}
    </section>

{if $rows|count}
    <section class="exp-ai-card" aria-labelledby="exp-ai-alttext-list">
        <h2 id="exp-ai-alttext-list">{'Images'|i18n( 'extension/expai/alttext' )}</h2>
{if $model}
        <p class="exp-ai-muted">{if $model.available}{'Suggestions are written by %model, a model that can look at images. The data policy applies to the images: an image is only sent to a provider its section and class allow.'|i18n( 'extension/expai/alttext',, hash( '%model', $model.label ) )|wash}{if $estimate} {if $estimate.known}{'Estimated cost of all suggestions: %cost.'|i18n( 'extension/expai/alttext',, hash( '%cost', $estimate.money ) )|wash}{else}{'The cost of this model is unknown.'|i18n( 'extension/expai/alttext' )}{/if}{/if}{else}{'Not available:'|i18n( 'extension/expai/alttext' )} {$model.reason|wash}{/if}</p>
{/if}
        <form method="post" action={concat( 'expai/alttext/(node)/', $node.node_id )|ezurl}>
        <table class="list exp-ai-draft-table" cellspacing="0">
            <thead><tr><th class="tight"><input type="checkbox" class="exp-ai-alttext-all" data-exp-ai-check-all="ExpAiAltTextRows[]" title="{'Choose all'|i18n( 'extension/expai/alttext' )}" aria-label="{'Choose all'|i18n( 'extension/expai/alttext' )}" /></th>
                <th>{'Content'|i18n( 'extension/expai/alttext' )}</th><th>{'Class'|i18n( 'extension/expai/alttext' )}</th><th>{'Field'|i18n( 'extension/expai/alttext' )}</th>
                <th>{'Language'|i18n( 'extension/expai/alttext' )}</th><th>{'Size'|i18n( 'extension/expai/alttext' )}</th><th></th></tr></thead>
            <tbody>
{foreach $rows as $r}
                <tr><td><input type="checkbox" name="ExpAiAltTextRows[]" value="{$r.object_id}:{$r.attribute_id}:{$r.language|wash}" aria-label="{$r.name|wash}" /></td>
                    <td><a href={$r.url_alias|ezurl}>{$r.name|wash}</a></td><td>{$r.class|wash}</td><td>{$r.field|wash}</td><td>{$r.language|wash}</td>
                    <td>{$r.width} &times; {$r.height}</td>
                    <td><a href={concat( 'content/edit/', $r.object_id, '/f/', $r.language )|ezurl}>{'Edit'|i18n( 'extension/expai/alttext' )}</a></td></tr>
{/foreach}
            </tbody>
        </table>
{if and( $queue, $model, $model.available )}
        <p>
            <label class="exp-ai-label">{'Budget of this job group (optional, %currency)'|i18n( 'extension/expai/alttext',, hash( '%currency', ezini( 'Pricing', 'Currency', 'expai.ini' ) ) )|wash}
                <input type="text" class="box" name="ExpAiAltTextBudget" size="8" inputmode="decimal" /></label>
            <input type="submit" class="defaultbutton exp-ai-btn" name="ExpAiAltTextQueueButton" value="{'Queue suggestions as drafts'|i18n( 'extension/expai/alttext' )}" />
        </p>
        <p class="exp-ai-hint">{'Each chosen image gets a job: a vision model writes the alt text into a new draft of the object (never published). Review the drafts and publish them as usual. At most %max per click; jobs are held when the site budget%cap or the group budget is reached.'|i18n( 'extension/expai/alttext',, hash( '%max', $max_queue, '%cap', cond( $site_cap, concat( ' (', $site_cap, ')' ), '' ) ) )|wash}</p>
{elseif $queue|not}
        <p class="exp-ai-muted">{'The job queue (extension expqueue) is not active: open each image in the edit form and use "Write alt text" there.'|i18n( 'extension/expai/alttext' )}</p>
{/if}
        </form>
    </section>
{elseif $node}
    <p class="exp-ai-notice">{'Every image here has an alt text.'|i18n( 'extension/expai/alttext' )}</p>
{/if}
</div>
{* the "Where" form and "Choose all" (data-exp-ai-node-form, data-exp-ai-check-all): expai_page_actions.js, no inline code *}
{ezscript( array( 'expai_page_actions.js' ) )}
