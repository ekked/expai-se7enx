# The dashboard page, the menu entry, the block and the log

## expai/dashboard

`expai/dashboard` is a page of the Dashboard tab: its view uses the navigation part `ezmynavigationpart` (the one of
`content/dashboard`), so the Dashboard tab stays active and the left menu of that tab shows it highlighted. It needs
`expai/write`.

The left menu of the Dashboard tab is built by `design:parts/my/menu.tpl` from menu.ini `[Leftmenu_my]` through
`parts/ini_menu.tpl`. expai adds its entry purely through settings:

```ini
[Leftmenu_my]
Links[expai]=expai/dashboard
LinkNames[expai]=Exponential Intelligence
PolicyList_expai[]=expai/write
```

`ini_menu.tpl` hides an entry whose `PolicyList_<key>[]` the user lacks (and, with `[MenuAccessSettings]
CheckViewAccess`, one the user cannot open), so users without `expai/write` never see it. The name is translated in
the menu's context `design/admin/parts/my/menu` (expai ships it).

### Start writing

The card preselects a location: `expai.ini [DashboardSettings] DefaultNodeID`, or, when empty, `content.ini
[NodeSettings] RootNode`. It shows the location's name and says that Exponential Intelligence works there and
anywhere below it, within the editor's own permissions.

- **Write** keeps the request in the session (`ExpAiPrefill`, for that node) and opens
  `content/view/full/<node>#exp-ai`; expai.js opens the Intelligence tab and `expai::load` fills the request into the
  message field once. The editor presses Send.
- **change** (next to the location) keeps the request and opens the admin's content browser (`eZContentBrowse`, browse.ini
  `[ExpAiStartWriting]`: one node); after the choice the same happens for the chosen node. Cancel returns to the
  dashboard.
- When the user may not read the default location or create anything below it, the card says so and offers only
  "Choose the location".

### The page, top to bottom

- **Header**: the title, the period (Today, 7d, 30d, Month) and, with `expai/log`, Mine / Site.
- **Status line**: Ready / Switched off / Not configured, the model in use, whether an approval workflow runs before
  publishing. **Details** opens: the model choice (when `AllowUserModelChoice` is on), whether the user may create
  and edit content (everywhere, in some places, nowhere: the access words of `content/create` and `content/edit`),
  with `expai/settings` where the site default model is set, the price table's date, and a note on Anthropic's
  organisation spend limit (visible in the Claude Console only). The key is never shown.
- **Four figures** for the period: estimated cost (hidden with `[Pricing] Enabled=false`), tokens (abbreviated, with
  input · output · cached below and the exact numbers as a tooltip), requests (average time, errors), drafts (how
  many were sent or published). Each shows ▲ / ▼ and the change in per cent when the period before had data, and
  nothing otherwise.
- **Start writing** (below).
- **Limits**: a bar for each limit of `[Limits]` that is set (site limits only with `expai/log`), with when it resets;
  "No limits configured." otherwise (administrators get a link to configuration.md). Below, the API rate limits the
  last response reported (`anthropic-ratelimit-*`), when there are any.
- **Waiting for my approval**: approval items (`ezapprove`) waiting for the user's decision whose version expai wrote,
  with the label. Only shown when there are some.
- **Top users this month** (Site view, `expai/log`): the five users with the most tokens, with a bar each.
- **Chart**: tokens or estimated cost per day (7 days, 30 days, or the month so far), today highlighted, empty days
  faint, a tooltip on every bar and the same numbers as a table for screen readers; "Details in the log" opens
  expai/log with the same period (and user).
- **Recent drafts**: the user's last five versions written by Exponential Intelligence with state and the icon links
  (View, Edit, Prompt again, Intelligence tab; "deleted" or "in trash" when the object is gone), and the link to all
  of them in the log.

Amounts and numbers use the user's locale (`expai_money`, `expai_tokens`, `expai_number` template operators); an
amount under one cent shows as "< $0.01", one priced with the fallback prices as "≈".

### View parameters

The page's choices are view parameters, never query strings, and each is kept as the user's preference
(`expai_period`, `expai_scope`, `expai_chart`), so the page opens the same way next time:

| Address | Effect |
| --- | --- |
| `expai/dashboard/(period)/30d` | `today`, `7d` (default), `30d`, `month` |
| `expai/dashboard/(scope)/site` | `me` (default) or `site` (with `expai/log`) |
| `expai/dashboard/(chart)/cost` | `tokens` (default) or `cost` |
| `expai/dashboard/(node)/2` | preselects that location for Start writing ("Prompt again") |
| `expai/dashboard/(node)/2/(again)/7` | also fills in the request of the user's own log entry 7 (only with `[UsageSettings] LogRequests=enabled`) |

They can be combined: `expai/dashboard/(period)/month/(scope)/site/(chart)/cost`. Values that do not fit are ignored.

## The dashboard block

dashboard.ini adds the block `expai_drafts` ("Exponential Intelligence: my drafts", template
`design:dashboard/expai_drafts.tpl`, 5 items, priority 35, policy `expai/write`) to `content/dashboard`. It uses the
fetch function `fetch( 'expai', 'my_drafts', hash( 'limit', 5 ) )`.

Fetch functions (modules/expai/function_definition.php), each empty for users without `expai/write`:

| Fetch | Result |
| --- | --- |
| `fetch( 'expai', 'my_drafts', hash( 'offset', 0, 'limit', 10 ) )` | the user's log rows (as on the dashboard page) |
| `fetch( 'expai', 'my_draft_count' )` | their number |
| `fetch( 'expai', 'waiting_for_my_approval' )` | AI-written versions waiting for the user's approval |

## expai/log

Two views: **Requests and usage** (`expai/log`) and **Versions written** (`expai/log/(view)/drafts`). Users with
`expai/write` see their own, with `expai/log` everyone's. See [usage-tracking.md](usage-tracking.md) for what is
recorded.

Requests are grouped into turns (one panel message, one field action, one "Fill the fields", with every request it
led to); a turn opens to its requests and tool calls. Above them: the totals of the filtered requests, and per day and
per user (`expai/log`) as tables. 25 turns per page.

Filters, sorting and paging are view parameters; the filter form posts and is redirected to the matching address:

```
expai/log/(from)/2026-10-01/(to)/2026-10-31/(user)/14/(node)/2/(action)/field:rewrite/(model)/claude-sonnet-5-5/(errors)/1
expai/log/(sort)/latency_ms/(dir)/asc/(offset)/25
expai/log/(export)/csv/(from)/2026-10-01        the filtered requests as CSV (no request texts, user ids only)
expai/log/(provider)/openrouter/(region)/eu      the requests of one provider, or of the providers of one region
```

With more than one provider every turn shows its provider and region, every request its model alias, region,
retention and tool mode (when not native), and the cost says "reported" when the provider reported it (OpenRouter).
The CSV has the columns `provider`, `model_alias`, `region`, `retention`, `reasoning_tokens`, `cost_source`
(`reported`, `alias`, `table`, `default`, or `unknown`), `tool_mode` and `mcp_calls`. The dashboard adds a
"By provider" table (requests, tokens, cost, errors per provider and its region) when more than one provider was used
in the period. The action filter also offers `probe` (bin/php/expaiprobe.php) and `test` (the test prompt of
expai/models).

`sort` is one of `created` (default), `latency_ms`, `input_tokens`, `output_tokens`; `dir` `desc` (default) or
`asc`; `action` one of `panel`, `fill`, `field:<name>`, `probe`, `test`; `provider` a provider id; `region` `us`, `eu` or
`local`. `user` is honoured only with `expai/log`.
