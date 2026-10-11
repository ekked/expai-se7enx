{* The model choice (preference expai_model, a model alias): the models the user may choose, grouped by provider, each
   with its region, tool mode, relative cost and "slow" for a local model; the default marked. expai.js
   saves a change through expai::model. Parameters: compact (bool), reset (bool: show "Reset to default"), cached (bool:
   the page is view-cached, so the default is selected here and expai.js selects the user's choice from expai::load).
   A stopped or unreachable model is shown with its state and cannot be chosen (on a cached page expai.js marks it). *}
{def $exp_ai_models = fetch( 'expai', 'models' )}
{if $exp_ai_models.choice}
<span class="exp-ai-model{if first_set( $compact, false() )} exp-ai-model-compact{/if}">
    <label>{'Model'|i18n( 'extension/expai/model' )}
        <select class="exp-ai-model-select" data-default="{$exp_ai_models.default|wash}">
{foreach $exp_ai_models.groups as $exp_ai_group}
{if gt( $exp_ai_models.groups|count, 1 )}            <optgroup label="{$exp_ai_group.label|wash}{if $exp_ai_group.region} ({$exp_ai_group.region|upcase|wash}){/if}">{/if}
{foreach $exp_ai_group.models as $exp_ai_m}
            <option value="{$exp_ai_m.alias|wash}"{if eq( $exp_ai_m.alias, cond( first_set( $cached, false() ), $exp_ai_models.default, $exp_ai_models.current ) )} selected="selected"{/if}{if and( first_set( $cached, false() )|not, $exp_ai_m.disabled )} disabled="disabled"{/if} data-label="{$exp_ai_m.label|wash}">{$exp_ai_m.label|wash}{if and( first_set( $cached, false() )|not, is_set( $exp_ai_models.states[$exp_ai_m.alias] ) )} &middot; {$exp_ai_models.states[$exp_ai_m.alias].text|wash}{/if}{if eq( $exp_ai_m.alias, $exp_ai_models.default )} {'(default)'|i18n( 'extension/expai/model' )}{/if}{if gt( $exp_ai_models.groups|count, 1 )} &middot; {$exp_ai_m.cost|wash}{if $exp_ai_m.slow} &middot; {'slow'|i18n( 'extension/expai/model' )}{/if}{if eq( $exp_ai_m.tool_use, 'prompted' )} &middot; {'prompted tools'|i18n( 'extension/expai/model' )}{elseif eq( $exp_ai_m.tool_use, 'none' )} &middot; {'no tools'|i18n( 'extension/expai/model' )}{/if}{if $exp_ai_m.writes|not} &middot; {'read only'|i18n( 'extension/expai/model' )}{/if}{/if}</option>
{/foreach}
{if gt( $exp_ai_models.groups|count, 1 )}            </optgroup>{/if}
{/foreach}
        </select>
    </label>
{if first_set( $reset, false() )}
    <button type="button" class="exp-ai-link exp-ai-model-reset"{if eq( $exp_ai_models.current, $exp_ai_models.default )} hidden="hidden"{/if}>{'Reset to default'|i18n( 'extension/expai/model' )}</button>
{/if}
    {* a stopped local model: the link to expai/models for users with expai/model_manage (expai.js shows it on a cached page) *}
    <a class="exp-ai-link exp-ai-model-manage" href={'expai/models'|ezurl}{if or( first_set( $cached, false() ), $exp_ai_models.manage|not, $exp_ai_models.states|count|not )} hidden="hidden"{/if}>{'Start it under Models'|i18n( 'extension/expai/model' )}</a>
    <span class="exp-ai-model-note" aria-live="polite">{if first_set( $cached, false() )|not}{if $exp_ai_models.refused}{'The model you chose is no longer available; the default is used.'|i18n( 'extension/expai/model' )}{else}{$exp_ai_models.slow|wash}{/if}{/if}</span>
</span>
{else}
<span class="exp-ai-model">{$exp_ai_models.allowed[$exp_ai_models.current]|wash}</span>
{/if}
{undef $exp_ai_models}
