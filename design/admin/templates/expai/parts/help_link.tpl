{* The "?" link to a help page: expai/help/(page)/<page>, which leads to the page of the extension's documentation in the
   help center (exphelp/viewer/ext/expai/<page>.md, Exponential 6.0.15 and later) or, without it, on GitHub
   (expAiHelpLinks). Parameters (always all three): page (the name of a
   doc/*.md without .md: dashboard, vector, full-view-panel, summary, alt-text, chains, packages, ...), label (the
   page's subject, translated), text (what the link shows: '?' or a word). *}
<a class="exp-ai-help-link{if ne( $text, '?' )} exp-ai-help-link-text{/if}" href={concat( 'expai/help/(page)/', $page )|ezurl} title="{'Help: %subject'|i18n( 'extension/expai/help',, hash( '%subject', $label ) )|wash}" aria-label="{'Help: %subject'|i18n( 'extension/expai/help',, hash( '%subject', $label ) )|wash}">{$text|wash}</a>
