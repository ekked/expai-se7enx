# Architecture

## The parts

```
browser (admin)                         server (expai)                                   Anthropic
---------------                         --------------                                   ---------
expai.js  --POST ezjscore/call-->  ezjscore router  -->  expAiServerFunctions::<fn>
  panel, edit form, labels,           (Functions[]=expai)     |  checks POST, form token, Enabled, expai/write
  dashboard buttons                                           v
                                    expAiAssistant (panel)   expAiEditForm (edit form)
                                          |  one model call per request                  POST /v1/messages
                                          +-- expAiAnthropicProvider --> expAiHttpClient --> (tools, thinking,
                                          |                                                 structured output)
                                          +-- expAiToolRegistry --> expAiTool subclasses (run as the user)
                                          |        expAiContentReader / expAiContentWriter (kernel API)
                                          +-- expAiConversation  (table expai_conversation)
                                          +-- expAiDraftLog      (table expai_draft_log)
                                    expAiPublisher: keep / send (content/publish operation) / discard
                                    expAiApprovalCheck: does an approval workflow apply?
```

| Class | File | Role |
| --- | --- | --- |
| `expAiServerFunctions` | classes/expaiserverfunctions.php | the ezjscore functions: load, history, chat, reset, drafts, keep, send, discard, field, fill, labels |
| `expAiAssistant` | classes/expaiassistant.php | one step of a panel conversation; history; the bounded window; draft rows for the panel |
| `expAiEditForm` | classes/expaieditform.php | field actions and "Fill the fields" for the editor's own draft |
| `expAiProvider` | classes/expaiprovider.php | the provider interface (request and answer in the Messages API shape) |
| `expAiAnthropicProvider` | classes/expaianthropicprovider.php | the Anthropic Messages API |
| `expAiHttpClient` | classes/expaihttpclient.php | curl POST with timeouts and a size limit; never logs request, answer or headers |
| `expAiTool`, `expAiToolRegistry`, `expAiToolContext` | classes/expaitool*.php | tools and their registration |
| tools | classes/tools/*.php | get_node, list_children, get_tree_context, list_creatable_classes, get_class_definition, create_draft, update_draft |
| `expAiContentReader` | classes/expaicontentreader.php | nodes, fields, classes as small arrays, with the user's permission checks |
| `expAiContentWriter` | classes/expaicontentwriter.php | creates drafts, writes values through the datatypes, rich text through the kernel's input parser |
| `expAiFieldAction`, `expAiPromptFieldAction`, `expAiFieldActionRegistry`, `expAiFieldRequest` | classes/fieldactions/ | field actions of the edit form |
| `expAiPublisher` | classes/expaipublisher.php | keep, send for approval, discard |
| `expAiApprovalCheck` | classes/expaiapprovalcheck.php | evaluates the approve events before content/publish |
| `expAiConversation`, `expAiDraftLog` | classes/expaiconversation.php, classes/expaidraftlog.php | persistent objects |
| `expAiDashboard`, `expAiFunctionCollection` | classes/expaidashboard.php, classes/expaifunctioncollection.php | the dashboard page, block and fetch functions |
| `expAiSchema` | classes/expaischema.php | the tables, per engine (bin/php/expaischema.php) |
| `expAiPrompts`, `expAiText`, `expAiConfig`, `expAiException` | classes/ | instructions, text helpers, settings, errors |

## How the browser talks to the server

Every request is `POST <siteaccess root>/ezjscore/call` with `ezjscServer_function_arguments=expai::<function>`, the
form token (`ezxform_token` field and the `X-CSRF-Token` header) and the function's fields. ezjscore checks
`ezjscore/call` for the function list `expai` (ezjscore.ini `[ezjscServer_expai] Functions[]=expai`); each function
then checks that the request is a POST, that the form token matches the session's, that expai is enabled and
configured, and the policy `expai/write` (`labels` needs only a logged-in user who may read the content). Failures
are thrown as `expAiException` with a translated message, which ezjscore returns as `error_text`; expai.js shows it.

The token and the root URL come from what every admin page already has: `#ezxform_token_js` (ezformtoken) and
`$.ez.root_url` (ezjscore's jqueryio). expai.js and expai.css are in design.ini's `BackendJavaScriptList` and
`BackendCSSFileList`, so the ezjscore packer bundles them with the admin's scripts; the panel, the edit form and the
dashboard are started by markup (`#exp-ai-panel`, `#exp-ai-edit`, `#exp-ai-dashboard`), the label everywhere.

The module `expai` exists for the policies (`write`, `log`) and two pages: `expai/dashboard` and `expai/log`.

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

Because each HTTP request makes one model call, its duration is bounded by `[AISettings] Timeout` (115 s by default)
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

## The provider call

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
the editor opens meanwhile, and the history survives logging out. See database.md.

## Publishing

expai never publishes on its own. "Send for approval" (expAiPublisher::send) checks that the version is the editor's
own draft written by expai, that the editor may edit it, and that required fields have content, then runs
`eZOperationHandler::execute( 'content', 'publish', ... )` as the editor (asynchronous publishing off for this call)
and reads the version's status afterwards: published, pending (an approval workflow holds it) or still a draft.
