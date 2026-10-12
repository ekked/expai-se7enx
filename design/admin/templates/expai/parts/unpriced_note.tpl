{* #389 the note beside a summed cost when some requests had no price (their model has no [Pricing] and the provider
   reported no cost): "15 requests without a price", with a link to those requests in expai/log ((unpriced)/1).
   Parameters: count (int), link (bool, default true), filter_path (view parameters of the log filters to keep, or ''),
   short (bool: "15 without a price", for table cells). The same words as expAiUsage::unpricedNote(). *}
{if and( is_set( $count ), gt( $count, 0 ) )}
{if is_set( $link )|not}{def $link = true()}{/if}
{if is_set( $filter_path )|not}{def $filter_path = ''}{/if}
{if is_set( $short )|not}{def $short = false()}{/if}
<span class="exp-ai-unpriced exp-ai-muted">{if $short}({'%count without a price'|i18n( 'extension/expai/usage',, hash( '%count', $count|expai_number ) )|wash}){elseif eq( $count, 1 )}{'1 request without a price'|i18n( 'extension/expai/usage' )}{else}{'%count requests without a price'|i18n( 'extension/expai/usage',, hash( '%count', $count|expai_number ) )|wash}{/if}{if and( $link, $filter_path|contains( '(unpriced)' )|not )} <a href={concat( 'expai/log', $filter_path, '/(unpriced)/1' )|ezurl}>{'Show them'|i18n( 'extension/expai/usage' )}</a>{/if}</span>
{/if}
