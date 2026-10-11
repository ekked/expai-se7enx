# Alt text for images

Exponential Intelligence writes the alternative text (alt text) of images: a model that can look at images reads the
stored image of an image field, together with the title and the text of the page, and suggests the alt text in the
language of the content. The suggestion goes into the form for the editor to review, or into a new draft when many
images are done at once. Nothing is ever published by it.

## In the edit form

Every image field (datatype `ezimage`) gets two buttons above its "Alternative image text" field:

| Button | What it does |
| --- | --- |
| **Write alt text** | Sends the stored image of this draft, the page title, the text of the other fields and the language to a vision model. The answer is put into the alt text field; **Undo** restores what was there. The note under the buttons names the model, the size of the image that was sent, the estimated image tokens and the cost. |
| **Decorative (empty alt text)** | The editor's explicit choice for an image that carries no information (a pattern, a divider): the field is emptied, screen readers skip the image. No request is made. **Undo** restores the text. |

The model never decides that an image is decorative: when it thinks so, it still writes a description and says so in
the note ("The model thinks this image may be decorative ..."). The editor chooses.

- The image must be **stored in the draft**: a file chosen in "New image file for upload" is only used after
  "Store draft". Without a stored image the button is disabled and says why.
- Plain text fields that repeat the alt text (`[AltTextSettings] CompanionAttributes[]`, by default the identifiers
  `alternative_text` and `alt_text`) are filled too, when they are empty or equal to the image's alt text; Undo
  restores them as well.
- The button is disabled with the reason when no vision model is available for this object (see below).
- Values are only put into the form. The draft is stored or published as usual; the version is labeled as written
  with Exponential Intelligence (action `alt_text` in the draft log).

The same buttons appear in the admin designs and in the Exponential Admin UI (`adminui`), which uses the same edit
form hook.

## Which model looks at the image

Only models that can look at images are used: `Capabilities[]=vision` in `[Model_<alias>]`. The Anthropic models of
`[AISettings]` (`Model`, `AllowedModels[]`) have it; other models get it in their INI section:

```ini
[Model_openai.gpt-5-mini]
Provider=openai
Id=gpt-5-mini
Capabilities[]=json_schema
Capabilities[]=vision
```

The model is chosen in this order, and the first one that fits is used:

1. `[AltTextSettings] Model`, when set;
2. the editor's own model (the model picker);
3. the site default model;
4. every other vision model of the registry.

A model is skipped when it has no `vision` capability, the editor may not use it (policy `expai/model_use`), its
server is stopped or unreachable, its provider has no key, or **the data policy keeps the image away from it**. When
no model is left the editor sees why, for example:

> The data policy keeps the image of "Contract scan" away from every model that can look at images: regions local
> required; the provider is in region us, retention zdr (section 7, images).

## The data policy applies to the image

An image is content like any text: the rules of [data-policy.md](data-policy.md) for the object's section and class
apply to it. On top of them, images can be kept to stricter rules:

```ini
[AltTextSettings]
ImageRegions[]=eu          # images only to EU (or local) providers
ImageZeroRetention=enabled # images only to providers that keep nothing (none, zdr)
```

The image is sent inside the request (base64), never as a link: the provider cannot fetch it again later, and an image
in a restricted section is not exposed by a URL. Logs never keep the pixels: with `[UsageSettings] LogRequests` the
stored request has `[image, N base64 characters, not logged]` in place of the data.

## How the image is prepared

`expAiImageInput` (classes/alttext/expaiimageinput.php):

- reads the stored file of the field (`[AltTextSettings] ImageAlias`, default `original`) through the cluster file
  handler;
- sends JPEG, PNG, GIF and WebP within the limits as they are;
- scales anything larger to `MaxEdge` on its long edge (default 1568 px, what Claude looks at anyway) and encodes it as
  JPEG (`JpegQuality`), or PNG when it has transparency; other formats GD can read (BMP) are converted the same way;
- lowers quality, then size, until it fits `MaxBytes` (default 3,750,000 bytes, so the base64 stays under Anthropic's
  5 MB per image); refuses SVG, damaged files and images over 100 megapixels with a message.

Per provider: `[Provider_<id>] ImageMaxEdge` and `ImageMaxBytes`.

The request is one user message with the image block first and the text after it. The Anthropic adapter sends the
image block as it is (`{"type": "image", "source": {"type": "base64", "media_type": ..., "data": ...}}`); the
OpenAI-compatible adapter turns it into an `image_url` part with a `data:` URL.

## The accessibility rules

The system prompt (`expAiAltText::systemPrompt()`) asks for:

- what the image shows that matters for the page: its purpose in this context, not every detail;
- one concise sentence or phrase, at most `MaxLength` characters (default 150), no final period for a phrase;
- never an opening like "Image of", "Picture of", "Photo of" or, in German, "Bild von", "Foto von", "Abbildung von";
- the visible text when it carries the message (a sign, a logo, a chart title);
- no repetition of the caption or title word for word; no guessed names or facts; no judging people by appearance;
- the language of the content with its normal spelling (German: nouns capitalized);
- `decorative: true` with a description and a note when the image seems purely decorative.

