# Installing expai (Exponential Intelligence)

All commands run from the installation root as the web server's user. When you run them as root, add
`--allow-root-user`, and afterwards make sure the files they wrote under `var/` belong to the web server's user.
The short version is the [checklist](#checklist-install-and-upgrade) at the end.

## 1. Requirements

- Exponential 6.0.14 or later (the legacy kernel), PHP 8.1 or later with `curl`, `dom`, `json` and `mbstring`. The
  documentation in the administration (EI: Help, the "?" links) needs the help center of Exponential 6.0.15.
- The extensions **ezjscore** (the browser talks to expai through `ezjscore/call`) and, recommended, **ezformtoken**
  (every expai request carries the form token).
- For queued work the extension **expqueue** (0.0.1.0 or later): lanes and provider batches, budgets, AI chains,
  translation sync, the "Missing alt text" report, large package imports and background runs of Vector. Without it
  the panel, the edit form, Vector's direct runs and the dashboard work; the queued features say that they need it.
- Optional: **expmcp** 0.3.1 or later for the in-process MCP bridge ([doc/mcp.md](doc/mcp.md)), **expservices** for
  service tools ([doc/service-tools.md](doc/service-tools.md)), **xrowmetadata** for the metadata presets
  ([doc/presets.md](doc/presets.md)).
- At least one model: an API key of a hosted provider (Anthropic: https://console.anthropic.com/; or any provider of
  [doc/providers.md](doc/providers.md)) with outbound HTTPS to it, or a local model ([doc/local-models.md](doc/local-models.md)).
- A database engine the kernel supports: MySQL/MariaDB, PostgreSQL, SQLite, Oracle (ezoracle) or MongoDB.

## 2. Put the extension in place

```
extension/expai/
```

With Composer: `composer require se7enxweb/expai` (the package type `ezpublish-legacy-extension` puts it there). Or
clone https://github.com/se7enxweb/expai into `extension/expai`.

## 3. Activate it

expai belongs to the administration interface. Activate it either only for the admin siteaccess(es) or for every
siteaccess.

**Only for the admin siteaccesses (recommended).** In the `site.ini.append.php` of **each** admin siteaccess:

```ini
[ExtensionSettings]
ActiveAccessExtensions[]=expai
```

Each siteaccess needs its own line: `ActiveAccessExtensions[]` is read from the siteaccess's own settings, so a line
in a shared file (for example a file that `[ExtensionSettings] ExtensionSettingsSiteAccess` points at) does not
activate it for the other siteaccesses. Because expai is then active only in those siteaccesses, every script and
cron line that needs expai must name one of them with `-s <admin siteaccess>` (sections 5 and 8).

**For every siteaccess.** In `settings/override/site.ini.append.php`:

```ini
[ExtensionSettings]
ActiveExtensions[]=expai
```

**A siteaccess with its own tab list.** If a siteaccess's `admininterface.ini.append.php` empties
`[WindowControlsSettings] AdditionalTabs[]` and lists its tabs itself, add expai's two tabs to that list, or the
Intelligence tab does not appear there:

```ini
[WindowControlsSettings]
AdditionalTabs[]=expai
AdditionalTabs[]=expai_media
```

(The same applies to any other expai list a siteaccess or another extension empties, see
[doc/configuration.md](doc/configuration.md).)

## 4. Keys (never committed)

Create `settings/override/expai.ini.append.php`, readable only by the web server's user and group (mode 0640), and
keep it out of version control:

```ini
<?php /* #?ini charset="utf-8"?

[AISettings]
Enabled=true
ApiKey=<your Anthropic API key>
# Only for a key that is not scoped to a workspace:
#AnthropicWorkspaceId=<workspace id>

# Other providers: list them and give each its key here, in the override file only
#[ProviderSettings]
#Providers[]=openrouter
#[Provider_openrouter]
#ApiKey=<key>
#TermsCheckedAt=<the day you read the provider's terms>

*/ ?>
```

Keys and tokens (providers, external MCP servers, the web search service) belong in `settings/override` only. They
are read on the server only: never sent to the browser, never written to a log, never shown on a page; `expai/models`
and the "EI: Summary" page say only whether a key is set and in which file, and warn about a key in any other file or in
a file every user of the server can read. Every other setting is in [doc/configuration.md](doc/configuration.md);
regions, retention and the data policy in [doc/data-policy.md](doc/data-policy.md).

## 5. Create the tables

expai keeps 15 tables: the draft log, the conversations, the usage log, the probe results, provider batches, content
packages, seven tables of threads and Vector, and two of AI chains ([doc/database.md](doc/database.md)). Create them
in the database of the admin siteaccess, on any engine:

```
php extension/expai/bin/php/expaischema.php --install -s <admin siteaccess>
```

It is idempotent (a second run creates nothing), uses the kernel's schema handler of the database's engine, and is
also the upgrade: for tables that exist it adds missing columns and indexes, it moves panel conversations into
threads, and it gives role policies of pre-release builds the current function names (with their limitations).
`--check` only reports. When several siteaccesses use different databases, run it once per database. To create the
tables by hand instead, use the file of your engine:

| Engine | File |
| --- | --- |
| MySQL / MariaDB | `sql/mysql/schema.sql` |
| PostgreSQL | `sql/postgresql/schema.sql` |
| SQLite | `sql/sqlite/schema.sql` |
| Oracle | `sql/oracle/schema.sql` (run in SQL*Plus; the `/` lines end the PL/SQL blocks) |
| MongoDB | `--install` (the kernel's MongoDB handler creates the collections); `sql/mongodb/schema.json` lists them |

`share/db_schema.dba` holds the same schema for installers and packages.

## 6. Roles and policies

| Policy | Gives |
| --- | --- |
| `expai/write` | the Intelligence tab, the edit form controls, presets and alt text, the dashboard page and block, one's own log, the help pages |
| `expai/vector` | Vector (limitations Subtree, Section, Scope, Review), with `expai/write` |
| `expai/chain` | AI chains (limitation Chain), with `expai/write` |
| `expai/translation_sync` | Check, Approve, Translate in full and Skip of translation updates, with `expai/write` |
| `expai/web` | the web and history tools (limitation Tool) |
| `expai/services` | service tools (limitation Domain) |
| `expai/mcp` | MCP tools (limitation Server: `exp` for the expmcp bridge, or an external server id) |
| `expai/model_use` | models of providers outside `ProvidersWithoutPolicy[]` (limitation Provider) |
| `expai/lane_fast` | queued requests in a fast lane (a price premium) |
| `expai/log` | everyone's requests and versions, the site view of the dashboard, budgets and lanes |
| `expai/settings` | where the site default model is set; "Resume held jobs" |
| `expai/model_manage` | `expai/models`: providers, probe results, the local model |
| `expai/summary` | the "EI: Summary" page `expai/summary` (needs nothing else; also opens the help pages) |
| `ezjscore/call` | needed for every browser request of expai; unlimited, or limited to the function list `expai` |

**A recommended Editor set** (Setup > Roles and policies > Editor > New policy):

- `expai/write`;
- `expai/vector` with the limitation Review `staged` (every Vector change is reviewed before it is written), and
  Subtree or Section where the role is limited to a part of the site;
- `expai/chain` and `expai/translation_sync` where editors use chains and translation sync;
- `ezjscore/call` with the function list `expai` (many installations give `ezjscore/call` to all logged-in users
  through the Member role already).

Leave `expai/web`, `expai/mcp`, `expai/services`, `expai/model_use` and `expai/lane_fast` off until you have
configured what they open and checked it against your data policy. Give `expai/log`, `expai/settings`,
`expai/model_manage` and the package policies to administrators (the Administrator role holds every policy already),
and `expai/summary` alone to a data protection officer's role. What the assistant may then do in the content tree is
decided by the editor's own content policies ([doc/permissions.md](doc/permissions.md)).

## 7. Autoloads, caches, web server

```
php bin/php/ezpgenerateautoloads.php -e
php bin/php/ezcache.php --clear-tag=ini
php bin/php/ezcache.php --clear-id=template,template-override,translation,content,ezjscore-packer,template-block
```

Then reload PHP-FPM so it reads the new classes and settings. The INI and template caches matter: the templates read
expai.ini when they are compiled, and the packed script and style bundles list expai's scripts and styles.

**Exponential Velocity.** Velocity's workers keep the classes and settings they loaded. After installing or updating
expai, restart it (`./console exp:velocity restart`), or use the deploy, which does all of the above in order:

```
./console exp:velocity deploy --packer
```

Velocity's pool workers stream Vector's events; past its stream cap the answer is 503 and the browser polls.

## 8. Cron parts and the job queue

expai's cron part `expai` and expqueue's part `expqueue` run with the siteaccess's settings, so when expai is an
access extension of the admin siteaccess only, **both need `-s <admin siteaccess>`**: without it the scripts do not
see expai's settings, its job types (`expai.request`, `expai.thread.turn`, `expai.alttext` ...) are unknown to the
worker and queued work never runs.

```
# every minute: the job queue worker slice and its maintenance (extension expqueue)
* * * * *   cd <installation> && php runcronjobs.php -q -s <admin siteaccess> expqueue
# every few minutes: resume jobs a budget held, release requests of a lost batch submit, prune usage rows and thread events
*/5 * * * * cd <installation> && php runcronjobs.php -q -s <admin siteaccess> expai
```

The part `expai` runs `expai_queue.php` (held jobs, lost batch submits), `expai_prune.php` (usage rows older than
`[UsageSettings] RetentionDays`) and `expai_thread_prune.php` (thread events, proposals and checkpoints older than
`[ThreadSettings] *KeepDays`). The approval workflow needs the kernel's `frequent` part as usual (it continues
workflows after the approver decided). A systemd worker of expqueue can replace the per-minute cron line; it needs the
same siteaccess.

## 9. Streaming and long requests (Apache, PHP-FPM, nginx)

**Long requests.** One request of the panel is one call to the model (the browser makes the next one when the model
wants a tool), so each request stays within the provider's `Timeout` (115 s by default for hosted providers, longer
for a local model). Keep that below the web server's and PHP's limits for a request: Apache `Timeout`, a proxy's read
timeout (nginx `proxy_read_timeout`), PHP-FPM's `request_terminate_timeout` and `max_execution_time`. Slow models run
their turns in the job queue instead ([doc/vector.md](doc/vector.md)). See [doc/architecture.md](doc/architecture.md),
"Long turns".

