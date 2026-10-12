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

All menu entries of expai (settings/menu.ini.append.php), each shown only with its policies:

| Menu | Entry | View | Policies |
| --- | --- | --- | --- |
| Dashboard | Exponential Intelligence | `expai/dashboard` | `expai/write` |
| Dashboard | EI: Vector | `expai/vector` | `expai/write`, `expai/vector` |
| Dashboard | EI: Chains | `expai/chain` | `expai/write`, `expai/chain` |
| Dashboard | EI: Log | `expai/log` | `expai/write` |
| Dashboard | EI: Help | `expai/help` | `expai/write` |
| Design | Missing alt text | `expai/alttext` | `expai/write` |
| Setup | EI: Summary | `expai/summary` | `expai/facts` |
| Setup | EI: Models | `expai/models` | `expai/model_manage` |
| Setup | EI: Budgets | `expai/budgets` | `expai/log` |
| Setup | Job queue | `expqueue/dashboard` | `expqueue/view` |
| Setup | Content packages | `expai/packages` | `expai/package_import` |

Job queue is the page of the extension expqueue; expai lists it right after EI: Budgets. Missing alt text is in the
left menu of the Design tab: `expai/alttext` uses the navigation part `ezvisualnavigationpart`, so the Design tab
stays active. The Setup entries are translated in `design/admin/parts/setup/menu`, the Design entry in
`design/admin/parts/design/menu`. Every expai page also has a "?" link to its help page (`expai/help/(page)/<name>`),
and the dashboard's **Details** link to the models page (with `expai/model_manage`), the "EI: Summary" page
(`expai/summary`, with `expai/facts`), the "Missing alt text" report and the help.

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
- **Watch this run**: after Write (or a location chosen with change), the dashboard remembers the location and the
  time (session variable `ExpAiWatch`, six hours) and shows a line at the top of the card: a state chip (Not sent yet
  until the editor presses Send in the Intelligence tab, then the thread's state: Queued, Working, Waits for you, To
  review, Done, Failed, Stopped) and the links to the run's live record: **Watch this run →** (the thread in Vector,
  with `expai/vector`; else the Intelligence tab), the Intelligence tab, **Log** (`expai/log/(thread)/<id>`) and, for a
  run in the background, **Job** (`expqueue/job/<uuid>`, with `expqueue/view`). While the run waits or works,
  expai_watch.js asks `expaiwatch::state` (ezjscore, POST with the form token, `expai/write`) every four seconds, not
  while the browser tab is hidden, and the line follows the run without a reload.

### The page, top to bottom

- **Header**: the title, the period (Today, 7d, 30d, Month) and, with `expai/log`, Mine / Site.
- **Status line**: Ready / Switched off / Not configured, the model in use, whether an approval workflow runs before
  publishing. **Details** opens: the model choice (when `AllowUserModelChoice` is on), whether the user may create
  and edit content (everywhere, in some places, nowhere: the access words of `content/create` and `content/edit`),
  with `expai/settings` where the site default model is set, the price table's date, and a note on the organization's
  spend limit at Anthropic (visible in the Claude Console only). The key is never shown.
- **Four figures** for the period: estimated cost (hidden with `[Pricing] Enabled=false`), tokens (abbreviated, with
  input · output · cached below and the exact numbers as a tooltip), requests (average time, errors), drafts (how
  many were sent or published). Each shows ▲ / ▼ and the change in percent when the period before had data, and
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
- **Models**: the track record of each model in the period and scope of the page (Mine, or Site with `expai/log`),
  one row per model, most requests first (see "Models" below).
- **Recent drafts**: the user's last five versions written by Exponential Intelligence with state and the icon links
  (View, Edit, Prompt again, Intelligence tab; "deleted" or "in trash" when the object is gone), and the link to all
  of them in the log.

Amounts and numbers use the user's locale (`expai_money`, `expai_tokens`, `expai_number` template operators); an
amount under one cent shows as "< $0.01", one priced with the fallback prices as "≈".

### Models

One row per model that had requests in the period and scope (Mine / Site), most requests first
(`expAiModelStats::forPeriod()`, from `expai_usage_log`; the same rows as expai/log):

- **Model**: the label, the provider and region as badges, the alias; **slow: ~N s per step** when the median request
  took longer than `[WebBudget] Seconds` (the time a web request may spend on one model call, 25 s by default). The
  label opens the model's requests in the log for the same period (and user): `expai/log/(model)/<alias>/(from)/…/(to)/…`.
  A row of an older request without a model alias is listed by its model id.
- **Requests**: how many, the success rate (never shown as 100 % while one failed), the errors by kind with a link to
  the failed ones only (`…/(errors)/1`): timeout (no answer within the time limit), unreachable (the connection
  failed), HTTP error (the provider answered with an error status), refused (a rule of the site, see
  switch-and-veto.md, or the model itself), limit (a usage limit of `[Limits]` or the provider's rate limit), budget (a
  budget, or the web budget), other. **Replaced by the fallback N times** counts the requests that asked for this
  model and were answered by the site default instead (`requested_model_alias`, by `fallback_reason`); the model that
  answered says "answered N for another model".
- **Latency**: the median of the successful requests, and the 95th percentile.
- **Speed**: the median output speed (output tokens per second of a request) and the median time per turn (all
  requests of one panel message, field action or thread run added up: what the editor waited).
- **Cost**: the estimated cost (hidden with `[Pricing] Enabled=false`; "unknown" when a price is missing).
- **Per day**: the median latency per day as a small bar row (red for a slow model); the numbers are in each bar's
  tooltip and in a text for screen readers. Not shown for Today.
- **Test and probe**: the newest result of **Test prompt** on expai/models (time to the first token where the
  provider reports it, tokens per second, the total; links to the request in the log and to the job) and the newest
  probe (bin/php/expaiprobe.php: pass rate, passed of tasks, date; red below 80 %).

The figures of a period and scope are computed once per request and kept for `[ModelStats] CacheSeconds` (60 by
default) in `var/<site>/cache/expai/model-stats/`, per siteaccess; the card then costs a file read (about 0.3 ms
measured, 50 to 90 ms computed with a few hundred requests). The existing index on `expai_usage_log.created` serves the
query; no other index is needed.

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
expai/log/(model)/local.qwen3.5-4b/(errors)/1    a model alias of the registry filters by alias, anything else by model id
expai/log/(request)/802                          one request (the test prompt's result links here)
expai/log/(thread)/151                           the requests of one thread ("Watch this run" links here)
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
`local`. `user` is honored only with `expai/log`.
