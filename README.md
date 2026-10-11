# expai: Exponential Intelligence (Powered by Claude from Anthropic)

A writing assistant for the Exponential 6 administration interface. Editors ask in plain language for new content
or for changes ("write three short articles about our spring events under this folder", "rewrite the intro of this
article, shorter"); Exponential Intelligence looks up what it needs (class definitions, the current content, the
content tree around it) and writes **drafts** - as the logged-in editor, with the editor's own permissions. Nothing
is ever published on its own: the editor keeps the drafts or sends them through the normal publishing, where an
approval workflow holds them for the approver.

Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
Licensed under the GNU General Public License v2.0 (or any later version), see [LICENSE](LICENSE).

## Features

- **Intelligence tab on the content full view**: a conversation panel per user and node. Claude answers, asks back
  when the request is unclear, shows each lookup and each draft it writes as a compact step, and lists the drafts it
  wrote with links to edit, view and discard them. The conversation history is kept per user and node, shown as
  one collapsible row per exchange (only the latest open), with "Expand all", "Collapse all" and "Show older".
- **Save as drafts** and **Send for approval**: "Send for approval" runs the regular publish operation as the editor.
  An approval workflow (`ezapprove` before `content/publish`) holds the version as pending and the approver decides
  in the collaboration views; where none applies, the version is published at once. Each draft says beforehand
  which of the two will happen.
- **Content edit form integration** (the hook ezautosave uses): an "Exponential Intelligence" area that fills the
  fields from a brief, and per-field actions (Write, Rewrite, Shorten, Translate, Suggest) next to text fields. The
  values go into the form for the editor to review; nothing is stored until the draft is stored or published as
  usual, so autosave, the Online Editor and Tiptap keep working.
- **The "Exponential Intelligence" label** on every version it wrote: in "My drafts", the drafts page, version views,
  edit forms and the approval (collaboration) views, with the model in the tooltip.
- **Dashboard page** (`expai/dashboard`, in the Dashboard tab's left menu): estimated cost, tokens, requests and
  drafts for a period with trends, a chart per day, limits and API rate limits, "Start writing" at a preselected
  location, recent drafts, AI-written versions waiting for your approval; for administrators the whole site and its
  top users. Plus a dashboard block "Exponential Intelligence: my drafts" and a log page (`expai/log`).
- **Usage tracking, costs and limits**: every request is recorded (tokens, time, tools, errors; user ids only), its
  cost estimated from a price table, and optional limits per conversation, session, user, day, month and site stop
  requests before they are sent ([doc/usage-tracking.md](doc/usage-tracking.md)).
- **Model choice**: editors pick from the models the administrator allows (Claude Sonnet 5.5, Opus 5.5, Haiku 5.5 by
  default).
- **Extension points**: tools Claude can call, field actions, panel and edit form sections, providers and JavaScript
  hooks, all registered through INI settings and small classes ([doc/extending.md](doc/extending.md)).
- Works on Apache/PHP-FPM and on Exponential Velocity; every database engine of the kernel (MySQL, PostgreSQL,
  SQLite, Oracle, MongoDB); translations eng-US and ger-DE.

## Quick start

1. Put the extension in `extension/expai` and activate it for the admin siteaccess(es), see [INSTALL.md](INSTALL.md).
2. Put the API key into `settings/override/expai.ini.append.php` (never commit that file):

   ```ini
   [AISettings]
   Enabled=true
   ApiKey=<your Anthropic API key>
   ```

3. Create the tables: `php extension/expai/bin/php/expaischema.php --install -s <admin siteaccess>`
4. Give editors the policy `expai/write`, regenerate the autoloads and clear the caches ([INSTALL.md](INSTALL.md)).
5. Open a folder in the admin, choose the **Intelligence** tab and ask for something.

How it is used day to day: [USAGE.md](USAGE.md). All documentation: [doc/index.md](doc/index.md).

## Screenshots

Taken at 960 px width and device scale 2 during the 0.1.0 tests (kept outside the repository, they show a real
installation): the empty panel, a conversation with its steps and the drafts it wrote, the history collapsed and
expanded, the edit form with the field actions and "Fill the fields", the label in "My drafts", in the drafts page,
in a version view and in an approval item, the dashboard page, and the panel in the admin, admin3 and admin4
designs and in German.

## Links

- Source: https://github.com/se7enxweb/expai
- Anthropic API documentation: https://docs.anthropic.com/
- Exponential: https://github.com/se7enxweb/exponential