**Server-Sent Events.** Vector and the Intelligence tab follow running threads live through `expai/stream`
(`[VectorSettings] Transport=auto`); without a working stream the browser falls back to polling after 2 seconds, so
nothing breaks, but events arrive later. For the stream to reach the browser as it happens:

- **Apache with mod_proxy_fcgi**: the FastCGI worker must flush every packet. Set `flushpackets=on` on the worker
  that the PHP handler really uses, and set that handler **inside the `<Directory>` of the document root**: a hosting
  panel's own handler in a `<Files>` nested in its `<Directory>` is applied after a vhost-level one and would win
  (with its own worker, without flushpackets):

  ```apache
  <Directory /path/to/the/installation>
      <IfModule mod_proxy_fcgi.c>
          <Files ~ (\.php$)>
              SetHandler "proxy:unix:/path/to/php-fpm.sock|fcgi://exp-site/"
          </Files>
      </IfModule>
  </Directory>
  <IfModule mod_proxy_fcgi.c>
      <Proxy "fcgi://exp-site/">
          ProxySet flushpackets=on
      </Proxy>
  </IfModule>
  ```

- **Never set `enablereuse=on`** on that worker when the PHP-FPM pool is small: every idle Apache thread then keeps
  a PHP-FPM child bound to its connection, and once all children are held the site stops answering.
