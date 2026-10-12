{* expai/help: one page of the extension's documentation (classes/expaihelp.php). Variables: page (name, file, title,
   html: rendered and escaped by expAiHelp, never markup of the document), pages (name => file, title). *}
<div class="exp-ai exp-ai-help-page" id="exp-ai-help">
    <header class="exp-ai-head">
        <span class="exp-ai-mark" aria-hidden="true"></span>
        <div>
            <h1 class="context-title">{'Exponential Intelligence help'|i18n( 'extension/expai/help' )}</h1>
            <p class="exp-ai-sub">{'The documentation of this installation\'s version, file %file'|i18n( 'extension/expai/help',, hash( '%file', $page.file ) )|wash}</p>
        </div>
    </header>
    <div class="exp-ai-help-layout">
        <nav class="exp-ai-help-nav" aria-label="{'Help pages'|i18n( 'extension/expai/help' )}">
            <p class="exp-ai-help-nav-title">{'Help pages'|i18n( 'extension/expai/help' )}</p>
            <ul>
{foreach $pages as $exp_ai_name => $exp_ai_page}
                <li><a href={concat( 'expai/help/(page)/', $exp_ai_name )|ezurl}{if eq( $exp_ai_name, $page.name )} aria-current="page"{/if}>{$exp_ai_page.title|wash}</a></li>
{/foreach}
            </ul>
        </nav>
        <article class="exp-ai-help-doc" lang="en">
{$page.html}
        </article>
    </div>
</div>
