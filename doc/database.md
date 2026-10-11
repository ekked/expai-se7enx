# Database

## The tables

**expai_draft_log**: one row per version expai wrote or filled.

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
| source | varchar(20) | panel, edit |
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
| tool_mode | varchar(10) | `native`, `prompted` or `none` |
| reasoning_tokens | int | reasoning tokens the provider reported (part of the output tokens) |
| reported_cost_micros | int | the cost the provider reported (OpenRouter), in millionths of the currency; 0: none |
| mcp_calls | int | MCP tool calls of the request's answer |
| input_tokens, output_tokens, cache_write_tokens, cache_read_tokens | int | the usage the API reported, normalised by the provider |
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

Verified for 0.1.0: SQLite (created with `--install` on a live installation, idempotent on the second run), MySQL /
MariaDB and PostgreSQL (each file run in a throwaway database, one row inserted into each table). The Oracle and
MongoDB files are generated by the kernel's handlers and were not run.

Verified for the provider columns (#358): the upgrade on a live SQLite installation of 0.1.0 (`--check` listed the
missing columns and index, `--install` added them, `--check` then passed), and the MySQL / MariaDB and PostgreSQL files
again in throwaway databases with all four tables. Oracle and MongoDB: generated, not run.

## Removing

There is no uninstall script. To remove expai's data, drop the three tables (or collections) after deactivating the
extension. Drafts expai wrote stay ordinary drafts.