- **nginx or another proxy** in front: expai sends `X-Accel-Buffering: no` with the stream, which nginx honors; turn
  off response buffering and compression for `expai/stream` in any other proxy or CDN.
- **Output compression**: `mod_deflate` or `zlib.output_compression` must not buffer `text/event-stream` answers.
- **Pool size**: each open stream holds one PHP process for at most `StreamSeconds` (25). Keep
  `[VectorSettings] MaxStreams` at no more than a quarter of the pool's `pm.max_children`; over it the answer is 204
  and the browser polls.
- A graceful reload of PHP-FPM lets running requests finish within `process_control_timeout`; set it to a few
  minutes so a reload does not cut a model call.

## 10. Check that it works

```
php extension/expai/bin/php/expaischema.php --check -s <admin siteaccess>        # every table, column and index present
php bin/php/ezexec.php extension/expai/tests/expaiprovider_test.php -s <admin siteaccess>   # the provider layer, no network
php bin/php/ezexec.php extension/expai/tests/expaicost_test.php -s <admin siteaccess>       # the price table
php vendor/bin/phpunit -c extension/expai/phpunit.xml.dist --exclude-group e2e             # the test suite (doc/testing.md)
php extension/expai/bin/php/expaiprobe.php --list -s <admin siteaccess>                    # the models and their probe state
php runcronjobs.php -s <admin siteaccess> expai                                             # the cron part runs without errors
```

