{* The "?" link to a help page: straight to its page in the help center (exphelp/viewer/ext/expai/<page>.md, Exponential
   6.0.15 and later, for users with exphelp/read) or, without it, to expai/help/(page)/<page>, which redirects to GitHub
   (expai_help_url, expAiHelpLinks::link(): the page names are mapped there, never here). Parameters (always all three): page (the name of a
   doc/*.md without .md: dashboard, vector, full-view-panel, summary, alt-text, chains, packages, ...), label (the
   page's subject, translated), text (what the link shows: '?' or a word). *}
<a class="exp-ai-help-link{if ne( $text, '?' )} exp-ai-help-link-text{/if}" href={expai_help_url( $page )|ezurl} title="{'Help: %subject'|i18n( 'extension/expai/help',, hash( '%subject', $label ) )|wash}" aria-label="{'Help: %subject'|i18n( 'extension/expai/help',, hash( '%subject', $label ) )|wash}">{$text|wash}</a>
