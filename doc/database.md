# Database

## The tables

**expai_draft_log**: one row per version expai wrote or filled.
The kernel reuses the number of a discarded version (an edit draft removed by the editor or the draft cleanup): a
row older than the `created` of the version that now has its number belongs to the removed version, and the label and
every lookup of the version (`expAiDraftLog::fetchByVersion()`, `fetchForVersions()`) ignore it. The prompt summary
is blanked after `[RetentionSettings] PromptSummaryKeepDays`.

| Column | Type | Meaning |
| --- | --- | --- |
| id | auto increment | |
| contentobject_id, version | int | the version |
| user_id | int | the editor (user object id) it was written for |
| node_id | int | the node of the panel (0 in the edit form of a never published object) |
| parent_node_id | int | the parent of a new object |
| conversation_id | int | the panel conversation, 0 for the edit form |
| language_code | varchar(20) | |
| action | varchar(20) | create, update, field, fill |
| source | varchar(20) | panel, edit (and vector, chain, queue, package, diff); `revert` for parts reverted into the same version from "What changed" ([review.md](review.md#direct-mode-what-changed-and-revert)) |
| status | varchar(20) | draft, kept, pending, published, discarded |
| model | varchar(100) | the model that answered |
| provider | varchar(40) | the provider id (default `anthropic`) |
| prompt_summary | varchar(255) | the start of the request |
| created, modified | int | Unix time |

Indexes: (contentobject_id, version), (user_id, status), (node_id).

**expai_usage_log**: one row per request to the model (see [usage-tracking.md](usage-tracking.md)).

| Column | Type | Meaning |
| --- | --- | --- |
| id | auto increment | |
| created | int | Unix time the request ended |
| user_id | int | the editor (user object id; no names are stored) |
| node_id | int | the node of the panel or the edit form (0 when none) |
| conversation_id, turn, iteration | int | the panel conversation (0 in the edit form), the editor's message in it, the model call within that message (1, 2, ...) |
| session_key | varchar(40) | a hash of the login session, for the per-session limit (never the session id itself) |
| action | varchar(40) | `panel`, `fill`, `field:<action>` |
| provider | varchar(40) | the provider id of the registry (`anthropic`, `openrouter`, `local` ...), or the `providerId()` of a custom provider |
| model_alias | varchar(100) | the model alias of the registry (empty for rows before it) |
| model | varchar(100) | the model that answered (as the API reported it) |
| region, retention | varchar(10) | the provider's `Region` and `Retention` at the time of the request |
| requested_model_alias | varchar(100) | the model the request asked for when another one answered (the fallback to the default model), else empty |
| fallback_reason | varchar(20) | why it did not: `stopped`, `unreachable`, `timeout`, `not_allowed`, `removed`, `data_policy` (empty otherwise) |
| tool_mode | varchar(10) | `native`, `prompted` or `none` |
| reasoning_tokens | int | reasoning tokens the provider reported (part of the output tokens) |
| reported_cost_micros | int | the cost the provider reported (OpenRouter), in millionths of the currency; 0: none |
| mcp_calls | int | MCP tool calls of the request's answer |
| input_tokens, output_tokens, cache_write_tokens, cache_read_tokens | int | the usage the API reported, normalized by the provider |
| stop_reason | varchar(30) | `end_turn`, `tool_use`, `max_tokens`, `refusal`, ... |
| http_status, retries, latency_ms | int | the HTTP status, retries made, time of the call |
| tool_count, tool_ms, tool_calls | int, int, longtext | the tools the answer asked for, their total time, JSON `[{name, ok, ms}]` (names and outcomes, no inputs) |
| drafts_written | int | drafts created or changed by those tools |
| error_class, error_message | varchar(60), varchar(255) | for a failed request |
| rate_limits | longtext | JSON of the provider's rate limit response headers (`RateLimitHeaders[]`: `anthropic-ratelimit-*`, `x-ratelimit-*`, `retry-after` ...) |
| request_debug | longtext | the full request, only with `[UsageSettings] LogRequests=enabled` |

Indexes: (created), (user_id, created), (conversation_id, turn), (provider, created). The cost is not stored (except
a cost the provider reported): it is computed from the tokens with the model alias's prices or the `[Pricing]` table
when shown, so a corrected price table corrects the past as well.

**expai_conversation**: one row per conversation of a user on a node.

| Column | Type | Meaning |
| --- | --- | --- |
| id | auto increment | |
| user_id, node_id | int | |
| messages | longtext | JSON: `{system, tools_json, messages, history, window_turns}` |
| turn_count, iterations, draft_count | int | editor messages, model calls of the current message, drafts written |
| model | varchar(100) | the last model that answered |
| model_alias, provider | varchar(100), varchar(40) | the model the conversation is bound to ([providers.md](providers.md)) |
| created, modified | int | Unix time |

Index: (user_id, node_id).

**expai_probe_result**: one row per run of bin/php/expaiprobe.php ([probe.md](probe.md)).

| Column | Type | Meaning |
| --- | --- | --- |
| id | auto increment | |
| model_alias, provider, model | varchar(100), varchar(40), varchar(100) | the model probed |
| tool_mode | varchar(10) | `native` or `prompted` |
| probe_mode | varchar(10) | `live` (counts for the write gate) or `mock` (a test server) |
| tasks, passed, pass_rate | int | the tasks, how many passed, the pass rate in percent |
| details | longtext | JSON per task: id, ok, reason, ms, the tools called (no content) |
| user_id, created | int | who ran it, Unix time |

Index: (model_alias, created). Names fit Oracle's limits; no column is an Oracle reserved word.

**expai_batch**: one provider batch of a batch lane ([lanes.md](lanes.md)): `provider`, `lane`, `model_alias`, `model`,
`batch_api`, `remote_id` (the provider's id), `input_ref` / `output_ref` (input and result files or URLs), `state`
(submitted, in_progress, canceling, ended, failed, expired, canceled, done), `remote_status`, `request_count`,
`succeeded`, `errored`, `expired`, `canceled`, `submitted`, `ended`, `expires_at`, `results_fetched`, `deleted` (when
it was deleted at the provider), `polls`, `submit_job_uuid`, `user_id`, `retention`, `last_error`, `created`,
`modified`. Index (state, provider). Its requests are the expqueue jobs whose `external_ref` is `expai-batch:<id>`.

Columns of lanes in the other tables: `expai_usage_log.lane`, `service_tier` (what the provider reported),
`job_uuid`, `batch_id`, `price_factor` (percent of the list price the request is billed at; costs use it; index
on job_uuid); `expai_draft_log.job_uuid` (the queue job or background package install a draft came from), `package_uuid` (index; empty: content packages are the kernel's, [packages.md](packages.md)).

## Installing

```
php extension/expai/bin/php/expaischema.php --install -s <siteaccess>   # create missing tables, columns, indexes (idempotent)
php extension/expai/bin/php/expaischema.php --check -s <siteaccess>     # report only (tables, columns, indexes)
php extension/expai/bin/php/expaischema.php --print=mysql               # the SQL of an engine
```

`--install` asks the database which expai tables exist (the drivers' own table lists only cover tables named
`ez*`), generates the statements of the missing ones with the kernel's schema handler of the engine and runs them.
For tables that exist it compares their columns and indexes with the definition and adds what is missing (`ALTER
TABLE ... ADD`, `CREATE INDEX`, generated by the same handler): `--install` is also the upgrade. MongoDB has no fixed
columns; new collections are created, new indexes of existing collections are not.

The money columns (`*_micros`: `expai_usage_log.reported_cost_micros` and `reserved_micros`, `expai_thread.cost_micros`
and `reserved_micros`, `expai_thread_run.cost_micros`, `expai_chain_run.cost_micros`, `expai_chain_step.cost_micros`)
are `bigint`: 32 bits end at 2 147 currency units. `--install` widens them on an installation of an earlier state
(MySQL / MariaDB `ALTER TABLE ... MODIFY ... bigint(20)`, PostgreSQL `ALTER COLUMN ... TYPE bigint`); SQLite stores
every integer in 64 bits and Oracle's `INTEGER` is `NUMBER(38)`, so nothing changes there. The money columns of the
queue (`expqueue_job`, `expqueue_group`) belong to expqueue and are unchanged.

`expai_conversation.busy_owner` and `busy_until` are the lock of a step under way (one step per conversation at a
time); `expai_usage_log.reserved_tokens` and `reserved_micros` are the reservation of a request under way.

**`--install` is a mandatory step of every deployment of this version**. Until it has run, expai degrades instead of
failing: without `reserved_tokens` and `reserved_micros` the usage log leaves them out of its rows and queries and
requests are not reserved before they are sent (the limits count settled requests only); without `busy_owner` and
`busy_until` steps run without the conversation lock. Each logs one warning per process naming the command. A
process asks for the columns once; a missing column is asked again after five minutes, so long-running workers see
the upgrade without a restart. On MySQL / MariaDB the widening to `bigint` rebuilds the table (`ALTER TABLE ... MODIFY`): on a large
`expai_usage_log` it takes a while and blocks writes to the table meanwhile, so run it outside busy hours. PostgreSQL
rewrites the table as well (`ALTER COLUMN ... TYPE bigint`).

| Engine | File | Notes |
| --- | --- | --- |
| MySQL / MariaDB | `sql/mysql/schema.sql` | InnoDB |
| PostgreSQL | `sql/postgresql/schema.sql` | sequences `expai_*_id_seq` |
| SQLite | `sql/sqlite/schema.sql` | |
| Oracle | `sql/oracle/schema.sql` | sequences `se_expai_*` and insert triggers; run in SQL*Plus |
| MongoDB | `sql/mongodb/schema.json` | collections and indexes; `--install` creates them with expMongoSchema |

All files and `share/db_schema.dba` are generated from `expAiSchema::definition()`:

```
php extension/expai/bin/php/expaischema.php --write-files
```

Verified for 0.0.1.0: SQLite (created with `--install` on a live installation, idempotent on the second run), MySQL /
MariaDB and PostgreSQL (each file run in a throwaway database, one row inserted into each table). The Oracle and
MongoDB files are generated by the kernel's handlers and were not run.

Verified for the provider columns: the upgrade on a live SQLite installation of 0.0.1.0 (`--check` listed the
missing columns and index, `--install` added them, `--check` then passed), and the MySQL / MariaDB and PostgreSQL files
again in throwaway databases with all four tables. Oracle and MongoDB: generated, not run.

## Removing

There is no uninstall script. To remove expai's data, drop the 15 `expai_*` tables (or collections) after deactivating the
extension. Drafts expai wrote stay ordinary drafts.

## Threads

Seven tables for threads and Vector ([threads.md](threads.md)), defined in `expAiThreadSchema` (classes/vector/) and
merged into `expAiSchema::definition()`, so `--install`, `--write-files` and every engine cover them:

| Table | One row per | Main columns |
| --- | --- | --- |
| `expai_thread` | thread | uuid, user_id, title, origin, node_id, scope_json, run_mode, review_policy, model_alias, provider, lane, state, shelf, position, lead_run_id, plan_id, cost_micros, tokens, requests, reserved_micros, last_event_id, seen_event_id, created, modified |
| `expai_thread_run` | agent run | thread_id, parent_run_id, plan_item, job_uuid, conversation_id, kind, state, label, progress_done, progress_total, model_alias, lane, cost_micros, tokens, started, finished |
| `expai_thread_event` | event (append only) | thread_id, run_id, kind, payload (JSON), created |
| `expai_followup` | queued message | thread_id, position, body, delivery (queue, steer), state (waiting, sent, dropped) |
| `expai_checkpoint` | state at a turn | thread_id, turn, label, conversation_len, drafts_json, changes_json |
| `expai_plan` | plan | thread_id, items_json, estimate_json, state, approved_by, approved |
| `expai_change` | staged proposal (object x language x attribute) | thread_id, run_id, contentobject_id, new_key, parent_node_id, class_identifier, language_code, attribute_identifier, datatype, base_version, base_hash, proposed, hunks_json, decision, applied_version, decided_by, decided |

Indexes: `expai_thread` (user_id, shelf, modified), (user_id, state), (uuid), (node_id); runs (thread_id, id),
(conversation_id); events (thread_id, id), (created); follow-ups (thread_id, state); checkpoints (thread_id, turn),
(created); plans (thread_id); changes (thread_id, decision), (contentobject_id, language_code), (created).

New columns of the existing tables: `expai_conversation.thread_id`, `run_id`; `expai_usage_log.thread_id`, `run_id`;
`expai_draft_log.thread_id`, `change_id` (each with an index on `thread_id`). `expai_draft_log.source` is `vector` for
drafts of a Vector thread. Names stay within Oracle's 30 characters and avoid its reserved words (`run_mode`,
`position`).

**Upgrade**: `bin/php/expaischema.php --install` adds the tables and columns and turns the panel conversations into
threads (idempotent; `bin/php/expaithreads.php --migrate --dry-run` shows what it would do). Verified on a copy of
alpha's expai tables in a throwaway SQLite database (upgrade, dry run, migration, second run without changes, links
and cached cost per thread), then on the live SQLite installation. The MySQL, PostgreSQL and Oracle statements are
generated by the kernel's handlers (`--print=<engine>`).

**Retention**: events, proposals and checkpoints after 30 days (`[ThreadSettings]`, the cronjob part `expai`,
`bin/php/expaithreads.php --prune`).

## AI chains

Two tables for AI chains ([chains.md](chains.md#database)), defined in `expAiChainSchema` (classes/chain/) and merged
into `expAiSchema::definition()`, so `--install`, `--check`, `--write-files` and every engine (MySQL, PostgreSQL,
SQLite, Oracle, MongoDB) cover them; `--install` on an installation without them is the upgrade:

| Table | One row per | Main columns |
| --- | --- | --- |
| `expai_chain_run` | run of a chain | `uuid` (index), `chain`, `chain_hash`, `user_id`, `group_uuid` (the expqueue job group), `node_id`, `object_id`, `version`, `language`, `brief`, `state` (index with `user_id`), `current_step`, `data` and `snapshot` (JSON), `cost_micros`, tokens, `last_error`, times |
| `expai_chain_step` | step and iteration of a run | `run_id` (index), `step`, `step_type`, `iteration`, `job_uuid` (index), `model`, `provider`, `lane`, `service_tier`, `state`, `verdict`, `score` (-1: none), `issue_count`, tokens, `cost_micros`, `usage_log_id`, `input_hash`, `output` (JSON, capped), `note`, times |

The usage rows of chain steps are ordinary `expai_usage_log` rows (action `queue.chain`) found by `job_uuid`; the draft
a run writes has an `expai_draft_log` row with `source` = `chain` and the job. Removing chain data: delete the rows of
both tables; the drafts stay ordinary drafts.
