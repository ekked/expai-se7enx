{* The monthly site budget of queued requests (expAiBudget::status()): parameters status, heading (bool: h2 and the
   link to expai/budgets are left out on that page itself). Shown on expai/dashboard and expai/budgets. *}
{def $s = $status.site}
<section class="exp-ai-card exp-ai-budget-summary" aria-labelledby="exp-ai-budget-title">
    <h2 id="exp-ai-budget-title">{'Budget this month'|i18n( 'extension/expai/budget' )}</h2>
{if gt( $s.cap, 0 )}
    {include uri='design:expai/parts/bar.tpl' value=$s.spent|sum( $s.reserved ) max=$s.cap level=$s.level label='budget'
             text='%spent spent + %reserved reserved of %cap (%percent %)'|i18n( 'extension/expai/budget',, hash( '%spent', $s.spent_money, '%reserved', $s.reserved_money, '%cap', $s.cap_money, '%percent', $s.percent ) )}
    <p class="exp-ai-muted">{'%left left; resets %time.'|i18n( 'extension/expai/budget',, hash( '%left', $s.remaining_money, '%time', $s.resets|l10n( 'shortdatetime' ) ) )}</p>
{else}
    <p class="exp-ai-muted">{'No monthly site budget is set ([Budgets] SiteMonthly or [Limits] MaxCostPerSitePerMonth). Spent this month: %spent; reserved by queued requests: %reserved.'|i18n( 'extension/expai/budget',, hash( '%spent', $s.spent_money, '%reserved', $s.reserved_money ) )}</p>
{/if}
{if gt( $status.held, 0 )}
    <p class="exp-ai-error">{'%n queued jobs are held by a budget.'|i18n( 'extension/expai/budget',, hash( '%n', $status.held ) )}</p>
{/if}
{if first_set( $heading, false() )|not}
    <p><a href={'expai/budgets'|ezurl}>{'Budgets, lanes and batches'|i18n( 'extension/expai/budget' )}</a></p>
{/if}
</section>
{undef $s}