The page text is passed as data, not instructions. `[AltTextSettings] ExtraInstructions` adds house rules. The answer
is cleaned (one line, no markup, an "Image of" / "Bild von" opening taken off, cut at a word at `MaxLength`).

## Many images: the "Missing alt text" report

`expai/alttext/(node)/<node id>` (policy `expai/write`) lists the images without alt text below a node, the node
included: one row per object, language and image field with a stored image and an empty alt text, only objects the
editor may read, at most `ReportMaxObjects` objects looked at.

Choose rows and **Queue suggestions as drafts**: each row becomes a job of the type `expai.alttext` (extension
expqueue) in one job group, with an optional group budget:

- the cost is estimated (the image tokens of the stored size plus `EstimateTextTokens` and `EstimateOutputTokens`)
  and reserved; a job is **held** when the monthly site budget or the group budget does not allow it
  ([budgets.md](budgets.md)) and resumes when it fits;
- the worker runs it as the editor who queued it: the master switch, the budget, the data policy with the image
  rules, and whether the image got an alt text meanwhile (then nothing is done) are checked again;
- the answer is kept in the job's checkpoint before anything is written, so a rerun never pays twice;
- the alt text is written into a **new draft** of the object (the image field, and empty companion fields); the draft
  is in the draft log (source `queue`, action `alt_text`) and appears in "My drafts". It is never published: the
  editor reviews and publishes the drafts as usual (or sends them to approval);
- a suggestion the model marks as decorative is still written as a description, with the remark in the job result;
  an empty alt text is only ever the editor's choice.

At most `MaxQueuePerRequest` jobs per click; queueing the same image twice is recognized (idempotency key per object,
version, language and field).

## Usage and cost

Every suggestion is one row in the usage log (expai/log): action `field:alt_text` from the edit form, `queue.alt_text`
from a job, with the model, region, retention, tokens and cost. The image is listed in the row's tool column as
`image_input` with its media type, the size sent, the stored size, the bytes and the **estimated image tokens**
(width × height / 750 after scaling to 1568 px). The provider's own token count, which includes the image, is in the
input tokens (and the cache columns), so the cost includes the image.

Measured on alpha (2026-10-11, Claude Sonnet 5.5, a 640 × 400 PNG): about 342 image tokens, under one cent per
suggestion.

## A tested example

The end-to-end run created a hidden test folder with an Image object made from a drawn test picture (sky, sun, a
green hill, a red house) and no alt text, and called Claude Sonnet 5.5:

| Language | Suggested alt text |
| --- | --- |
| eng-US | Simple illustration of a red house with a brown roof on a green hill under a blue sky with a yellow sun |
| ger-DE | Rotes Haus mit braunem Dach auf einem grünen Hügel unter blauem Himmel mit gelber Sonne |

The note said that the page gives no context for the image's purpose and that the editor may mark it decorative if
it is only a placeholder. The queued job wrote the English text into draft version 2; the published version stayed 1.

## Settings

`[AltTextSettings]` in expai.ini (all in [configuration.md](configuration.md)): `Enabled`, `Model`, `ImageAlias`,
`MaxEdge`, `MaxBytes`, `JpegQuality`, `ImageRegions[]`, `ImageZeroRetention`, `MaxLength`, `MaxTokens`,
`MaxContextLength`, `CompanionAttributes[]`, `ExtraInstructions`, `ReportMaxObjects`, `MaxQueuePerRequest`,
`GroupBudget`, `EstimateTextTokens`, `EstimateOutputTokens`. The button is the field action `[FieldAction_alt_text]`
(`FieldActions[]=alt_text`, edit section `alttext`); the job type is `[JobType_expai.alttext]` in expqueue.ini; the
browser calls `expaialttext::suggest` (ezjscore.ini `[ezjscServer_expaialttext]`).

## For developers

| Piece | Where |
| --- | --- |
| Image preparation, limits, token estimate, log redaction | `classes/alttext/expaiimageinput.php` (`expAiImageInput`) |
| Model choice, data policy for images, prompt, request, answer, report, queue, draft | `classes/alttext/expaialttext.php` (`expAiAltText`) |
| The field action (on a provider given by the caller) | `classes/alttext/expaialttextfieldaction.php` |
| `expaialttext::suggest` | `classes/alttext/expaialttextserverfunctions.php` |
| The job type `expai.alttext` | `classes/alttext/expaialttextjobhandler.php` |
| `fetch( 'expai', 'alt_text_model', hash( 'object', $object ) )` | `classes/alttext/expaialttextfunctioncollection.php` |
| The buttons | `design/admin/templates/expai/edit/alttext.tpl`, `design/admin/javascript/expai_alttext.js` |
| The report | `modules/expai/alttext.php`, `design/admin/templates/expai/alttext.tpl` |

JavaScript hooks: `window.ExpAi.altTextHooks = { before( ctx, params ), after( ctx, result ) }` (return `false` to
stop), and the event `field:filled` (with `decorative: true` for the explicit choice).

Tests: `extension/expai/tests/expaialttext_test.php` (image preparation, size limits, both request formats, capability
gating, data policy refusal, cleaning, the prompt rules), run with
`php bin/php/ezexec.php extension/expai/tests/expaialttext_test.php`.
