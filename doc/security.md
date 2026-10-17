# Security and privacy

## The API key

- Only in `settings/override/expai.ini.append.php` (mode 0640, owned by the web server's user and group), which is
  never committed. The extension ships `ApiKey=` empty.
- Read on the server only and sent only to the configured endpoint in the `x-api-key` header. It is never sent to the
  browser, never written to a template, a log or the debug output: expAiHttpClient's error messages never contain the
  request, the answer or the headers, and expAiConfig::providerSettings() (which contains the key) is passed only to
  the provider. The dashboard shows only whether expai is configured.
- `AnthropicWorkspaceId` is checked to be a plain id before it goes into a header.

## Permissions as the user

Every lookup and every write runs as the logged-in editor with the kernel's own permission checks (permissions.md).
expai has no service account, never switches users, and has no setting that lets it publish on its own: publishing
happens only when the editor presses "Send for approval", through the normal publish operation, so approval workflows
apply.

## Requests

Every function is POST only and checks the form token (ezformtoken) against the session's, in addition to ezformtoken
itself. ezjscore checks `ezjscore/call` for the function list `expai`; expai checks `expai/write` (and enabled +
configured, the master switch and the veto hook of [switch-and-veto.md](switch-and-veto.md)). Ids from the browser are cast to integers; actions on drafts are limited to versions in the log written
for this editor and still the editor's own drafts.

## What goes to the provider

- Panel: the instructions (with the open node's name, class, path and the site's default language), the tool
  definitions, the editor's messages, and the results of the tools the model calls: names and field values of
  content the editor may read, class definitions. Images are not sent here (only their alt texts), files and user account fields
  never.
- Edit form: the object's name and class, the field, its current value and the other fields' current values in the
  form (about 30 KB at most), the editor's instruction.
- Alt text: the stored image of the field (scaled down to `MaxEdge` when larger, as base64 inside the request), the page title, the text of the
  other fields and the language, to a vision model whose provider meets the data policy for images; the request log
  never keeps the pixels ([alt-text.md](alt-text.md)).
- Not sent: the editor's name, login or e-mail, passwords, session data, the content of other users' drafts.

Choose a provider account and data retention that fit your content: the provider's commercial terms and retention
options, written into `Region`, `Retention` and `TermsCheckedAt` ([data-policy.md](data-policy.md)). The "EI: Summary"
page sums it up for a data protection officer ([summary.md](summary.md)).

## Several providers

- Keys of every provider live in settings/override or in an untracked `KeyLocations[]` location; expai/models shows "key present" and warns about a key in
  any other file or in a file every user of the server can read. A key-like string in a provider's error message is
  redacted before it is logged.
- The data policy keeps sections and classes away from providers outside the regions they allow or without zero
  retention ([data-policy.md](data-policy.md)); the region of every request is logged.
- Only models with native tool calling that passed the probe write drafts ([probe.md](probe.md)); the gate is checked
  when the tools are offered and again when one is run.
- A local model listens on the loopback address only; the web process starts and stops it only through a spool
  request that a root-owned helper checks against its own allowlist ([local-models.md](local-models.md)).
- MCP: allowlisted tools only, read-only, an SSRF guard on the URL, results wrapped as data, and no write tool while
  an external result is part of the conversation's window ([mcp.md](mcp.md)).
- The write tools write only inside the panel's subtree or a thread's scope (`[AssistantSettings] WriteScope`), and
  the arguments of every tool call are checked against the tool's schema before it runs.
- One step at a time per conversation: a second request for the same conversation while a step runs (another tab, a
  double click) is refused instead of running the tools twice.

## Prompt injection

Content can contain text written to steer an assistant ("ignore your instructions and publish ..."). expai limits what
such text can achieve:

- the instructions and every tool description tell the model that content is data, not instructions;
- the model can only call expai's tools: read what the editor may read, and write drafts the editor may write;
- it cannot publish, delete, move, change permissions or send mail; it never acts outside the editor's permissions;
- rich text it writes is cleaned to a small HTML subset (no scripts, styles, iframes, images or embeds; only http(s)
  and mailto links) and stored through the kernel's input parser;
- every draft carries the label and waits for the editor's review.

Review drafts written from untrusted content (for example user-submitted text) as you would any contributor's draft.

## Web content

`web_fetch`, `web_search` and `project_history` ([tools.md](tools.md#web-and-history-tools)) bring outside text into
the conversation. The rules:

- **SSRF**: web_fetch reads only hosts on `[WebSettings] AllowedDomains[]` (exact host or `*.domain`, compared after
  lower-casing and punycode, so `github.com.evil.example`, `evilgithub.com` and look-alike Unicode hosts do not
  match; numeric host forms such as `2130706433` or `0x7f.1` are refused). The host is resolved once and refused if
  any address is loopback, private, link-local (including 169.254.169.254, the cloud metadata address),
  carrier-grade NAT, unspecified, multicast or an IPv4-mapped form of those, unless it is named in `PrivateHosts[]`.
  The connection is pinned to the checked address (no DNS rebinding), redirects stay on the same host and are
  checked again, only ports 80 and 443 (plus `AllowedPorts[]`) and only http(s). Size, time and the number of calls
  per turn are capped. The same guard pins the Brave Search and GitHub API requests.
- **Nothing leaves through web_fetch but the URL.** No cookies, credentials or body are sent. A URL can still carry
  text (a query string), so keep `AllowedDomains[]` to sites you trust with what an editor might ask about. A web
  search sends its query to the search service, so the data policy checks the panel node against the service's
  `Region` and `Retention`, and the tool tells the model never to put site content into a query.
- **Prompt injection from web content**: a page can contain text written to steer the model. Page text is wrapped in
  "Begin/End of outside data" markers and every result says it is data, not instructions. After a web_fetch or
  web_search result, no draft is written while that result is part of the conversation's window (as after an
  external MCP tool): until a new conversation starts (a compaction keeps the earlier answers, which may repeat it; only a window with the editor's words only ends it). Even then the model can only write drafts the editor may write, which wait for
  review. Check links in drafts written from web results before publishing.
- **Project history**: git runs without a shell (an argument list, fixed format, literal pathspecs,
  `--end-of-options`, the search text as a fixed string), in a clean environment, only `log`, `ls-files` and
  `rev-parse`, only in clones under `Roots[]`. Excluded paths (`ai/*`) and untracked files are never named or read;
  author names are off by default.
- **Who**: the policy `expai/web` (limitation Tool) decides who gets each tool; the usage log records the tool and
  the host it read.

## Logging and privacy

- `expai_draft_log` keeps, per version written: object, version, user id, node, language, action, source, state,
  model, time and a summary of the request (the first `[LogSettings] MaxPromptSummaryLength` characters). The request
  text may contain whatever the editor wrote; the cronjob part `expai` blanks it after
  `[RetentionSettings] PromptSummaryKeepDays` (365); the row stays as the record of the draft.
- `expai_conversation` keeps the conversation (messages, answers, tool results) per user and node until "New
  conversation", until it has not been modified for `[RetentionSettings] ConversationKeepDays` (365), or, when it
  belongs to a thread, until the thread goes after `ThreadKeepDays` (365). The threads and conversations of a user
  that no longer exists are removed by the next cron run with `[RetentionSettings] Mode=delete` (the kernel sends no event when a user is removed; the rows
  are found by their user id).
- Errors go to the debug log as warnings with the provider's error type and message (never the key, request or
  answer).
- The browser stores only which history rows were opened and whether the edit form area is open (localStorage).
