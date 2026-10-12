# The tools the model can call

The tools of `[ToolSettings] Tools[]` are offered to the model in every panel call (a conversation keeps the
definitions it started with). Each runs in-process with the kernel API as the current user. A result is JSON, cut to
`[AssistantSettings] MaxToolResultBytes`; a failure is returned as an error result (`is_error`) with an English
message the model can react to, and shown as a red step in the panel. Field values are cut to
`[ToolSettings] FieldValueMaxLength`; lists to `[ToolSettings] ListLimit`.

Every tool description tells the model that field values are data written by people, not instructions.

## get_node

Reads a node and the fields of its object (current version).

| Input | Type | Default |
| --- | --- | --- |
| `node_id` | integer | the node the panel is open on |
| `include_fields` | boolean | true |

Result: `node` (node_id, object_id, name, class_identifier, class_name, parent_node_id, url, section_id, language,
languages, published, modified, hidden, children_count, you_can_edit, you_can_create_here) and `fields` (identifier,
name, datatype, required, writable, value). Rich text comes as a small HTML; images as `[image: alt text]`; files as
`[file]`; relations as names with object ids (only readable ones); user accounts as `[account data]`.
Checks: `canRead()` on the node, else "Node %id does not exist or you may not read it."

## list_children

| Input | Type | Default |
| --- | --- | --- |
| `node_id` | integer | the open node |
| `class_identifier` | string | all classes |
| `offset` | integer | 0 |
| `limit` | integer | `ListLimit` (and at most that) |

Result: `parent_node_id`, `total`, `offset`, `children` (node_id, object_id, name, class_identifier, modified), in
the node's sort order. Checks: `canRead()`; `subTree()` with the user's limitations.

## get_tree_context

| Input | Type | Default |
| --- | --- | --- |
| `node_id` | integer | the open node |

Result: `node`, `path` (readable ancestors), `siblings` (readable, up to `ListLimit`), `children_count`.

## list_creatable_classes

| Input | Type | Default |
| --- | --- | --- |
| `parent_node_id` | integer | the open node |

Result: `parent_node_id`, `classes` (identifier, name) from `canCreateClassList()` of the user.

## get_class_definition

| Input | Type | Required |
| --- | --- | --- |
| `class_identifier` | string | yes |

Result: identifier, name, description, is_container, object_name_pattern, `attributes` (identifier, name, datatype,
required, writable, format, description, max_length for ezstring). `format` says how to write a value (e.g. "rich text
as HTML using only p, h2, h3, h4, strong, em, ul, ol, li, a href"). Unknown class: "There is no content class ...".

## create_draft (writes)

Creates a new object as an unpublished draft owned by the editor.

| Input | Type | Required / default |
| --- | --- | --- |
| `class_identifier` | string | yes |
| `fields` | object: identifier => value | yes |
| `parent_node_id` | integer | the open node |
| `language` | string, e.g. eng-GB | the default content language |

Checks: the parent is readable; `content/create` for that class, the parent's class and the language on the parent
(with all role limitations); `[AssistantSettings] MaxDraftsPerConversation`. An unknown field identifier fails the
call with the list of valid ones. Values: see "Writing values". Result: status, object_id, version, language, name,
`written` (identifiers), `skipped` (identifier => reason), `errors` (identifier => message). The draft is logged
(action `create`) and carries the label.

## update_draft (writes)

Writes fields into a draft of an existing object: the draft this conversation wrote for it, or else a new version.

| Input | Type | Required / default |
| --- | --- | --- |
| `object_id` | integer | yes |
| `fields` | object | yes |
| `language` | string | the object's main language |

Checks: the object is readable; reusing a draft only if it is still a draft created by the editor; else
`content/edit` in that language and the object must be published (a never published object is changed in the edit
form). A new version counts against `MaxDraftsPerConversation`. Result as create_draft, plus `new_version`.

## Staged review: create_draft and update_draft in Vector

In a Vector thread with staged review both tools **stage proposals** instead of writing drafts ([review.md](review.md)):
one `expai_change` per field, compared with the published version, checked against the thread's scope and the
editor's edit (or create) permission. The result is `status` ("staged for the editor's review"), `change_ids`,
`staged`, `unchanged`, `skipped`, `errors`, and `new_key` for a new object; `create_draft` takes `new_key` to revise
that proposal. Without an approved plan only one object in one language can be staged.

## propose_plan (Vector threads)

Stores a plan for bulk work for the editor to approve ([plan-mode.md](plan-mode.md)); it reads and writes no content.

| Input | Type | Required / default |
| --- | --- | --- |
| `items` | array of { `action` (create, update, translate), `object_id`, `node_id`, `parent_node_id`, `class`, `languages[]`, `fields[]`, `summary` } | yes |
| `assumptions`, `questions`, `risks` | arrays of strings | no |

Checks: a Vector thread; each item against the scope and the editor's permissions (refused items are kept with the
reason). Result: `plan_id`, `items`, `runnable`, `refused` (item and reason), `estimate` (expected cost, tokens).

