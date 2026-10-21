# Permissions

## Policies of expai

| Policy | Allows |
| --- | --- |
| `expai/write` | The Intelligence tab, the edit form controls, every ezjscore function except `labels`, the dashboard page (own usage), the dashboard block, the left menu entries, `expai/log` (own requests and versions), the "Missing alt text" report (`expai/alttext`) |
| `expai/log` | everyone's requests and versions on `expai/log` (filter by user with `/(user)/<id>`), the "Site" view of the dashboard with the top users, the site limits |
| `expai/settings` | for administrators: the dashboard's Details show where the site default model is set (settings file and placement) |
| `expai/model_use` | choosing models of providers outside `[ProviderSettings] ProvidersWithoutPolicy[]` (default: every provider but anthropic). Limitation **Provider**: only the models of those providers. Without it a user has the site default and the open providers' models; a model posted that is not allowed is refused ("This model is not available.") |
| `expai/model_manage` | the view expai/models: providers, key presence, probe results, the local model server (start, stop, pull, remove, test prompt) |
| `expai/mcp` | MCP tools in the panel ([mcp.md](mcp.md)). Limitation **Server**: `exp` (the in-process expmcp bridge) or the id of an external server |
| `expai/lane_fast` | a queued request may ask for a fast lane (a price premium; [lanes.md](lanes.md)) |
| `expai/chain` | AI chains ([chains.md](chains.md#permissions)): with `expai/write`, starting runs (Intelligence tab, dashboard), the view `expai/chain`, approving, asking for changes, stopping and continuing runs at their checkpoint. Limitation **Chain**: only those chains |
| `expai/web` | the web and history tools in the panel ([tools.md](tools.md#web-and-history-tools)). Limitation **Tool**: `web_fetch`, `web_search`, `project_history`; without it all three (each is still offered only once configured) |
| `expai/translation_sync` | in the Translations section of the Intelligence tab: Check translations, Approve, Translate in full, Skip (`expaitranslation::check`, `approve`, `skip`), with `expai/write`; Approve and Skip also need `content/edit` in that language ([translation-sync.md](translation-sync.md)) |
| `expai/summary` | the view `expai/summary`, the printable "EI: Summary" page (Setup > EI: Summary, and a link in the dashboard's Details): providers, regions, retention, where keys come from (never the keys), roles, lanes, data policy, limits, tools ([summary.md](summary.md)) |
| `expai/services` | service tools: the read services of expservices that opted in, called by the assistant as the editor ([service-tools.md](service-tools.md)); each call also needs `ezjscore/call` and the access the service declares. Limitation **Domain**: only the services of those domains (`node`, `tags`, ...); without it every opted-in service |

The help: Dashboard > EI: Help opens the extension's index in the help center (`exphelp/extensions/expai`) and is shown
to users with `exphelp/read`; the dashboard's Help link and the "?" links open their page there
(`exphelp/viewer/ext/expai/<page>.md`). A user without `exphelp/read` gets the help center's access denied. The help
center is that of Exponential 6.0.15 and later; on an older kernel these links open nothing useful.

`expai/log` also opens `expai/budgets` (the site budget, job groups, lanes, batches); with `expai/settings` its
"Resume held jobs" button ([budgets.md](budgets.md)).

`expai/model_use`, `expai/mcp`, `expai/web`, `expai/services` and `expai/chain` have one limitation each; the others none. What the assistant may do in the content
tree is decided by the editor's content policies (below). The tab, the menu entry and the dashboard block are not shown at all without `expai/write`.

## ezjscore

The browser reaches expai through `ezjscore/call`, and ezjscore.ini says `[ezjscServer_expai] Functions[]=expai`, so
the user needs the policy `ezjscore/call` without limitation or with the limitation "function list" containing
`expai`. Many installations give `ezjscore/call` to all logged-in users through the Member role.

## Everything runs as the editor

- **Reading**: every lookup goes through the kernel's checks for the current user: `canRead()` on nodes and objects,
  `subTree()` with the user's limitations for children and siblings, `canCreateClassList()` for creatable classes.
- **Creating**: `create_draft` checks `content/create` on the parent node for that class, the parent's class and the
  language (`checkAccess( 'create', class, parent class, false, language )`), with every limitation the role has
  (section, subtree, class, parent class, language, owner ...). The object is created in the parent's section, owned
  by the editor, as version 1 with status draft.
- **Changing**: `update_draft` checks `content/edit` (in that language) on the object and writes a new version
  owned by the editor, or the draft it wrote before for the editor in this conversation.
- **Edit form**: field actions and "Fill the fields" work only on a draft the editor owns and may edit; they return
  values and store nothing.
- **Keep / Send / Discard**: only for versions in expai's log written for this editor, still the editor's own draft,
  and only if the editor may edit it (`editAccess()`, or for a never published object the right to create it
  there, as the kernel's discard does). "Send" runs the publish operation as the editor; the workflow events see the
  editor as the user.
- **Labels**: shown for versions of objects the user may read, for the editor's own versions, and in approval items
  the user takes part in.

expai never uses another account, never switches the current user and has no setting to publish on its own.

On top of the editor's permissions: the data policy decides which content may go to which provider
([data-policy.md](data-policy.md)), and the write gate which models may write drafts at all ([probe.md](probe.md)).
The MCP bridge runs expmcp's audited read tools as the editor too; an external MCP server sees only its own token.

## Setting up roles

Give `expai/write` to your editors' role (Setup > Roles and policies > role > New policy > module `expai`, function
`write`). Administrator has every policy already. Anonymous users and members without the policy see nothing of
expai.

## Vector: expai/vector

`expai/vector` opens Vector (`expai/vector`) and its functions (`expaivector::bootstrap`, `thread_save`, `turn`,
`stop`, `followup`); `expai/write` is still needed. Limitations, per policy (with several policies, what any one of
them allows):

| Limitation | Values | Effect |
| --- | --- | --- |
| `Subtree` | subtrees (the role editor browses for them) | every node and subtree of a thread's scope must lie inside one of them; a scope of classes or languages alone is refused |
| `Section` | sections | every node and subtree root of the scope must be in one of them |
| `Scope` | `node`, `subtree`, `class` | the kinds of scope entries the user may pick (languages are always allowed) |
| `Review` | `staged`, `direct` | the review policies the user may choose; without `direct` every Vector thread is staged |

The scope is checked when it is saved and again at every run, together with the user's read access to every node.
Writing still goes through the editor's own content/edit and content/create, as everywhere in expai: staging a change
checks them ([review.md](review.md)), and applying staged changes checks them again per object and language, so an
editor who lost the edit permission meanwhile cannot write them. Only the owner of a thread decides on its staged
changes and approves its plans ([plan-mode.md](plan-mode.md)); approvers who may open the thread see them read-only.

**Threads are private** to their owner; the approvers of a version a thread wrote may open it read only
([threads.md](threads.md#who-sees-a-thread)). No policy shows another editor's threads.

Example: an editor role for one site section, node work only, always reviewed:

    module expai, function vector: Subtree /1/2/58/, Scope node, Review staged
