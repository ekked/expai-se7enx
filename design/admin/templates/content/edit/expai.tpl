{* The Exponential Intelligence controls of the content edit form, added through content.ini [EditSettings]
   AdditionalTemplates[] (the hook ezautosave uses). The sections come from expai.ini [EditViewSettings] Sections[];
   expai.js adds the field actions next to the fields. Values are only put into the form: nothing is stored until
   the draft is stored or published as usual. *}
{if and( fetch( 'expai', 'switched_on' ),
         array( 'true', 'enabled' )|contains( ezini( 'EditViewSettings', 'Enabled', 'expai.ini' )|downcase ),
         fetch( 'user', 'has_access_to', hash( 'module', 'expai', 'function', 'write' ) ) )}
{def $exp_ai_attributes = array()
     $exp_ai_actions = array()
     $exp_ai_languages = array()}
{foreach $content_attributes as $exp_ai_attribute}
    {set $exp_ai_attributes = $exp_ai_attributes|append( hash( 'id', $exp_ai_attribute.id,
                                                              'identifier', $exp_ai_attribute.contentclass_attribute_identifier,
                                                              'datatype', $exp_ai_attribute.data_type_string,
                                                              'name', $exp_ai_attribute.contentclass_attribute_name ) )}
{/foreach}
{foreach ezini( 'EditViewSettings', 'FieldActions', 'expai.ini' ) as $exp_ai_action}
    {set $exp_ai_actions = $exp_ai_actions|append( hash( 'name', $exp_ai_action,
                                                        'label', ezini( concat( 'FieldAction_', $exp_ai_action ), 'Label', 'expai.ini' )|i18n( 'extension/expai/fieldaction' ),
                                                        'input', ezini( concat( 'FieldAction_', $exp_ai_action ), 'Input', 'expai.ini' ),
                                                        'datatypes', ezini( concat( 'FieldAction_', $exp_ai_action ), 'Datatypes', 'expai.ini' ) ) )}
{/foreach}
{foreach fetch( 'content', 'prioritized_languages' ) as $exp_ai_language}
    {set $exp_ai_languages = $exp_ai_languages|append( hash( 'code', $exp_ai_language.locale, 'name', $exp_ai_language.name ) )}
{/foreach}
<section class="exp-ai exp-ai-edit" id="exp-ai-edit" aria-labelledby="exp-ai-edit-title">
    <header class="exp-ai-head">
        <span class="exp-ai-mark" aria-hidden="true"></span>
        <div>
            <h2 id="exp-ai-edit-title">{'Exponential Intelligence'|i18n( 'extension/expai/edit' )}</h2>
            <p class="exp-ai-sub">{'Powered by Claude from Anthropic'|i18n( 'extension/expai/edit' )}</p>
        </div>
        <input type="button" class="button exp-ai-btn exp-ai-toggle" aria-expanded="false" aria-controls="exp-ai-edit-body" value="{'Show'|i18n( 'extension/expai/edit' )}" />
    </header>
    {include uri='design:expai/edit/presets.tpl' object=$object content_attributes=$content_attributes}
    <div class="exp-ai-edit-body" id="exp-ai-edit-body" hidden="hidden">
{foreach ezini( 'EditViewSettings', 'Sections', 'expai.ini' ) as $exp_ai_section}
        {include uri=concat( 'design:', ezini( concat( 'EditSection_', $exp_ai_section ), 'Template', 'expai.ini' ) ) section=$exp_ai_section}
{/foreach}
    </div>
    <script type="application/json" class="exp-ai-edit-config">{json_encode( hash(
        'object_id', $object.id,
        'version', $edit_version,
        'language', $edit_language,
        'attributes', $exp_ai_attributes,
        'actions', $exp_ai_actions,
        'writable', ezini( 'ToolSettings', 'WritableDatatypes', 'expai.ini' ),
        'languages', $exp_ai_languages,
        'strings', hash(
            'show', 'Show'|i18n( 'extension/expai/edit' ),
            'hide', 'Hide'|i18n( 'extension/expai/edit' ),
            'actions', 'Exponential Intelligence'|i18n( 'extension/expai/edit' ),
            'actions_title', 'Exponential Intelligence (Powered by Claude from Anthropic)'|i18n( 'extension/expai/edit' ),
            'instruction', 'What should be written?'|i18n( 'extension/expai/edit' ),
            'optional_instruction', 'Optional: how should it change?'|i18n( 'extension/expai/edit' ),
            'language', 'Into which language?'|i18n( 'extension/expai/edit' ),
            'go', 'Go'|i18n( 'extension/expai/edit' ),
            'cancel', 'Cancel'|i18n( 'extension/expai/edit' ),
            'working', 'Exponential Intelligence is working...'|i18n( 'extension/expai/edit' ),
            'filled', 'Filled in by Exponential Intelligence. Review it before you store the draft.'|i18n( 'extension/expai/edit' ),
            'filled_count', '%count field(s) filled in by Exponential Intelligence. Review them before you store the draft.'|i18n( 'extension/expai/edit' ),
            'undo', 'Undo'|i18n( 'extension/expai/edit' ),
            'undone', 'Restored the previous value.'|i18n( 'extension/expai/edit' ),
            'error', 'Something went wrong:'|i18n( 'extension/expai/edit' ),
            'timeout', 'The server did not answer in time. Please try again.'|i18n( 'extension/expai/edit' ),
            'nothing', 'Exponential Intelligence suggested no values.'|i18n( 'extension/expai/edit' )
        )
    ) )}</script>
</section>
{undef $exp_ai_attributes $exp_ai_actions $exp_ai_languages}
{/if}
