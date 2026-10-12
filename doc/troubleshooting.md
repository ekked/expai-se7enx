# Troubleshooting

Each entry was met while building and testing 0.1.0. The help pages of the administration interface
(`expai/help`) show this page too.

## The Intelligence tab is missing

- The user lacks `expai/write`, or `[AISettings] Enabled` is not `true`/`enabled`: the header template draws no tab.
- expai is not active in this siteaccess (`ActiveAccessExtensions[]=expai` in the siteaccess's own site.ini).
- The siteaccess's `admininterface.ini.append.php` empties `AdditionalTabs[]` and lists its tabs: add
  `AdditionalTabs[]=expai` and `AdditionalTabs[]=expai_media` there (INSTALL.md, step 3).
- The content is neither in a content nor a media section (the tabs belong to `ezcontentnavigationpart` and
  `ezmedianavigationpart`).
- Settings were changed but the caches are old: clear the INI cache and the template caches (below).

## `/(tab)/expai` in the address opens the View tab

The admin designs append extension tabs to their list of valid tabs as one nested array, so the server never selects
an extension tab from the address. Click the tab, or use the address with `#exp-ai` at the end (expai.js opens the
tab then). This affects every extension tab, not only expai's.

## The panel is cut off at the bottom of the tab box

The admin designs give the tab box a fixed, resizable height. expai.css lets it grow while the Intelligence tab is
open. If a design override changes `#window-controls` or `div.tab-content`, make sure expai.css (BackendCSSFileList)
still loads after it.

## A changed setting has no effect

- config.php may switch off the INI modification check: clear the INI cache: `php bin/php/ezcache.php --clear-tag=ini`.
- The templates read expai.ini when they are compiled: clear
  `--clear-id=template,template-override,content,template-block`.
- A new BackendJavaScriptList or BackendCSSFileList entry needs `--clear-id=ezjscore-packer,template-block`.
- Exponential Velocity keeps loaded classes and settings: `exp:velocity restart` or `./console exp:velocity deploy --packer`.
- A running conversation keeps the instructions and tools it started with: start a new conversation.

## "Exponential Intelligence is not configured correctly."

`Enabled=true` but `Model` or `ApiKey` is empty in the siteaccess's settings (the key belongs in
settings/override/expai.ini.append.php). The dashboard's Status says "Switched on, but not configured".

## "Exponential Intelligence refused the request (access)."

HTTP 401/403 from the API: the key is wrong or revoked, or a user key needs `AnthropicWorkspaceId`. The debug log has
the API's error type.

## "Exponential Intelligence reported an error." (HTTP 400, thinking block bound to a different conversation)

The API binds the model's reasoning to the exact start of a conversation. expai keeps each conversation's instructions
and tools for that reason, and for conversations stored before that it retries once letting the API drop the old
blocks. If it still happens, **New conversation** helps; the drafts stay.

## "Exponential Intelligence could not be reached." / "... answered instead: ... is stopped."

The model's provider did not take the connection: a local server (`Managed=ollama`) that is stopped, a wrong
`BaseURL`, a host that does not resolve, a TLS problem, a firewall. The usage log row (expai/log) has the curl error
(`curl error 7`: connection refused, `6`: host not resolved, `35`/`60`: TLS). With
`[AISettings] FallbackToDefaultModel=enabled` (the default) the editor does not see this error when another model can
answer: the site default answers and the panel says "<default> answered instead: <model> could not be reached / is
stopped" ([providers.md](providers.md), "When a model cannot answer"); the log row of the answer names the requested
model. The error remains when the failing model is the site default itself, when fallback is disabled, and when no
other allowed model may take the content ("... and no other model you may use can answer for this content.").

For a stopped local model: start it under expai/models (or choose another model; the pickers show it as "stopped").
A conversation that was bound to it goes on with the default and returns to the local model once it runs again.

## "Exponential Intelligence did not answer in time" / "The server did not answer in time"

One model call took longer than `[AISettings] Timeout`, or the web server cut the request. Keep Timeout below the
web server's and proxy's timeouts (architecture.md, "Long turns"); ask for less per message (two articles rather than
ten). **Continue** resumes from what was stored.

## 504 Gateway Timeout, or "The model ... is too slow for field actions here"

A web server ends a request after its own limit (Velocity after its request timeout, Apache after its proxy timeout)
and the browser then gets a 504 instead of an answer. A local model can need a minute to load and writes a few tokens
per second, so a call to it from a page request does not fit. expai therefore never makes such a call from a web
request (`[WebBudget]`, [configuration.md](configuration.md)):

- **Test prompt** and **Load model** on expai/models run as jobs; the page shows "Testing…" and then the result
  ([local-models.md](local-models.md), "Test prompt and Load model"). If "Testing…" stays at "waiting for a worker",
  no worker runs: see "Queued work never starts" below.
- **Turns** in the Intelligence tab and in Vector run in the background for slow models ([vector.md](vector.md)).
- **Field actions**, presets and the fill of the edit form refuse a slow model with "The model ... is too slow for
  field actions here (more than ... seconds per request)": choose another model in the model menu of the edit form,
  or use the Intelligence tab. A model counts as slow when it is marked `Slow=true`, belongs to a provider with
  `Managed=ollama`, or took longer than `[WebBudget] Seconds` on average over its last requests. Behind a web server
  with a long timeout, `[WebBudget] SlowModels=allow` (or `[Model_<alias>] RunInBackground=never`) lets it through.

