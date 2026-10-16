# Architecture

## The parts

```
browser (admin)                          server (expai)                                       providers
---------------                          --------------                                       ---------
expai.js, expai_vector.js,  --POST-->  ezjscore/call  -->  expAiServerFunctions (expai::*)
expai_presets.js, ...                    (Functions[]=expai)  expAiVectorServerFunctions (expaivector::*) ...
  panel, edit form, Vector,                                   | POST, form token, master switch, policies
  presets, alt text, labels                                   v
                                     expAiAssistant (panel) / expAiThreadRunner (Vector) / expAiEditForm
                                           |  one model call per request
                                           +-- expAiGate: master switch + veto hook
                                           +-- expAiLimits, expAiDataPolicy, expAiModelFallback
                                           +-- expAiProviderRegistry --> expAiAnthropicProvider  --> Anthropic Messages API
                                           |                         --> expAiOpenAiChatProvider --> any Chat Completions API
                                           |                         --> a custom class            (Ollama, OpenRouter, HF ...)
                                           |     (each wrapped by expAiUsageRecorder: one usage row per request)
                                           +-- expAiToolRegistry --> tools (run as the user): content, drafts,
                                           |        web, history, service tools, MCP bridge and external MCP servers
                                           +-- threads, events, staged changes, plans (classes/vector/)
                                     expAiPublisher: keep / send (content/publish operation) / discard
                                     expai/stream: Server-Sent Events of a thread (expAiVectorStream)
                                     expqueue jobs: queued requests, batches, chains, translation checks, alt text, imports
```

