{* The "?" link to a help page (expai/help, classes/expaihelp.php). Parameters (always all three): page (the page
   name: dashboard, vector, full-view-panel, ai-facts, alt-text, chains, packages, ...), label (the page's subject,
   translated), text (what the link shows: '?' or a word). *}
<a class="exp-ai-help-link{if ne( $text, '?' )} exp-ai-help-link-text{/if}" href={concat( 'expai/help/(page)/', $page )|ezurl} title="{'Help: %subject'|i18n( 'extension/expai/help',, hash( '%subject', $label ) )|wash}" aria-label="{'Help: %subject'|i18n( 'extension/expai/help',, hash( '%subject', $label ) )|wash}">{$text|wash}</a>