## "You do not have access to Exponential Intelligence." / an ezjscore access error

The user lacks `expai/write`, or `ezjscore/call` (unlimited, or with the function list `expai`).

## "The form token is missing or wrong. Please reload the page."

The session changed (logged out and in elsewhere). Reload the page.

## The expai tables are not created / `--check` says missing

`php extension/expai/bin/php/expaischema.php --install -s <siteaccess>` uses the siteaccess's database. Run it for the
siteaccess whose database the admin uses, as the web server's user (or with `--allow-root-user`). It reports each
statement that failed.

## "Undefined group: 'ExpAiStartWriting' in browse.ini" (Change location returns to the dashboard)

The extension's browse.ini is not read yet: clear the INI cache.

## Another extension's tool, field action or section does not appear

It must be active where expai is active, its classes in the autoload array (`php bin/php/ezpgenerateautoloads.php -e`)
and the caches cleared. A list emptied somewhere (`Tools[]` alone on a line in a later settings file) drops entries
of extensions read before it.

## A field action fills nothing into a rich text field

expai.js writes through Tiptap's or TinyMCE's API when one is mounted on the field's textarea, else into the textarea.
A custom editor needs a field adapter (`ExpAi.fieldAdapters.register( 'ezxmltext', ... )`, extending.md).

## No label in a list

The label needs `ezjscore/call` for the viewer, `[LabelSettings] Enabled`, and links to `content/versionview/<id>/<v>`,
`content/edit/<id>/<v>` or `collaboration/item/full/<id>`. Only versions in expai's log are labelled.

## "Send for approval" leaves the version pending

That is the approval: the approver decides in the collaboration inbox, and the workflow cronjob (`runcronjobs.php`
part `frequent`, which runs `workflow.php`) continues the workflow afterwards. Without the cronjob an approved version
stays pending.

## Scripts say "Running scripts as root may be dangerous"

Run them as the web server's user, or add `--allow-root-user`.

## PHP deprecation notices about nullable parameters

PHP 8.4 deprecates implicitly nullable typed parameters; expai 0.1.0 declares them explicitly (`?expAiConfig`).
Notices from an older copy go away with the update.

## The dashboard shows no usage, and the debug output says "a query on expai_usage_log failed"

The table is missing or older than the code (a column such as `provider`, `session_key` or `rate_limits` is absent).
The pages then show empty figures instead of an error. Compare the table with `bin/php/expaischema.php
--print=<engine>` and add what is missing, then reload. When updating, add columns before deploying code that uses
them.

## A cost shows "≈", or no cost at all

"≈": the model has no row in `[Pricing]` and was priced with the `Default*` prices; add its prices (configuration.md).
No cost anywhere: `[Pricing] Enabled=false`. Run `php bin/php/ezexec.php extension/expai/tests/expaicost_test.php` after
changing the table.

## "The limit ... is reached (... of ...). It resets ..."

A limit of `[Limits]` was reached; the message names it and says when it resets. The dashboard shows each limit as a
bar. Raise or empty the setting, or wait for the reset.

## An old link with `?period=`, `?scope=` or `?from=` shows the defaults

expai's pages read view parameters only: `expai/dashboard/(period)/30d`, `expai/log/(from)/2026-10-01`. Query strings
are ignored. See dashboard.md.

## Queued work never starts (chains, batches, alt text suggestions, background runs stay "queued")

- The cron part of expqueue (or its systemd worker) does not run, or runs without `-s <admin siteaccess>` while expai
  is an access extension of the admin siteaccess only: the worker then does not know expai's job types. Run
  `php runcronjobs.php -q -s <admin siteaccess> expqueue` every minute ([../INSTALL.md](../INSTALL.md), "Cron parts
  and the job queue").
- A budget holds the jobs: expai/budgets shows them as held, with the reason; the cron part `expai` (also with
  `-s <admin siteaccess>`) resumes them once they fit ([budgets.md](budgets.md)).
- The master switch is off for the siteaccess the jobs were queued from: they are held with "switched off: ..."
  ([switch-and-veto.md](switch-and-veto.md)).

## Vector and the Intelligence tab update only every few seconds

The live stream (`expai/stream`) does not reach the browser, so it polls instead. Usually a buffer in between:

- Apache's FastCGI worker without `flushpackets=on`, or a handler of the hosting panel that wins over the vhost's
  (set the handler inside the `<Directory>` of the document root, [../INSTALL.md](../INSTALL.md), "Streaming and
  long requests");
- a proxy or CDN that buffers or compresses `text/event-stream` (expai sends `X-Accel-Buffering: no` for nginx);
- `[VectorSettings] Transport=poll`, or more open streams than `MaxStreams` (the answer is then 204 and the browser
  polls).

The browser's developer tools show the stream request: it should answer `200` with `text/event-stream` and a first
line `: ok` at once.

## The site stops answering after the proxy to PHP-FPM was changed

`enablereuse=on` on the FastCGI worker keeps one PHP-FPM child per idle Apache thread; with a small pool all
children are held and new requests wait. Remove `enablereuse` (keep `flushpackets=on`) and reload Apache.

## A help page says "not found"

`expai/help/(page)/<name>` knows the extension's own documents only: `readme`, `install`, `usage`, `index`, the name
of a `doc/*.md` without `.md` (`providers`, `vector`, `summary` ...) and `changelog-<version>`. The pages are read
from the installed extension, so they always match its version.