| Area | Classes | Role |
| --- | --- | --- |
| Entry points | `expAiServerFunctions`, `expAiVectorServerFunctions`, `expAiPresetServerFunctions`, `expAiAltTextServerFunctions`, `expAiTranslationSyncServerFunctions`; modules/expai/*.php | the ezjscore functions and the views |
| The panel | `expAiAssistant`, `expAiConversation`, `expAiPrompts` | one step of a panel conversation; history; the bounded window |
| Vector and threads | classes/vector/ (`expAiThread`, `expAiThreadRunner`, `expAiChangeStager`, `expAiPlanner`, `expAiVectorStream` ...) | threads, runs, staged review, plans, events ([vector.md](vector.md)) |
| The edit form | `expAiEditForm`, classes/fieldactions/, classes/alttext/ | field actions, "Fill the fields", presets, the Improve menu, alt text |
| Providers | `expAiProvider`, `expAiProviderBase`, `expAiProviderRegistry`, `expAiAnthropicProvider`, `expAiOpenAiChatProvider`, `expAiPromptedToolProvider`, `expAiHttpClient`, `expAiProviderHealth` | adapters to one internal format (the Messages API shape), the registry of providers and models, health ([providers.md](providers.md)) |
| Safety | `expAiGate`, `expAiDataPolicy`, `expAiProbe`, `expAiLimits`, `expAiModelFallback` | master switch and veto, regions and retention, the write gate, limits, fallback |
| Tools | `expAiTool`, `expAiToolRegistry`, `expAiToolContext`, classes/tools/, classes/web/, classes/history/, classes/mcp/ | what the model may call, as the user ([tools.md](tools.md)) |
| Content | `expAiContentReader`, `expAiContentWriter`, `expAiPublisher`, `expAiApprovalCheck`, `expAiDraftLog` | reading with the user's checks, writing drafts, keep / send / discard |
| Usage and cost | `expAiUsageRecorder`, `expAiUsageLog`, `expAiCost`, `expAiDashboard`, `expAiSummary` | the usage log, prices, the dashboard and the "EI: Summary" page |
| Queued work | classes/queue/, classes/lanes/, classes/budget/, classes/chain/, classes/diff/, classes/package/ | lanes and batches, budgets, chains, translation sync, packages ([lanes.md](lanes.md)) |
| Help | `expAiHelp` | the documentation at `expai/help` |
| Storage | `expAiSchema` (with `expAiThreadSchema`, `expAiChainSchema`) | the 15 tables, per engine ([database.md](database.md)) |

## How the browser talks to the server

Every request is `POST <siteaccess root>/ezjscore/call` with `ezjscServer_function_arguments=expai::<function>` (or
`expaivector::`, `expaipreset::`, `expaialttext::`, `expaitranslation::`), the form token (`ezxform_token` field and
the `X-CSRF-Token` header) and the function's fields. ezjscore checks `ezjscore/call` for the function list `expai`
(every expai group in ezjscore.ini has `Functions[]=expai`); each function then checks that the request is a POST,
that the form token matches the session's, the master switch and the veto hook ([switch-and-veto.md](switch-and-veto.md)),
and the function's policy (`expai/write`; `labels` needs only a logged-in user who may read the content). Failures
are thrown as `expAiException` with a translated message, which ezjscore returns as `error_text`; the scripts show it.

The token and the root URL come from what every admin page already has: `#ezxform_token_js` (ezformtoken) and
`$.ez.root_url` (ezjscore's jqueryio). expai's scripts and styles are in design.ini's `BackendJavaScriptList` and
`BackendCSSFileList`, so the ezjscore packer bundles them with the admin's (with the packer on, `[Packer] AppendLastModifiedTime=enabled` puts the change time into the name of the packed file, which is what keeps a browser from showing an old script after an update; with the packer off, as in `DevelopmentMode`, the files keep their own address and an old copy is reloaded by hand). The scripts the templates load directly (presets, alternative text, translations) carry `?v=<change time>` themselves (template operator `expai_version`); the panel, the edit form, the dashboard
and Vector are started by their markup (`#exp-ai-panel`, `#exp-ai-edit`, `#exp-ai-dashboard`, `#exp-ai-vector`).

The module `expai` holds the policies ([permissions.md](permissions.md)) and the views `expai/dashboard`, `expai/log`,
`expai/vector`, `expai/stream`, `expai/chain`, `expai/alttext`, `expai/models`, `expai/budgets`,
`expai/summary` and `expai/help`.

## The step loop (panel)

A request of the panel is one call to the model:

1. `chat` with `message`: the editor's message is added to the conversation and stored *before* the call.
2. The model answers. Text is shown; tool calls (`tool_use`) are run one after the other as the current user, their
   results stored as the next user turn, and the answer says `status: continue`.
3. The browser calls `chat` with `continue=1` at once; the model sees the tool results and either calls more tools or
   answers in words (`status: done`).

`[AssistantSettings] MaxToolIterations` caps the calls per editor message; the last allowed call is made with
`tool_choice: none`, so the model must answer in words. Every step returns the exchange for the history, the steps
for the panel and the current list of drafts.

## Long turns (Apache, PHP-FPM, Velocity)

Because each HTTP request makes one model call, its duration is bounded by the provider's `Timeout` (`[AISettings] Timeout`
or `[Provider_<id>] Timeout`: 115 s by default for hosted providers, 280 s for the shipped local one, at most 600)
plus the tool work, and the browser does the looping. The `chat`, `field` and `fill` functions raise PHP's time limit
to Timeout + 30 s and ignore a client abort (so a stored step is never cut in half). Keep Timeout below:

- the web server's request timeout (Apache `Timeout`; a reverse proxy's read timeout, e.g. nginx `proxy_read_timeout`),
- PHP-FPM's `request_terminate_timeout` when it is set,
- Velocity's limits, if any are configured for the site.

On Exponential Velocity the request ends through ezjscore's own output (`eZExecution::cleanExit()`), which Velocity
implements by throwing; expai never catches that: its functions only catch `expAiException`. Long calls block one
worker for their duration; the pool serves other requests meanwhile. Velocity's workers keep loaded classes and
settings, so a changed expai class or setting needs `exp:velocity restart` (or the deploy).

The browser's own timeout for one request is 180 s; a timeout is shown as "The server did not answer in time", and
**Continue** resumes the conversation from what was stored.

A model too slow for one web request (a local model on a CPU host) runs its turns in the job queue instead
(`expai.thread.turn`, [vector.md](vector.md)); the browser follows the thread's events.

## Events and streaming

Runs of threads (Vector, and panel turns in the background) write their progress as thread events
(`expai_thread_event`). The browser reads them through `expai/stream` (Server-Sent Events: the session lock released
first, a first line at once, at most `[VectorSettings] StreamSeconds`, resumed with Last-Event-ID) or, when no stream
reaches it within 2 seconds, by polling `expaivector::events`. On Apache the FastCGI worker needs `flushpackets=on`
and no buffering proxy in between ([../INSTALL.md](../INSTALL.md), "Streaming and long requests"); Velocity's pool
workers stream themselves.

## The provider call (Anthropic)

`expAiAnthropicProvider` sends `POST {Endpoint}` with `x-api-key`, `anthropic-version` and, when set,
`anthropic-workspace-id`. The body has `model`, `max_tokens`, `system`, `messages`, `tools`, `output_config`
(`effort`, and `format` with a JSON schema for structured answers), top-level `cache_control` (prompt caching) and
`fallbacks: "default"` with the beta header `server-side-fallback-2026-07-01` (a request the model declines for a
safety reason is retried on the model Anthropic recommends; Claude API only).

Assistant turns are stored exactly as received (`content_json`) and sent back unchanged, because the model's
thinking blocks are signed and bound to the exact start of the conversation. For the same reason a conversation keeps
the instructions and tool definitions it started with (its "frame"), and the stored list is only ever appended to.
If the API still refuses old thinking blocks (a conversation stored before frames existed), expai retries once with
`thinking.block_binding.prefix_mismatch_behavior: drop_block` (beta `thinking-binding-controls-2026-08-01`).

Field actions and "Fill the fields" are single calls without tools and with a JSON schema for the answer
(`{value, note}` or `{fields: [{identifier, value}], note}`).

Other providers go through the same interface: `expAiOpenAiChatProvider` translates the internal format to and from
OpenAI Chat Completions (Hugging Face router, OpenRouter, Mistral, Ollama, vLLM ...), and `expAiPromptedToolProvider`
describes the tools in the instructions for a model without native tool calling ([providers.md](providers.md)).

## The bounded window and the history

The panel's conversation is stored twice: the **history** (what the panel shows, one entry per exchange, the last 200)
and the **window** (what goes to the model). When the window holds `[AssistantSettings] ContextWindowTurns`
exchanges, the next message starts a new window: a summary of the older exchanges (each cut to a line from its own
text, no extra model call), then the last half of the exchanges word for word (as plain text, without tool calls or
thinking blocks), then the new message. The frame stays the same, so prompt caching of instructions and tools keeps
working. Details: full-view-panel.md.

## Storage

- `expai_conversation`: one row per user and node (the newest is the current one; "New conversation" removes them).
  Column `messages` holds a JSON document `{system, tools_json, messages, history, window_turns}`.
- `expai_draft_log`: one row per version expai wrote or filled (create, update, field, fill), with its state
  (draft, kept, pending, published, discarded) kept in step with the version's status when it is shown.

A table rather than the session: a long step would otherwise be overwritten by the session write of any other page
the editor opens meanwhile, and the history survives logging out. The other tables (usage, probe results,
batches, packages, threads, chains) are in [database.md](database.md).

## Publishing

expai never publishes on its own. "Send for approval" (expAiPublisher::send) checks that the version is the editor's
own draft written by expai, that the editor may edit it, and that required fields have content, then runs
`eZOperationHandler::execute( 'content', 'publish', ... )` as the editor (asynchronous publishing off for this call)
and reads the version's status afterwards: published, pending (an approval workflow holds it) or still a draft.
