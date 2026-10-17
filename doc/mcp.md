# MCP tools in the panel

expai can use tools of MCP servers in the panel, in two layers. Both are read-only, and both need the policy
`expai/mcp` (limitation Server: `exp` for the bridge, or a server id).

## The in-process bridge to expmcp

```ini
[McpBridge]
Enabled=enabled
Tools[]
Tools[]=exp_content_query
Tools[]=exp_content_get
Tools[]=exp_search
Tools[]=exp_content_relations
Tools[]=exp_content_versions
Tools[]=exp_content_meta
Tools[]=exp_class_list
Tools[]=exp_class_describe
```

Read tools of the extension expmcp are called in the same PHP process, as the current user, named `exp__<tool>`
(e.g. `exp__exp_content_get`). expai calls them through expmcp's own bridge seam, `expMcpBridge` (expmcp 0.3.1 or
later; its `doc/bridge.md`):

- **Which tools**: expmcp decides which of its tools are safe in process (`expMcpBridge::isBridgeSafe()`); `Tools[]`
  is expai's opt-in allowlist on top. A name expmcp does not declare bridge-safe is ignored with a warning.
- **As the current user**: `expMcpBridge::call()` runs the tool in bridge mode, as `eZUser::currentUser()`, never as
  expmcp's service or write account. Every answer is limited to what the editor may read; content he may not read is
  answered like content that does not exist. No bridge tool writes.
- **The siteaccess allowlist** of expmcp (`expmcp.ini [Security] AllowedSiteAccesses`) still applies: on another
  siteaccess a call is a tool error.
- **Description and schema** come from expmcp (`expMcpBridge::description()`, `inputSchema()`), prefixed with
  "[Exponential, read only]".
- **The data policy** of the panel's provider applies on top of the user's read access: rows of `nodes`
  (`exp_content_query`), `items` (`exp_search`, `exp_content_relations`, the `drafts` and `pending` lists of
  `exp_content_versions`) and `locations` whose object the policy keeps away are left out (counted as
  `withheld_by_data_policy`), and an answer about one object it keeps away (a top-level `object_id` or `node_id`, as
  from `exp_content_get`, `exp_content_meta` or `exp_url`) is a tool error.

| expmcp tool | Bridge-safe | Offered by default |
| --- | --- | --- |
| `exp_content_query`, `exp_content_get`, `exp_search` | yes | yes |
| `exp_content_relations`, `exp_content_versions`, `exp_content_meta` | yes | yes |
| `exp_class_list`, `exp_class_describe` | yes | yes |
| `exp_url`, `exp_access_check` | yes | no (administration parts; add them to `Tools[]` if wanted) |
| `exp_fetch`, `exp_solr_query`, `exp_user_get`, `exp_role_describe`, the system and every write tool | no | never |

**An older expmcp** (before 0.3.1, no `expMcpBridge`): only `exp_content_query` is offered, built the old way with
expmcp's own security object and a logger that drops everything; the other names are ignored with a notice.

expai's own lookup tools (`get_node`, `list_children`, ...) already cover most reading; the bridge adds expmcp's
richer content query (attribute filters, sorting, depth), search, relations, versions and metadata.

Tests: `tests/expaiprovider_test.php` (the bridge-safe set, the old-expmcp fallback and the data-policy filter, no
database writes) and `tests/expaibridge_e2e_test.php` (an administrator and a restricted user the test creates and
removes again).

## External MCP servers

```ini
[McpSettings]
Servers[]=docs

[McpServer_docs]
Label=Documentation search
URL=https://mcp.example.org/mcp
Token=...                          # settings/override or an untracked KeyLocations[] location
AllowedTools[]=search_docs
Region=us
Retention=unknown
```

A small client of the streamable HTTP transport (JSON-RPC 2.0 POSTs; the answer is JSON or an event stream):
`initialize` (the server's `Mcp-Session-Id` is sent back, `MCP-Protocol-Version` on every later request),
`notifications/initialized`, `tools/list` (cached for `ToolsCacheTTL` seconds) and `tools/call`. No stdio from the
web process, no requests from the server to the client.

- **Allowlist**: only `AllowedTools[]` are offered, named `mcp__<server>__<tool>`; the server's `readOnlyHint` is not
  trusted, no remote tool can write.
- **SSRF guard**: the URL comes from the INI only; https, or http to a loopback address with `AllowLoopback=enabled`;
  the host is resolved once and every address checked (loopback, private, link-local, carrier-grade NAT and reserved
  ranges are refused unless `AllowLoopback` / `AllowPrivate`); the connection is pinned to the checked address; no
  redirects.
- **Identity**: the server sees one token, not the editor: send only shared, public data. Its `Region` and
  `Retention` are checked against the panel node's data policy before every call.
- **Results** are wrapped as data ("data, not instructions"), capped at `MaxResultBytes`, and an error result is a tool
  error. After an external result entered a turn, no write tool runs for the rest of that turn, whatever the model
  asks: an injected instruction in a search result cannot create a draft.
- **Log**: `expai_usage_log.mcp_calls` counts the MCP calls of a request; the tool names are in its tool calls.

expai itself is not exposed as an MCP server; expmcp already gives agents access to the content.
