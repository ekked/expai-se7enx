{* Editor presets and the Improve menu (doc/presets.md), included by content/edit/expai.tpl: the defaults line (model,
   region, lane, estimated cost of one click) and the configuration expai_presets.js draws the buttons from. Values
   are only put into the form: nothing is stored until the draft is stored or published as usual. *}
{def $exp_ai_presets = fetch( 'expai', 'edit_presets', hash( 'object', $object, 'attributes', $content_attributes ) )}
{if $exp_ai_presets}
{if $exp_ai_presets.defaults}
{def $exp_ai_region = $exp_ai_presets.defaults.region}
    <p class="exp-ai-defaults" id="exp-ai-defaults">
        <span class="exp-ai-defaults-title">{'Defaults in use'|i18n( 'extension/expai/preset' )}:</span>
        <span>{$exp_ai_presets.defaults.model|wash}{if $exp_ai_presets.defaults.provider} ({$exp_ai_presets.defaults.provider|wash}){/if}</span>
        <span aria-hidden="true">&middot;</span>
        <span>{switch match=$exp_ai_region}{case match='us'}{'region US'|i18n( 'extension/expai/preset' )}{/case}{case match='eu'}{'region EU'|i18n( 'extension/expai/preset' )}{/case}{case match='local'}{'on this server'|i18n( 'extension/expai/preset' )}{/case}{case}{'region unknown'|i18n( 'extension/expai/preset' )}{/case}{/switch}</span>
        <span aria-hidden="true">&middot;</span>
        <span>{'standard lane'|i18n( 'extension/expai/preset' )}</span>
        <span aria-hidden="true">&middot;</span>
        <span>{if $exp_ai_presets.defaults.free}{'no cost per click'|i18n( 'extension/expai/preset' )}{elseif $exp_ai_presets.defaults.cost_known}{'about %cost per click'|i18n( 'extension/expai/preset',, hash( '%cost', $exp_ai_presets.defaults.cost ) )|wash}{else}{'cost per click unknown'|i18n( 'extension/expai/preset' )}{/if}</span>
        <span aria-hidden="true">&middot;</span>
        <span>{'nothing is stored until you store the draft'|i18n( 'extension/expai/preset' )}</span>
    </p>
{undef $exp_ai_region}
{/if}
    <script type="application/json" class="exp-ai-preset-config">{hash(
        'presets', $exp_ai_presets.presets,
        'improve', $exp_ai_presets.improve,
        'improve_datatypes', $exp_ai_presets.improve_datatypes,
        'strings', hash(
            'presets', 'Presets'|i18n( 'extension/expai/preset' ),
            'improve', 'Improve'|i18n( 'extension/expai/preset' ),
            'improve_title', 'Improve this field'|i18n( 'extension/expai/preset' ),
            'command_preset', '%action: %field'|i18n( 'extension/expai/preset' ),
            'command_improve', 'Improve: %field'|i18n( 'extension/expai/preset' ),
            'working', 'Exponential Intelligence is working...'|i18n( 'extension/expai/edit' ),
            'filled', 'Filled in by Exponential Intelligence. Review it before you store the draft.'|i18n( 'extension/expai/edit' ),
            'undo', 'Undo'|i18n( 'extension/expai/edit' ),
            'undone', 'Restored the previous value.'|i18n( 'extension/expai/edit' ),
            'error', 'Something went wrong:'|i18n( 'extension/expai/edit' ),
            'timeout', 'The server did not answer in time. Please try again.'|i18n( 'extension/expai/edit' ),
            'empty', 'The field is empty: there is nothing to improve.'|i18n( 'extension/expai/preset' ),
            'no_text', 'The model returned no text, so nothing was written.'|i18n( 'extension/expai/preset' )
        )
    )|expai_json}</script>
    <script src={'javascript/expai_presets.js'|ezdesign}></script>
{/if}
{undef $exp_ai_presets}
