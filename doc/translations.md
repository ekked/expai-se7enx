# Translations

expai ships `translations/eng-US`, `translations/ger-DE` and `translations/untranslated` (every message, untranslated,
as the start for a new language). site.ini `[RegionalSettings] TranslationExtensions[]=expai` makes them active.

| Context | Where |
| --- | --- |
| `extension/expai/tab` | the tab label "Intelligence" |
| `extension/expai/panel` | the panel |
| `extension/expai/step` | the step lines of the panel (from the tools' `stepText()`) |
| `extension/expai/label` | the label and the note in version views and approval items |
| `extension/expai/edit` | the edit form area and buttons |
| `extension/expai/fieldaction` | the field action labels (`[FieldAction_<name>] Label`) |
| `extension/expai/dashboard` | the dashboard page, the block, the browser description |
| `extension/expai/log` | the log page |
| `extension/expai/error` | messages of `expAiException` shown to the editor |
| `design/admin/parts/my/menu` | the left menu entry "Exponential Intelligence" |

Not translated, in every language: "Exponential Intelligence", "Claude", "Anthropic". German: "Powered by Claude from
Anthropic" is "Bereitgestellt von Claude von Anthropic"; the tab is "Intelligenz".

Texts the browser shows are rendered into the page by the templates (JSON blocks) or come translated from the server
(error messages, step lines, the label texts of `expai::labels`), so expai.js has no texts of its own.

## A new language

1. Copy `translations/untranslated/translation.ts` to `translations/<locale>/translation.ts` (e.g. `fre-FR`), set
   `language="fr_FR"` and fill every `<translation>`.
2. Clear the translation cache: `php bin/php/ezcache.php --clear-id=translation`.
3. Open the full view, the edit form, the dashboard and the log in a siteaccess with that locale; the admin's debug
   output lists any "Missing translation" for them.

The model answers in the language the editor writes in, whatever the interface language.
