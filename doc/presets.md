# Editor presets and the Improve menu

The edit form has two kinds of one-click help that need no prompt:

- **Presets**: "Suggest title", "Write summary", "Meta description", "Keywords". A preset button sits next to every
  field a class maps it to, including the title, description and keywords of an xrowmetadata field.
- **The Improve menu**: one menu on every text field with Shorten, Expand, Clarify, Fix grammar, Simplify for new
  readers, More formal and More casual.

Both work like the other field actions ([edit-view-integration.md](edit-view-integration.md)): the answer is put
into the field in the form, highlighted, with a note and **Undo**. Nothing is stored until the editor stores or
publishes the draft as usual (autosave may store the draft, as it does for typed text; it never publishes).
After the answer (or a failure after the request was sent) the note also links **View record**, the request's row
in the usage log (`expai/log/(request)/<id>`, in a new tab, so the unsaved form stays open).

```
Defaults in use: Claude Sonnet 5.5 (Anthropic) · region US · standard lane · about $0.01 per click · nothing is stored until you store the draft

Title *
* Exponential Intelligence [Write] [Rewrite] [Translate] [Suggest] [Suggest title] [Improve v]
```

## The defaults line

Above the fields, always visible (also while the Exponential Intelligence area is closed), one line names what a
click uses: the editor's model (the site default unless the editor chose another), its provider's region, the lane
(the edit form always uses the standard lane) and the estimated cost of one click. The estimate is
`[EditPresets] EstimateInputTokens` and `EstimateOutputTokens` at the model's prices ([Pricing], or the model's own
prices); a model without prices shows "cost per click unknown". `[EditPresets] DefaultsLine=disabled` hides the line.

## Presets per class

`expai.ini`:

```ini
[EditPresets]
Presets[]=title
Presets[]=summary
Presets[]=meta_description
Presets[]=keywords

[Presets]
# Class_<class identifier>[<preset>]=<target>[,<target>...]
Class_article[title]=title
Class_article[summary]=intro
Class_ng_article[title]=title,metadata.title
Class_ng_article[summary]=teaser_intro,full_intro
Class_ng_article[meta_description]=metadata.description
Class_ng_article[keywords]=metadata.keywords
```

A target is an attribute identifier, or `<attribute>.<part>` for a part of an xrowmetadata field (`title`,
`description`, `keywords`). The preset shows one button next to each target; a class without a line gets no
presets. A target is only offered when the field's datatype is writable (`[ToolSettings] WritableDatatypes[]`) or it
is an xrowmetadata part.

The shipped mapping:

| Class | Suggest title | Write summary | Meta description | Keywords |
| --- | --- | --- | --- | --- |
| article | title | intro | | |
| blog_post | title | | metadata.description | tags, metadata.keywords |
| folder | name | short_description | | tags |
| ng_article, ng_blog_post, ng_news | title, metadata.title | teaser_intro, full_intro | metadata.description | metadata.keywords |
| ng_landing_page, ng_category | title, metadata.title | teaser_intro, full_intro | metadata.description | metadata.keywords |

The ng_* classes keep their tags in eztags fields, which are not written; their keywords go to the metadata.
Alt text for images is a separate field action for image fields, not a preset.

Each preset is a field action section `[FieldAction_preset_<name>]` (Label, Handler, Datatypes[], Input=none,
Instruction), so its wording is changed like any field action ([extending.md](extending.md)). A part of an
xrowmetadata field is described to the model as one line of plain text with the length xrowmetadata.ini allows
(`[EditorInputSettings] MaxTitle`, `MaxDescription`), and the answer is cut to it.

## The Improve menu

`[EditPresets] Improve[]` lists the entries, each `[FieldAction_improve_<name>]`; `ImproveDatatypes[]` the datatypes
that get the menu (default ezstring, eztext, ezxmltext). An empty field is not sent ("The field is empty: there is
nothing to improve."). Rich text keeps its formatting (the HTML subset of the field actions).

The menu is a menu button: Enter, Space or Down opens it, Up and Down move, Home and End jump, Enter chooses, Esc
closes and gives the focus back to the button.

## The command palette

Every preset button and every Improve menu is registered with `ExpAi.commands.register()` ([keyboard.md](keyboard.md)):
in an edit form, Ctrl/Cmd+K (outside a field) lists "Suggest title: Title", "Meta description: Meta Data",
"Improve: Body text" and so on. Choosing a preset runs it; choosing an Improve entry opens that field's menu.

## The request

`expaipreset::preset` (ezjscore, function list `expai`) with `object_id`, `version`, `language`, `attribute_id`,
`kind` (`preset` or `improve`), `action`, `part`, `value` (the field as the form has it) and `context` (the other
fields). The checks are those of `expai::field`: POST, the form token, `[AISettings] Enabled`, the policy
`expai/write`, the editor's own editable draft, the data policy, the limits and the fallback to the default model;
plus: the preset is mapped to that field of the object's class, or the field's datatype has the Improve menu.
The version is marked in the log (action `field`, summary `preset:<name>` or `improve:<name>`).

## Switching it off

`[EditPresets] Enabled=false` removes the presets, the menu and the defaults line; `[EditViewSettings] Enabled=false`
or the master switch `[AISettings] Enabled=false` remove every control of the edit form.

## Files

| File | Part |
| --- | --- |
| `classes/fieldactions/expaieditpresets.php` | the mapping, the defaults line, running a preset or an Improve entry |
| `classes/fieldactions/expaipresetfieldrequest.php` | a field request that can address an xrowmetadata part |
| `classes/fieldactions/expaipresetserverfunctions.php` | `expaipreset::preset` |
| `classes/fieldactions/expaipresetfunctioncollection.php` | `fetch( 'expai', 'edit_presets', hash( 'object', $object, 'attributes', $content_attributes ) )` |
| `design/admin/templates/expai/edit/presets.tpl` | the defaults line and the configuration, included by `content/edit/expai.tpl` |
| `design/admin/javascript/expai_presets.js` | the buttons, the menu, Undo, the palette entries |
