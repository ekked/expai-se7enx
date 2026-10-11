# expai documentation

| Document | What it covers |
| --- | --- |
| [../README.md](../README.md) | What expai is, features, quick start |
| [../INSTALL.md](../INSTALL.md) | Requirements, activation, API key, tables, policies, caches, Velocity, first check |
| [../USAGE.md](../USAGE.md) | For editors: the Intelligence tab, the edit form, the label, approval, the dashboard |
| [architecture.md](architecture.md) | How the parts fit: browser, ezjscore, the step loop, tools, provider, storage, long turns |
| [configuration.md](configuration.md) | Every setting of expai.ini and the other settings files, with default and effect |
| [providers.md](providers.md) | The provider registry, the anthropic and openai_chat adapters, models, the picker, tool modes, errors and retries |
| [provider-support.md](provider-support.md) | What is proven with which provider: live, against docs-derived mocks, or not yet |
| [data-policy.md](data-policy.md) | Regions and retention of providers, rules per section and class, where they are enforced |
| [probe.md](probe.md) | The tool-calling probe and the write gate |
| [local-models.md](local-models.md) | A local open-weight model: install, limits, the helper, expai/models |
| [mcp.md](mcp.md) | MCP tools: the in-process expmcp bridge and external servers |
| [lanes.md](lanes.md) | Lanes (standard, flex, batch, fast), the lane policy, queued requests, provider batches of five APIs |
| [budgets.md](budgets.md) | The monthly site budget and job group budgets: reservation, hold, resume, figures |
| [packages.md](packages.md) | Content packages: export, validate, diff, import as drafts, conflicts, expai/packages |
| [package-format.md](package-format.md) | The package format 1.0 and its JSON Schema, attribute values per datatype, the checksum |
| [package-authoring-for-agents.md](package-authoring-for-agents.md) | How a local CLI agent writes a package (terms, data, the validation loop) |
| [diff.md](diff.md) | The diff engine: segmenters per datatype, sentences, hunks with stable ids, metrics, compare and apply |
| [translation-sync.md](translation-sync.md) | Translation sync: no-op / patch / full after a source change, thresholds, the workflow event, the Translations section, the CLI |
| [permissions.md](permissions.md) | Policies, and how the editor's own permissions limit everything the assistant does |
| [full-view-panel.md](full-view-panel.md) | The Intelligence tab: sections, conversation, history, context window, drafts |
| [threads.md](threads.md) | Threads: what a thread is, states and shelves, who sees one, events, retention, the migration |
| [vector.md](vector.md) | Vector (expai/vector): scope, plan and write, review, model and lane, run/stop/queue, polling and streaming, functions |
| [keyboard.md](keyboard.md) | The command palette (Ctrl/Cmd+K), the shortcuts, your own keys, commands of your own |
| [edit-view-integration.md](edit-view-integration.md) | The edit form hook, field actions, how values reach Tiptap / the Online Editor |
| [dashboard.md](dashboard.md) | expai/dashboard (figures, chart, limits, Start writing, view parameters), the menu entries, the dashboard block, the log page |
| [usage-tracking.md](usage-tracking.md) | What is recorded per request, turns, the estimated cost and how to check it, limits, retention, privacy |
| [tools.md](tools.md) | Every tool Claude can call: input, result, permission checks, limits |
| [approval-workflow.md](approval-workflow.md) | Drafts, ezapprove, the collaboration item and the label, the tested path |
| [security.md](security.md) | The key, permissions as the user, no auto-publish, prompt injection, logging and privacy |
| [database.md](database.md) | The four tables, per engine, the install and upgrade script |
| [extending.md](extending.md) | Adding field actions, panel sections, tools, providers, JavaScript hooks (worked examples) |
| [customization.md](customization.md) | Template overrides, CSS tokens, labels, translations, instructions |
| [translations.md](translations.md) | Contexts, the shipped languages, adding one |
| [troubleshooting.md](troubleshooting.md) | Failures and their fixes |
| [faq.md](faq.md) | Short answers |
| [changelog/0.1.0.md](changelog/0.1.0.md) | The first release |
| [examples/expaiexample](examples/expaiexample) | The example extension of extending.md |
