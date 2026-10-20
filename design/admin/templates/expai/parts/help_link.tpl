{* The "?" link to a help page: the page of the extension's documentation in the help center (exphelp/viewer/ext/expai/
   <page>.md, doc/index.md "Help in the administration interface"). Parameters (always all three): page (the name of a
   doc/*.md without .md: dashboard, vector, full-view-panel, summary, alt-text, chains, packages, ...), label (the
   page's subject, translated), text (what the link shows: '?' or a word). *}
<a class="exp-ai-help-link{if ne( $text, '?' )} exp-ai-help-link-text{/if}" href={concat( 'exphelp/viewer/ext/expai/', $page, '.md' )|ezurl} title="{'Help: %subject'|i18n( 'extension/expai/help',, hash( '%subject', $label ) )|wash}" aria-label="{'Help: %subject'|i18n( 'extension/expai/help',, hash( '%subject', $label ) )|wash}">{$text|wash}</a>
