{* Edit view section "alttext" (doc/alt-text.md), included by content/edit/expai.tpl: the configuration expai_alttext.js
   draws the "Write alt text" button from, next to every image field of the form. The suggestion only goes into the
   alt text field (with undo); nothing is stored until the draft is stored or published as usual. *}
{def $exp_ai_alt_images = array()
     $exp_ai_alt_companions = array()
     $exp_ai_alt_texts = array()
     $exp_ai_alt_names = ezini( 'AltTextSettings', 'CompanionAttributes', 'expai.ini' )}
{foreach $content_attributes as $exp_ai_alt_attribute}
    {if eq( $exp_ai_alt_attribute.data_type_string, 'ezimage' )}
        {set $exp_ai_alt_images = $exp_ai_alt_images|append( hash( 'id', $exp_ai_alt_attribute.id,
                                                                  'identifier', $exp_ai_alt_attribute.contentclass_attribute_identifier,
                                                                  'name', $exp_ai_alt_attribute.contentclass_attribute_name,
                                                                  'has_image', $exp_ai_alt_attribute.content.original.is_valid|choose( false(), true() ),
                                                                  'alt_text_required', fetch( 'expai', 'alt_text_required', hash( 'attribute', $exp_ai_alt_attribute ) ) ) )}
    {elseif and( eq( $exp_ai_alt_attribute.data_type_string, 'ezstring' ), $exp_ai_alt_names|contains( $exp_ai_alt_attribute.contentclass_attribute_identifier ) )}
        {set $exp_ai_alt_companions = $exp_ai_alt_companions|append( hash( 'id', $exp_ai_alt_attribute.id, 'identifier', $exp_ai_alt_attribute.contentclass_attribute_identifier ) )}
    {elseif array( 'ezstring', 'eztext', 'ezxmltext', 'ezkeyword' )|contains( $exp_ai_alt_attribute.data_type_string )}
        {set $exp_ai_alt_texts = $exp_ai_alt_texts|append( hash( 'id', $exp_ai_alt_attribute.id,
                                                                'identifier', $exp_ai_alt_attribute.contentclass_attribute_identifier,
                                                                'datatype', $exp_ai_alt_attribute.data_type_string ) )}
    {/if}
{/foreach}
{if and( $exp_ai_alt_images|count, ezini( 'EditViewSettings', 'FieldActions', 'expai.ini' )|contains( 'alt_text' ) )}
{def $exp_ai_alt_model = fetch( 'expai', 'alt_text_model', hash( 'object', $object ) )}
    <script type="application/json" class="exp-ai-alttext-config">{json_encode( hash(
        'object_id', $object.id,
        'version', $edit_version,
        'language', $edit_language,
        'images', $exp_ai_alt_images,
        'companions', $exp_ai_alt_companions,
        'texts', $exp_ai_alt_texts,
        'model', $exp_ai_alt_model,
        'strings', hash(
            'actions', 'Exponential Intelligence'|i18n( 'extension/expai/edit' ),
            'actions_title', 'Exponential Intelligence (Powered by Claude from Anthropic)'|i18n( 'extension/expai/edit' ),
            'write', ezini( 'FieldAction_alt_text', 'Label', 'expai.ini' )|i18n( 'extension/expai/fieldaction' ),
            'write_title', 'A model that can look at images suggests the alt text: %model'|i18n( 'extension/expai/alttext',, hash( '%model', $exp_ai_alt_model.label ) ),
            'decorative', 'Decorative (empty alt text)'|i18n( 'extension/expai/alttext' ),
            'decorative_title', 'Only for images that carry no information: screen readers skip an image with an empty alt text.'|i18n( 'extension/expai/alttext' ),
            'decorative_required', 'This field requires an alternative text, so it cannot be left empty.'|i18n( 'extension/expai/alttext' ),
            'decorative_done', 'The alt text is empty now: screen readers skip this image. Review it before you store the draft.'|i18n( 'extension/expai/alttext' ),
            'decorative_hint', 'The model thinks this image may be decorative. If it carries no information, choose "Decorative (empty alt text)".'|i18n( 'extension/expai/alttext' ),
            'no_image', 'Upload the image and store the draft first: the alt text is written from the stored image.'|i18n( 'extension/expai/alttext' ),
            'unavailable', 'Not available:'|i18n( 'extension/expai/alttext' ),
            'facts', '%model looked at the image (%width x %height px, about %tokens image tokens), cost %cost.'|i18n( 'extension/expai/alttext' ),
            'facts_nocost', '%model looked at the image (%width x %height px, about %tokens image tokens).'|i18n( 'extension/expai/alttext' ),
            'working', 'Exponential Intelligence is working...'|i18n( 'extension/expai/edit' ),
            'filled', 'Filled in by Exponential Intelligence. Review it before you store the draft.'|i18n( 'extension/expai/edit' ),
            'undo', 'Undo'|i18n( 'extension/expai/edit' ),
            'undone', 'Restored the previous value.'|i18n( 'extension/expai/edit' ),
            'error', 'Something went wrong:'|i18n( 'extension/expai/edit' ),
            'timeout', 'The server did not answer in time. Please try again.'|i18n( 'extension/expai/edit' )
        )
    ) )}</script>
    <script src={'javascript/expai_alttext.js'|ezdesign}></script>
{undef $exp_ai_alt_model}
{/if}
{undef $exp_ai_alt_images $exp_ai_alt_companions $exp_ai_alt_texts $exp_ai_alt_names}