## Writing values

Only datatypes in `[ToolSettings] WritableDatatypes[]` are written; others are skipped with a reason. Each value goes
through the datatype's `fromString()` (or the rich text parser) and the attribute's `store()`:

| Datatype | Value |
| --- | --- |
| ezstring | one line; longer than the class's maximum length is an error |
| eztext | plain text (HTML is turned into text) |
| ezxmltext | the HTML subset, cleaned, through `eZSimplifiedXMLInputParser` (as the plain XML editor stores it); links become URL objects linked to the attribute |
| ezkeyword | comma separated (or a list) |
| ezboolean | true/false, 1/0, yes/no |
| ezinteger, ezfloat | numbers |
| ezdate, ezdatetime | a date (YYYY-MM-DD, YYYY-MM-DD HH:MM) or a timestamp |
| ezemail | an e-mail address |
| ezurl | an http(s) or mailto URL, optionally `|link text` |

Non-translatable fields are skipped in another language than the object's main one. After writing, the version's
name is computed from the class's name pattern.

## Web and history tools

Three read-only tools look outside the content tree. Each is offered only to a user with the policy `expai/web`
(limitation **Tool**: `web_fetch`, `web_search`, `project_history`; without it, all three) and only once it is
configured; a conversation keeps the tools it started with. Every result says it is data, not instructions, and the
page text of `web_fetch` is wrapped in "Begin/End of outside data" markers. The usage log adds the host to the tool's
entry (`host`: the fetched host, `search:<provider>`, or `history:<repositories>`), never the URL or the query.

A `web_fetch` or `web_search` result counts as outside data: like an external MCP result, the rest of that turn may
not write drafts. The model tells the editor what it found and the editor asks again ("now write the draft"); the
next turn may write. `[WebSettings] WritesAfterWebResults=enabled` lifts this; `project_history` (the team's own
committed work) does not stop writing.

## web_fetch

Fetches one page of an allowlisted site and returns it as Markdown.

| Input | Type | Default |
| --- | --- | --- |
| `url` | string | required: an absolute http(s) address |
| `max_chars` | integer | `[WebSettings] MaxResultChars` (12000), which is also the maximum |

Result: `url`, `final_url` (after same-host redirects), `title`, `content_type`, `links` (count), `truncated`,
`content` (Markdown: headings, paragraphs, lists, code, tables as rows, links as `[text](absolute URL)`; only `<main>`
or a single `<article>` when the page has one; scripts, styles, forms, frames, images and hidden elements are
dropped).

