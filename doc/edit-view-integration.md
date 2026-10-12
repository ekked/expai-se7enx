# The edit form (content/edit)

## The hook

The edit form templates of the admin designs (admin, admin3, admin4; admin4l and the editor design use these)
include every template of content.ini `[EditSettings] AdditionalTemplates[]` above the fields. ezautosave uses this
hook for its autosave; expai adds `content/edit/expai.tpl` the same way:

```ini
[EditSettings]
AdditionalTemplates[]=content/edit/expai.tpl
```

The template has the edit form's variables (`$object`, `$edit_version`, `$edit_language`, `$content_attributes`)
and draws, for users with `expai/write` while `[AISettings] Enabled` and `[EditViewSettings] Enabled` are on:

- the "Exponential Intelligence" area (closed until **Show**; the choice is remembered in localStorage
  `exp-ai-edit-open`), with the sections of `[EditViewSettings] Sections[]` (by default `assistant`: "Fill the
  fields");
- a JSON block (`.exp-ai-edit-config`) with the object id, version, language, the attributes (id, identifier,
  datatype, name), the field actions, the writable datatypes, the site's languages and the translated texts.

No edit form template is overridden; the per-field buttons are added by expai.js.

This differs from the older approach of ezgoogletranslate (archived), which overrode `content/edit.tpl`,
`content/edit_attribute.tpl` and datatype edit templates per design to place "translate" links: expai needs no copy
of any template, so it keeps working when a design's edit form changes.

## The field actions

For each attribute whose datatype is writable (`[ToolSettings] WritableDatatypes[]`) and offered by at least one
action (`[FieldAction_<name>] Datatypes[]`), expai.js finds the attribute's block (`.ezcca-edit-<identifier>` inside
the form, which every admin design renders) and puts a bar of buttons after its label:

```
* Exponential Intelligence  [Write] [Rewrite] [Shorten] [Translate]
```

A button with `Input=none` runs at once; the others open a small input (an instruction, or a language select) with Go
and Cancel. The request `expai::field` sends the object, version, language, attribute id, action, the field's current
value (as the form has it now), the editor's input and the other fields' current values (as context, at most about 30
KB). The server checks that the version is the editor's own draft and editable, that the attribute belongs to it and
that the action is offered for its datatype, runs the action's handler and returns `{value, note}`. expai.js puts the
value into the field, highlights it, shows the note and an **Undo** link.

The answer also carries `record` (`expAiEditForm::requestRecord()`: `usage_id`, `log` = `expai/log/(request)/<id>`,
and `job` = `expqueue/job/<uuid>` when the request ran as a job and the user has `expqueue/view`). The note shows it
as **View record** (and **View job**), opening in a new tab. A failure after the request was sent answers
`{error, record}` instead of an ezjscore error, so the link is there next to the error too; a failure before anything
was sent (no row) stays an ezjscore error. Presets and the Improve menu (`expaipreset::preset`) do the same.

The version is marked in the log (action `field` or `fill`), so it carries the label.

## Getting values in and out of the fields

expai.js reads and writes a field through an adapter per datatype (`ExpAi.fieldAdapters`):

| Datatype | Read | Write |
| --- | --- | --- |
| ezxmltext with Tiptap (exp_oe_tiptap) | `ExpOETiptap.get( textarea ).getHTML()` | `instance.editor.commands.setContent( html )`, then `instance.dirty = true; instance.sync()` (the textarea gets the HTML and an `input` event) |
| ezxmltext with the Online Editor (TinyMCE) | `tinyMCE.get( id ).getContent()` | `setContent( html )`, `save()`, `nodeChanged()`, `change` event |
| ezxmltext without an editor | the textarea | the textarea (the simplified XML input accepts the HTML subset) |
| every other datatype | the input or textarea in the block whose name ends with `_<attribute id>` | sets its value and fires `input` and `change` |

The `input`/`change` events are what ezautosave watches, so a filled field is autosaved like a typed one. Rich text
is the HTML subset `p, h2, h3, h4, strong, em, ul, ol, li, a href` (cleaned on the server).

## Presets and the Improve menu

Next to the field actions, `expai_presets.js` adds the presets of the object's class ("Suggest title", "Write summary",
"Meta description", "Keywords", also on the parts of an xrowmetadata field) and one "Improve" menu per text field, and
the line "Defaults in use" above the fields. They fill the form the same way, with Undo. See [presets.md](presets.md).

## "Fill the fields"

`expai::fill` sends the brief and the form's current values; the model gets the writable fields of the class (with
format hints, required flags, maximum lengths) and answers `{fields: [{identifier, value}], note}`; values are cleaned
per datatype and put into the form. **Undo** restores all of them.

## Designs

Tested in the admin4l, admin4 and admin3 designs and the editor siteaccess (the admin design has the same hook and
blocks, see design/admin/templates/content/edit.tpl and edit_attribute.tpl): the hook and the
`.ezcca-edit-<identifier>` blocks are the same in all of them. A design whose edit form does not include
`AdditionalTemplates[]` gets neither the area nor the buttons.
