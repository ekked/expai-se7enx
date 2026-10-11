# Installing expai (Exponential Intelligence)

All commands run from the installation root as the web server's user. When you run them as root, add
`--allow-root-user`, and afterwards make sure the files they wrote under `var/` belong to the web server's user.

## 1. Requirements

- Exponential 6.0.14 or later (the legacy kernel), PHP 8.0 or later with `curl`, `dom`, `json` and `mbstring`.
- The extensions **ezjscore** (the browser talks to expai through `ezjscore/call`) and, recommended, **ezformtoken**
  (every expai request carries the form token).
- An Anthropic account and an API key (https://console.anthropic.com/). The server needs outbound HTTPS to
  `api.anthropic.com`.
- A database engine the kernel supports: MySQL/MariaDB, PostgreSQL, SQLite, Oracle (ezoracle) or MongoDB.

## 2. Put the extension in place

```
extension/expai/
```

With Composer, once the package is published: `composer require se7enxweb/expai` (the package type
`ezpublish-legacy-extension` puts it there). Or clone https://github.com/se7enxweb/expai into `extension/expai`.

## 3. Activate it

expai belongs to the administration interface. Activate it either for every siteaccess or only for the admin
siteaccess(es).

**Only for the admin siteaccesses (recommended).** In the `site.ini.append.php` of **each** admin siteaccess:

```ini
[ExtensionSettings]
ActiveAccessExtensions[]=expai
```

Each siteaccess needs its own line: `ActiveAccessExtensions[]` is read from the siteaccess's own settings, so a line
in a shared file (for example a file that `[ExtensionSettings] ExtensionSettingsSiteAccess` points at) does not
activate it for the other siteaccesses.

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

(The same applies to any other expai list a siteaccess or another extension empties, see doc/configuration.md.)

## 4. The API key (never committed)

Create `settings/override/expai.ini.append.php`, readable only by the web server's user and group (mode 0640), and
keep it out of version control:

```ini
<?php /* #?ini charset="utf-8"?

[AISettings]
Enabled=true
ApiKey=<your Anthropic API key>
# Only for a key that is not scoped to a workspace:
#AnthropicWorkspaceId=<workspace id>

*/ ?>
```

The key is only ever read on the server: it is not sent to the browser, not written to any log and not shown on any
page (the dashboard says only whether a key is set). Every other setting is in doc/configuration.md.

## 5. Create the tables

expai keeps two tables: `expai_draft_log` (what it wrote, for the label and the dashboard) and `expai_conversation`
(the conversations). Create them in the database of the admin siteaccess:

```
php extension/expai/bin/php/expaischema.php --install -s <admin siteaccess>
```

It is idempotent (a second run creates nothing) and uses the kernel's schema handler of the database's engine.
`--check` only reports. To create them by hand instead, use the file of your engine:

| Engine | File |
| --- | --- |
| MySQL / MariaDB | `sql/mysql/schema.sql` |
| PostgreSQL | `sql/postgresql/schema.sql` |
| SQLite | `sql/sqlite/schema.sql` |
| Oracle | `sql/oracle/schema.sql` (run in SQL*Plus; the `/` lines end the PL/SQL blocks) |
| MongoDB | `--install` (the kernel's MongoDB handler creates the collections); `sql/mongodb/schema.json` lists them |

`share/db_schema.dba` holds the same schema for installers and packages. Details: doc/database.md.

## 6. Policies

| Policy | Gives |
| --- | --- |
| `expai/write` | the Intelligence tab, the edit form controls, the dashboard page and the dashboard block, the log of one's own drafts |
| `expai/log` | the log page of everyone's drafts (`expai/log/(user)/all`) |
| `ezjscore/call` | needed for every browser request of expai; unlimited, or limited to the function list `expai` |

Give `expai/write` to the roles of your editors (for example Editor) in Setup > Roles and policies > (role) > New
policy > module `expai`, function `write`. The Administrator role holds every policy already. What the assistant may
then do in the content tree is decided by the editor's own content policies (doc/permissions.md).

## 7. Autoloads, caches, web server

```
php bin/php/ezpgenerateautoloads.php -e
php bin/php/ezcache.php --clear-tag=ini
php bin/php/ezcache.php --clear-id=template,template-override,translation,content,ezjscore-packer,template-block
```

Then reload PHP-FPM so it reads the new settings. The INI and template caches matter: the templates read expai.ini
when they are compiled, and the packed script and style bundles list expai.js and expai.css.

**Exponential Velocity.** Velocity's workers keep the classes and settings they loaded. After installing or updating
expai, restart it (`./console exp:velocity restart`), or use the deploy, which does all of the above in order:

```
./console exp:velocity deploy --packer
```

## 8. Long requests

One request of the panel is one call to the model (the browser makes the next one when the model wants to use a
tool), so each request stays within `[AISettings] Timeout` (115 s by default). Keep that below the web server's and
PHP's limits for a request: Apache `Timeout`, a proxy's read timeout (for example nginx `proxy_read_timeout`) and
`max_execution_time` (which on Linux counts CPU time, not the time spent waiting for the model). See
doc/architecture.md, "Long turns".

## 9. Check that it works

1. As an editor, open a folder in the admin: the **Intelligence** tab is there. Ask "Which classes may I create here?".
   The panel shows a step "Checked which content you may create here" and an answer.
2. Ask for a short article. A draft appears in the panel and in My drafts, with the "Exponential Intelligence" label.
3. Open it in the edit form: the field actions are next to the text fields.
4. The dashboard (Dashboard tab > Exponential Intelligence) says "Ready".

If something does not work, see doc/troubleshooting.md.
