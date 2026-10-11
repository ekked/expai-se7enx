{* The model choice (preference expai_model): a select of [AISettings] AllowedModels[], the default marked. expai.js
   saves a change through expai::model. Parameters: compact (bool), reset (bool: show "Reset to default"), cached (bool:
   the page is view-cached, so the default is selected here and expai.js selects the user's choice from expai::load). *}
{def $exp_ai_models = fetch( 'expai', 'models' )}
{if $exp_ai_models.choice}
<span class="exp-ai-model{if first_set( $compact, false() )} exp-ai-model-compact{/if}">
    <label>{'Model'|i18n( 'extension/expai/model' )}
        <select class="exp-ai-model-select" data-default="{$exp_ai_models.default|wash}">
{foreach $exp_ai_models.allowed as $exp_ai_id => $exp_ai_label}
            <option value="{$exp_ai_id|wash}"{if eq( $exp_ai_id, cond( first_set( $cached, false() ), $exp_ai_models.default, $exp_ai_models.current ) )} selected="selected"{/if}>{$exp_ai_label|wash}{if eq( $exp_ai_id, $exp_ai_models.default )} {'(default)'|i18n( 'extension/expai/model' )}{/if}</option>
{/foreach}
        </select>
    </label>
{if first_set( $reset, false() )}
    <button type="button" class="exp-ai-link exp-ai-model-reset"{if eq( $exp_ai_models.current, $exp_ai_models.default )} hidden="hidden"{/if}>{'Reset to default'|i18n( 'extension/expai/model' )}</button>
{/if}
    <span class="exp-ai-model-note" aria-live="polite">{if and( $exp_ai_models.refused, first_set( $cached, false() )|not )}{'The model you chose is no longer available; the default is used.'|i18n( 'extension/expai/model' )}{/if}</span>
</span>
{else}
<span class="exp-ai-model">{$exp_ai_models.allowed[$exp_ai_models.current]|wash}</span>
{/if}
{undef $exp_ai_models}
