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
| `expai/panel/threads.tpl`, `expai/panel/chains.tpl`, `expai/panel/translations.tpl` | the panel's threads, chains and translations sections |
| `expai/edit/presets.tpl`, `expai/edit/alttext.tpl` | the presets, the Improve menu, the defaults line; the "Write alt text" button |
| `expai/vector.tpl`, `expai/vector/pane_*.tpl` | Vector and its right panes (plan, changes, cost) |
| `expai/chain.tpl`, `expai/chain_run.tpl`, `expai/chains/*.tpl` | AI chains: the runs, one run, the prompt of each step |
| `expai/alttext.tpl` | the "Missing alt text" report |
| `expai/models.tpl`, `expai/budgets.tpl`, `expai/summary.tpl` | models and providers, budgets and lanes, the "EI: Summary" page |
| `expai/help.tpl`, `expai/parts/help_link.tpl` | the help pages, the "?" link of each page |
| `expai/parts/*.tpl` | shared parts: the model picker, bars, trends, links, budget summary, thread cards |

Keep the classes and ids expai.js uses (`#exp-ai-panel`, `.exp-ai-log`, `.exp-ai-input`, `.exp-ai-send`, ... in the
shipped templates). To add, not replace, use sections (extending.md).

## Colors and look

expai.css scopes everything to `.exp-ai` and `.exp-ai-badge` and takes its colors from tokens, which follow the admin4
tokens where they exist (light and dark) and fall back otherwise:

| Token | Default | Used for |
| --- | --- | --- |
| `--exp-ai-accent` | `var(--a4-accent, var(--a4-orange, #c55a11))` | the mark, borders, highlights |
| `--exp-ai-accent-text` | `var(--a4-accent-text, #b4470c)` | accent-coloured text and links (readable on the surface in light and dark) |
| `--exp-ai-accent-ink` | `#fff` | text on the accent |
| `--exp-ai-accent-soft` | `var(--a4-accent-soft, var(--a4-orange-soft, rgba(197, 90, 17, 0.10)))` | the label, the banner |
| `--exp-ai-muted` | `var(--a4-muted, #5d6573)` | secondary text |
| `--exp-ai-line` | `var(--a4-line, #d9dde3)` | borders |
| `--exp-ai-surface` | `rgba(127, 127, 127, 0.06)` | cards, open history rows |
| `--exp-ai-user` | `rgba(127, 127, 127, 0.10)` | your messages |
| `--exp-ai-ok`, `--exp-ai-warn`, `--exp-ai-danger`, `--exp-ai-error` | `--a4-ok`, `--a4-warn`, `--a4-bad` of admin4, else `#2e7d32`, `#8a4b00`, `#b42318`, `#b3261e` | published, caution, refusal, errors |
| `--exp-ai-z-overlay`, `--exp-ai-z-toast`, `--exp-ai-z-popover` | `10000`, `10001`, `--a4-z-popover` (fallback 50) | the palette and toasts sit over everything (debug bar, full-screen editors, drag masks), drop-downs over their page |
| `--exp-ai-radius` | `var(--a4-radius-s, 6px)` | corners |

**Dark mode** needs no script on admin4: the tokens have their dark values under `html[data-a4-theme="dark"] .exp-ai`
so a field bar added after the page
loaded is right from its first paint. `expai.js` only marks the exceptions by the surface behind an element:
`exp-ai-on-dark` (a design that is dark without admin4's theme attribute; the older admin designs have no dark mode and stay light) and `exp-ai-on-light` (admin4l keeps
its content boxes light in dark mode; until the script has run, admin4l shows the dark tokens on its light boxes for a moment). Forced colours (high contrast) and `prefers-reduced-motion` are handled in the
stylesheets: the mark and the bars get borders and system colours, the spinner stops and there are no transitions.

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

## Behavior per class or section

- Which classes it may create, and where, follows the editor's `content/create` policies (class, section, subtree,
  parent class, language): restrict an editor role, and expai follows.
- Which fields it writes: `[ToolSettings] WritableDatatypes[]` (all classes), and per class the class definition
  (the description of an attribute is passed to the model as a hint).
- Per siteaccess: every setting can be set in a siteaccess's expai.ini.append.php; the tab only in siteaccesses where
  expai is active.
- Where approval applies is the approval workflow's configuration (sections, groups, languages), not expai's.
