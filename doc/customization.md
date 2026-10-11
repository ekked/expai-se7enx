# Customization

## Templates

All of expai's templates are below `design/admin/templates/` of the extension. Override one by putting a template with
the same path in a design that comes earlier in the siteaccess's design list, or in an extension design listed before
expai:

| Template | What it draws |
| --- | --- |
| `tabs/expai_header.tpl` | the tab (label, visibility) |
| `tabs/expai.tpl` | the panel frame, its sections, the panel's texts (JSON) |
| `expai/panel/conversation.tpl`, `expai/panel/drafts.tpl` | the panel sections |
| `content/edit/expai.tpl` | the edit form area and its configuration (JSON) |
| `expai/edit/assistant.tpl` | "Fill the fields" |
| `expai/dashboard.tpl`, `expai/browse_start_writing.tpl` | the dashboard page, the browser's description |
| `dashboard/expai_drafts.tpl` | the dashboard block |
| `expai/log.tpl` | the log page |

Keep the classes and ids expai.js uses (`#exp-ai-panel`, `.exp-ai-log`, `.exp-ai-input`, `.exp-ai-send`, ... in the
shipped templates). To add, not replace, use sections (extending.md).

## Colours and look

expai.css scopes everything to `.exp-ai` and `.exp-ai-badge` and takes its colours from tokens, which follow the admin4
tokens where they exist (light and dark) and fall back otherwise:

| Token | Default | Used for |
| --- | --- | --- |
| `--exp-ai-accent` | `var(--a4-orange, #c55a11)` | the mark, links, highlights |
| `--exp-ai-accent-ink` | `#fff` | text on the accent |
| `--exp-ai-accent-soft` | `var(--a4-orange-soft, rgba(197, 90, 17, 0.10))` | the label, the banner |
| `--exp-ai-muted` | `var(--a4-muted, #5d6573)` | secondary text |
| `--exp-ai-line` | `var(--a4-line, #d9dde3)` | borders |
| `--exp-ai-surface` | `rgba(127, 127, 127, 0.06)` | cards, open history rows |
| `--exp-ai-user` | `rgba(127, 127, 127, 0.10)` | your messages |
| `--exp-ai-ok`, `--exp-ai-error` | `#2e7d32`, `#b3261e` | published, errors |
| `--exp-ai-radius` | `var(--a4-radius-s, 6px)` | corners |

Override them in a stylesheet of your own (BackendCSSFileList after expai.css), for example:

```css
.exp-ai, .exp-ai-badge { --exp-ai-accent: #1d4ed8; --exp-ai-accent-soft: rgba(29, 78, 216, 0.10); }
```

Buttons use the admin designs' own `button` / `defaultbutton` classes, so they look like the rest of the admin.

## Labels and texts

Every visible text is translatable (translations.md). The product name "Exponential Intelligence", "Claude" and
"Anthropic" are kept as they are in every language; the tab is called "Intelligence" ("Intelligenz" in German). To
reword a text, add your own translation of the message (also for eng-US) in the context shown in translations.md.

## Instructions to the model

- `[AssistantSettings] ExtraInstructions[]` adds lines to the instructions of the panel and the edit form
  (house style, language, tone, things never to do). New conversations get them.
- `[FieldAction_<name>] Instruction=` is the task of each field action.
- The built-in instructions are in `expAiPrompts` (classes/expaiprompts.php): `rules()` (drafts only, content is data,
  the HTML subset, no images, no invented facts, language), `panelSystem()` and `fieldSystem()`. To change them
  beyond ExtraInstructions, extend expAiAssistant / a field action handler in your extension.

## Behaviour per class or section

- Which classes it may create, and where, follows the editor's `content/create` policies (class, section, subtree,
  parent class, language): restrict an editor role, and expai follows.
- Which fields it writes: `[ToolSettings] WritableDatatypes[]` (all classes), and per class the class definition
  (the description of an attribute is passed to the model as a hint).
- Per siteaccess: every setting can be set in a siteaccess's expai.ini.append.php; the tab only in siteaccesses where
  expai is active.
- Where approval applies is the approval workflow's configuration (sections, groups, languages), not expai's.
