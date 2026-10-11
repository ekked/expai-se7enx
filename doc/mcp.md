# MCP tools in the panel

expai can use tools of MCP servers in the panel, in two layers. Both are read-only, and both need the policy
`expai/mcp` (limitation Server: `exp` for the bridge, or a server id).

## The in-process bridge to expmcp

```ini
[McpBridge]
Enabled=enabled
Tools[]=exp_content_query
```

Audited read tools of the extension expmcp are called in the same PHP process, as the current user, named
`exp__<tool>` (e.g. `exp__exp_content_query`). The tool object is built the way expmcp builds it, with its own security
object (expmcp's siteaccess allowlist, `expmcp.ini [Security] AllowedSiteAccesses`, still applies, and writes are off)
and a logger that drops everything; expmcp is not changed. Nodes the data policy keeps away from the panel's provider
are left out of the results.

Only tools audited in `expAiMcp::BRIDGE_TOOLS` are accepted; any other name in `Tools[]` is ignored with a warning.

| expmcp tool | Verdict (2026-10-11) | Why |
| --- | --- | --- |
| `exp_content_query` | allowed | the subtree fetch applies the current user's read limitations |
| `exp_content_get` | refused | fetches any object without `canRead()` |
| `exp_search` | refused | runs as expmcp's service user |
| `exp_fetch` | refused | runs any template fetch function |
| every write tool | refused | the panel writes only through its own draft tools |

expai's own lookup tools (`get_node`, `list_children`, ...) already cover most reading; the bridge adds expmcp's
richer content query (attribute filters, sorting, depth). A cleaner seam would be a constructor of expmcp's tools
without the security and logger objects (or a small factory such as `expMcpToolBase::forCurrentUser()`); the bridge
does not need it.

## External MCP servers

```ini
[McpSettings]
Servers[]=docs

[McpServer_docs]
Label=Documentation search
URL=https://mcp.example.org/mcp
Token=...                          # settings/override only
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