Then in the browser:

1. As an editor, open a folder in the admin: the **Intelligence** tab is there. Ask "Which classes may I create
   here?". The panel shows a step "Checked which content you may create here" and an answer.
2. Ask for a short article. A draft appears in the panel and in My drafts, with the "Exponential Intelligence" label.
3. Open it in the edit form: the field actions, the presets and the Improve menu are next to the text fields.
4. The dashboard (Dashboard > Exponential Intelligence) says "Ready"; Setup > EI: Summary lists your providers; the "?"
   link of each page opens its help.

If something does not work, see [doc/troubleshooting.md](doc/troubleshooting.md).

## Checklist: install and upgrade

Install:

- [ ] Requirements met (kernel 6.0.14+, PHP 8.1+, ezjscore, ezformtoken; expqueue for queued work)
- [ ] `extension/expai` in place (Composer or clone)
- [ ] `ActiveAccessExtensions[]=expai` in the `site.ini.append.php` of **each** admin siteaccess (or `ActiveExtensions[]`)
- [ ] `AdditionalTabs[]=expai` and `expai_media` where a siteaccess lists its own tabs
- [ ] Keys only in `settings/override/expai.ini.append.php` (mode 0640, not in version control); `Enabled=true`
- [ ] Providers' `Region`, `Retention` and `TermsCheckedAt` set; data policy rules for sensitive sections and classes
- [ ] `php extension/expai/bin/php/expaischema.php --install -s <admin siteaccess>` on each database
- [ ] Policies: `expai/write` (+ the Editor set of section 6) for editors, `ezjscore/call` (function list `expai`)
- [ ] Autoloads (`ezpgenerateautoloads.php -e`), INI and template caches, PHP-FPM reload; Velocity restart or deploy
- [ ] Cron: `expqueue` every minute and `expai` every few minutes, both with `-s <admin siteaccess>`
- [ ] Streaming: `flushpackets=on` on the handler inside `<Directory>`, no `enablereuse` on a small pool, no
      buffering or compression of `text/event-stream` in proxies
- [ ] Checks of section 10 pass

Upgrade (a newer expai over an older one):

- [ ] Read the changelog of the new version (`doc/changelog/`)
- [ ] Update the files (Composer or `git pull`), keep `settings/override/expai.ini.append.php`
- [ ] Run `expaischema.php --install -s <admin siteaccess>` on each database **before** the new code serves requests
      (it adds new tables, columns and indexes and renames role policies of pre-release builds; `--check` first
      shows what is missing)
- [ ] Compare your override with the new `settings/expai.ini.append.php` for new groups (new providers, lanes, presets)
- [ ] Autoloads, INI and template caches, packed scripts and styles (`ezjscore-packer`, `template-block`), PHP-FPM
      reload; Velocity restart or `./console exp:velocity deploy --packer`
- [ ] New cron parts or policies of the version given to the right roles
- [ ] Checks of section 10 pass
