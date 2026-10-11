{* A horizontal bar (role="progressbar"): parameters value, max (0: no limit, no bar), text (the label next to it),
   level (ok | warn | high | over), label (aria-label). *}
<div class="exp-ai-meter exp-ai-meter-{first_set( $level, 'ok' )|wash}">
{if gt( $max, 0 )}
    <div class="exp-ai-bar" role="progressbar" aria-valuemin="0" aria-valuemax="{$max|wash}" aria-valuenow="{$value|wash}" aria-label="{first_set( $label, '' )|wash}" aria-valuetext="{$text|wash}">
        <span style="width: {min( 100, $value|mul( 100 )|div( $max ) )|round}%"></span>
    </div>
{/if}
    <span class="exp-ai-meter-text">{$text|wash}</span>
</div>
