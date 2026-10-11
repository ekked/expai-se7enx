{* The change against the period before: shown only when the period before has data. Parameters: change (percent or
   null), before (the value of the period before). *}
{if and( gt( $before, 0 ), is_null( $change )|not )}
<span class="exp-ai-trend {if gt( $change, 0 )}exp-ai-trend-up{elseif lt( $change, 0 )}exp-ai-trend-down{else}exp-ai-trend-flat{/if}" title="{'Compared with the period before'|i18n( 'extension/expai/usage' )}">{if gt( $change, 0 )}&#9650; {$change}&nbsp;%{elseif lt( $change, 0 )}&#9660; {$change|abs}&nbsp;%{else}&#61; 0&nbsp;%{/if}</span>
{/if}
