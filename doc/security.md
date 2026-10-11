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
configured). Ids from the browser are cast to integers; actions on drafts are limited to versions in the log written
for this editor and still the editor's own drafts.

## What goes to the provider

- Panel: the instructions (with the open node's name, class, path and the site's default language), the tool
  definitions, the editor's messages, and the results of the tools the model calls: names and field values of
  content the editor may read, class definitions. Images and files are not sent (only alt texts), user account fields
  never.
- Edit form: the object's name and class, the field, its current value and the other fields' current values in the
  form (about 30 KB at most), the editor's instruction.
- Not sent: the editor's name, login or e-mail, passwords, session data, the content of other users' drafts.

Choose a provider account and data retention that fit your content. With Anthropic, see their commercial terms and
data retention options.

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

## Logging and privacy

- `expai_draft_log` keeps, per version written: object, version, user id, node, language, action, source, state,
  model, time and a summary of the request (the first `[LogSettings] MaxPromptSummaryLength` characters). The request
  text may contain whatever the editor wrote; keep that in mind for data protection requests (delete the rows of a
  user with the user).
- `expai_conversation` keeps the conversation (messages, answers, tool results) per user and node until "New
  conversation" or until removed. Delete old rows by your own retention policy.
- Errors go to the debug log as warnings with the provider's error type and message (never the key, request or
  answer).
- The browser stores only which history rows were opened and whether the edit form area is open (localStorage).