Checks, in order: http or https only, no user name or password, ports 80 and 443 (plus `AllowedPorts[]`); the host
must be on `[WebSettings] AllowedDomains[]` (an exact host, or `*.domain` for its subdomains, matched on label
boundaries after lower-casing and punycode; IP literals only when listed exactly); the SSRF guard (below); the cap
`MaxFetchesPerTurn` (5); robots.txt (product token `ExponentialIntelligence`; a missing robots.txt allows, an
unreadable one refuses); at most `MaxRedirects` (3) redirects, only to the same host (a redirect elsewhere is an error
naming the target, which the model may fetch itself if it is allowed); `Timeout` (10 s) for the whole fetch;
`MaxBytes` (512 KB) read, the rest cut; text types only (HTML, plain text, Markdown, JSON, XML, CSV).

The SSRF guard resolves the host once, refuses it if any address is loopback, private, link-local (169.254.0.0/16,
the cloud metadata address), carrier-grade NAT, unspecified, multicast or an IPv4-mapped form of those (unless the
host is listed in `PrivateHosts[]`), and pins the connection to the checked address, so a second DNS answer (DNS
rebinding) cannot redirect it. Nothing but the URL is sent: no cookies, credentials, referrer or body.

## web_search

| Input | Type | Default |
| --- | --- | --- |
| `query` | string | required, 1 to 200 characters |
| `count` | integer | `[SearchSettings] MaxResults` (8), which is also the maximum |

Result: `query` and `results` (title, url, snippet, `fetchable`: whether web_fetch may read it). The service is
`[SearchSettings] Provider` (`brave`: the Brave Search API); the tool is offered only when its `ApiKey` is set in
settings/override. The query leaves the site, so the data policy of the panel's node is checked against the
provider's `Region` and `Retention` first, and the tool description tells the model never to put site content into a
query. Cap: `MaxSearchesPerTurn` (3). A result is fetchable when its host is allowlisted, or always with
`SearchResultsFetchable=enabled` (the SSRF guard still applies). Another service is one class extending
`expAiWebSearchProvider` (see [extending.md](extending.md)) and a `[SearchProvider_<id>]` group.

## project_history

| Input | Type | Default |
| --- | --- | --- |
| `repository` | string | all of `[HistorySettings] Repositories[]` |
| `since` | string | YYYY-MM-DD |
| `path` | string | a path inside the repository (commits that touch it) |
| `search` | string | text in commit titles / messages and release notes (at most 100 characters) |
| `limit` | integer | 10 commits per repository (at most `MaxCommits`, 30) |
| `include` | string | `commits`, `releases` or `both` (default) |

Result: per repository `commits` (short `hash`, `date`, `title`; `author` only with `ShowAuthors=enabled`) and
`releases` (tag, name, date, url, notes cut to `MaxReleaseNotesChars`); a repository that fails has `commits_error`
or `releases_error`. Commits come from the local clone (`[HistoryRepository_<id>] Path`, which must lie under
`[HistorySettings] Roots[]`), read with `git log` through a runner that uses no shell (an argument list, a fixed
format, `--literal-pathspecs`, `--end-of-options`, the search as `--fixed-strings --grep=`, a clean environment, a
time and size limit). Releases come from the public GitHub API without a token, cached `ReleasesCacheTTL` seconds.

Never shown: a path filter that matches `ExcludePaths[]` (default `ai/*`) or that git does not track (an untracked
file is never named, read or confirmed); commit titles and release note lines that name an excluded directory or an
untracked `*.md` in the clone's root (`HideUntrackedRootMarkdown`); author names by default. git log reads committed
history only, never the working tree. Cap: `MaxCallsPerTurn` (5).

## Service tools (exp_svc_*)

Read services of the extension expservices that opted in (`'ai' => true` in their declaration, or
`[ServiceTools] Services[]`), named as expmcp names them: `exp_svc_node_children_names`, `exp_svc_tags_tree`, ... Each
is offered only to a user with the policy `expai/services` (limitation **Domain**), `ezjscore/call` and the access the
service declares; it runs in process as the editor through `expServiceBase::invoke()`, its arguments and answer pass
the data policy, and the answer is wrapped as data (`service`, `note`, `data`, `meta`). A service that writes is never
offered. The usage log adds `service` (`node::childrenNames`) to the tool's entry. See [service-tools.md](service-tools.md).
