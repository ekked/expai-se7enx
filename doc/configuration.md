# Configuration

All of expai's own settings are in `expai.ini` (defaults in `extension/expai/settings/expai.ini.append.php`).
Change them in `settings/override/expai.ini.append.php` (or a siteaccess's `expai.ini.append.php`), never in the
extension. After a change, clear the INI and template caches (the templates read some settings when they are
compiled) and, on Velocity, restart it:

```
php bin/php/ezcache.php --clear-tag=ini
php bin/php/ezcache.php --clear-id=template,template-override,content,template-block,ezjscore-packer
```

**Lists.** expai's lists (`Tools[]`, `WritableDatatypes[]`, `Sections[]`, `FieldActions[]`, `Datatypes[]`) are not
emptied in the extension, so another extension can add to them whatever its place in the extension order. To replace
a list, empty it where you set it: a line with only `FieldActions[]`, then the entries you want.

## [AISettings]: the provider

| Setting | Default | Effect |
| --- | --- | --- |
| `Enabled` | `false` | `true` or `enabled` switches the assistant on. Off: no tab, no edit form controls; the dashboard says "Switched off". |
| `Provider` | `anthropic` | `anthropic` (the built-in Anthropic Messages API) or `custom` (the class in `ProviderClass`). |
| `ProviderClass` | (empty) | With `Provider=custom`: a class implementing `expAiProvider` (extending.md). |
| `Endpoint` | `https://api.anthropic.com/v1/messages` | The Messages API URL (http or https, no credentials in it). |
| `AnthropicVersion` | `2023-06-01` | The `anthropic-version` header. |
| `Model` | `claude-sonnet-5-5` | The model. The dashboard shows it; the log records the model that answered. |
| `AllowUserModelChoice` | `enabled` | Users may pick another model from `AllowedModels[]` (dashboard, panel, edit form); the choice is the preference `expai_model`. A choice that is no longer allowed falls back to `Model`. |
| `AllowedModels[]` | `claude-sonnet-5-5`, `claude-opus-5-5`, `claude-haiku-5-5` | The models offered. `Model` is always offered. |
| `ModelLabels[<id>]` | Claude Sonnet 5.5, ... | The name shown for a model id (the id when not set). |
| `ApiKey` | (empty) | The API key. Only in settings/override, never committed. Without it expai is "not configured". |
| `AnthropicWorkspaceId` | (empty) | For a key that is not scoped to a workspace: sent as `anthropic-workspace-id`. |
| `MaxTokens` | `8000` | The longest answer of one call, in tokens. |
| `Effort` | `medium` | How much the model thinks: `low`, `medium`, `high` (`xhigh`, `max` where the model has them); empty: the model's default. |
| `RefusalFallback` | `enabled` | Sends `fallbacks: "default"`: a request the model declines for a safety reason is retried on the model Anthropic recommends (Claude API only; set `disabled` for other endpoints). |
| `PromptCaching` | `enabled` | Asks the API to cache the stable start of each request (instructions, tools, earlier turns). |
| `Timeout` | `115` | Seconds for one call. Keep it below the web server's and PHP's request limits (architecture.md, "Long turns"). |
| `ConnectTimeout` | `10` | Seconds to connect. |
| `MaxResponseBytes` | `4194304` | The largest answer accepted, in bytes. |
| `FallbackToDefaultModel` | `enabled` | When the model a request asked for cannot answer (stopped, unreachable, not allowed, removed, kept away by the data policy), the site default answers instead (or the first allowed model that may take the content) and the editor sees one line saying so. `disabled`: the failure is shown as before. See [providers.md](providers.md), "When a model cannot answer". |
| `HealthCacheSeconds` | `30` | Seconds a provider's state (running, stopped, unreachable) is kept per process for the model pickers and the fallback; `0` checks on every use. |
| `Region` | `us` | Where Anthropic processes the requests (`us`, `eu`, `local`), for the data policy and the region the log shows per request. |
| `Retention` | `30d` | How long Anthropic keeps API data: `none`, `zdr` (zero data retention agreed), `30d`, `unknown`. Check your contract. |
| `TermsCheckedAt` | (empty) | The day you checked the terms (YYYY-MM-DD); expai/models shows "not checked" while empty. |

The settings above describe the provider "anthropic" as long as there is no `[Provider_anthropic]` group (the 0.1.0
settings keep working unchanged). More providers: the next three sections and [providers.md](providers.md).

## [ProviderSettings]: the providers

| Setting | Default | Effect |
| --- | --- | --- |
| `Providers[]` | `anthropic` | The active providers, in the order the model picker shows them. Each needs a `[Provider_<id>]` group, except `anthropic`, which falls back to `[AISettings]`. |
| `DefaultModel` | (empty) | The alias of the site default model; empty: `anthropic.<[AISettings] Model>`. |
| `ProvidersWithoutPolicy[]` | `anthropic` | The models of these providers can be chosen by everyone with `expai/write` (as in 0.1.0); every other provider's models need `expai/model_use` (limitation Provider). Empty it to require the policy for everything but the default. |

## [Provider_<id>]: one provider

| Setting | Effect |
| --- | --- |
| `Type` | `anthropic` (Messages API), `openai_chat` (OpenAI Chat Completions and every compatible server), or `custom` with `Class=` (a class implementing `expAiProvider`; its constructor receives these settings with the model's). |
| `Flavor` | `openai_chat` only: `openai`, `huggingface`, `openrouter`, `mistral`, `xai`, `ollama`, `llamacpp`, `generic`: the small differences (tool_choice support, Mistral's tool names, `max_completion_tokens`, OpenRouter's `reasoning` object). |
| `Label` | The name in the picker, in error texts and on expai/models. |
| `BaseURL` | The API: for `openai_chat` the base (`https://router.huggingface.co/v1`; `/chat/completions` is added, a query such as Azure's `api-version` is kept); for `anthropic` the messages URL. |
| `ApiKey` | The key, ONLY in settings/override. expai/models warns about a key in any other file. |
| `ApiKeyHeader` | `authorization` (Bearer, default of `openai_chat`), `x-api-key` (default of `anthropic`), `api-key` (Azure), `x-goog-api-key`, or `none` (a local server). |
| `KeyOptional` | `true`: send no key header when `ApiKey` is empty (a server that accepts both). |
| `Headers[]` | Extra request headers, `Name: value` (e.g. OpenRouter's `X-OpenRouter-Title`). Key headers, `Host` and line breaks are refused. |
| `ExtraBody` | A JSON object merged into every request body, e.g. OpenRouter's routing `{"provider":{"data_collection":"deny","zdr":true}}`. It cannot change the model, the messages or the tools. |
| `Region`, `Retention`, `TermsCheckedAt` | As in `[AISettings]`; used by the data policy ([data-policy.md](data-policy.md)) and shown per request. |
| `Timeout`, `ConnectTimeout`, `MaxResponseBytes` | As in `[AISettings]`, for this provider (a local CPU model needs a longer `Timeout`, e.g. 280). |
| `RateLimitHeaders[]` | Glob patterns of the response headers kept in the usage log (default: `anthropic-ratelimit-*` and `retry-after` for anthropic; `x-ratelimit-*`, `retry-after`, `x-request-id` for openai_chat). |
| `RetryAfterMax` | Seconds of a `retry-after` on 429/503/529 that expai waits once before retrying (default 5); a longer wait is reported to the editor at once. |
| `RetryOnServerError` | `enabled` (default): one retry after a second on 500/502/504 or a failed connection (never after a timeout). |
| `AnthropicExtras` | `anthropic` only: `disabled` leaves out prompt caching, the refusal fallback, the workspace header and the beta headers (for an Anthropic-compatible server such as Ollama's `/v1/messages`). |
| `Managed` | `ollama`: a local Ollama that expai/models can start, stop and pull models for ([local-models.md](local-models.md)). |

## [Model_<alias>]: one model

The alias is what users choose and what the usage log records (letters, digits, `.`, `_`, `:`, `-`).

| Setting | Effect |
| --- | --- |
| `Provider` | The provider id. Models of a provider that is not in `Providers[]` are ignored. |
| `Id` | What the API expects (Hugging Face: may end in `:fastest`, `:cheapest`, `:preferred` or `:<provider>`). |
| `Label` | The name in the picker. |
| `ToolUse` | `native` (the API's tool calling), `prompted` (tools described in the instructions, the model answers with JSON: read tools only), `none` (no tools: a read-only snapshot of the node). |
| `WriteTools` | `enabled`: the model may create and change drafts, if `ToolUse=native` and it passed the probe ([probe.md](probe.md)) or `WriteGate=trusted`. |
| `WriteGate` | `probe` (default) or `trusted` (no probe needed; the built-in Anthropic models are trusted). |
| `Capabilities[]` | `json_schema` / `json_object` (structured answers), `reasoning_effort` (sends `Effort`), `no_tool_choice` (the server ignores tool_choice), `tool_choice_none`, `prompt_cache`, `vision`. |
| `MaxOutput` | The longest answer of one call (tokens); default `[AISettings] MaxTokens`. |
| `Effort` | `none`, `minimal`, `low`, `medium`, `high`, `xhigh` (with `Capabilities[]=reasoning_effort`; `none` switches thinking off, e.g. for Qwen on a CPU). |
| `InputPerMTok`, `OutputPerMTok`, `CacheWritePerMTok`, `CacheReadPerMTok` | Prices per million tokens (0: free). Without them the model is priced by `[Pricing]` by its id; a model of another provider without prices has an unknown cost (never a Claude price). A cost the provider reports (OpenRouter) always wins. |
| `Slow` | `true` marks the model "slow" in the picker (default for providers with `Region=local`). |
| `MaxTokensParameter`, `ParallelToolCalls`, `StrictTools`, `ExtraBody` | `max_tokens` or `max_completion_tokens`; `enabled`/`disabled` sends `parallel_tool_calls`; `enabled` sends `strict: true` on tool definitions; a JSON object merged into the body for this model. |

## [AssistantSettings]: the conversation

| Setting | Default | Effect |
| --- | --- | --- |
| `MaxToolIterations` | `10` | The most model calls one editor message may lead to (each tool round is one). The last one is made without tools, so the model answers in words. |
| `MaxToolResultBytes` | `16000` | The longest tool result sent to the model; longer results are cut and say so. |
| `MaxConversationBytes` | `400000` | The largest window (messages sent to the model); beyond it the editor is asked to start a new conversation. |
| `MaxMessageLength` | `4000` | The longest message or brief, in characters (also the textarea's maxlength). |
| `MaxDraftsPerConversation` | `10` | The most drafts one conversation may create (new objects and new versions). |
| `ConversationLifetime` | `604800` | Seconds after its last message a conversation is still continued (older: a new one starts). |
| `ContextWindowTurns` | `8` | Exchanges in the window sent to the model. When it is full, the older exchanges become a short summary (cut from their own text, no extra call) and the last half are kept word for word. The panel still shows the whole history. |
| `ExtraInstructions[]` | (none) | Lines added to the instructions of the panel and the edit form, e.g. `ExtraInstructions[]=Write in British English.` New conversations get them; running ones keep the instructions they started with. |

## [ToolSettings]: what the assistant may use

| Setting | Default | Effect |
| --- | --- | --- |
| `Tools[]` | the seven tools of tools.md | The tools offered to the model, each defined in `[Tool_<name>] Class=`. |
| `WritableDatatypes[]` | ezstring, eztext, ezxmltext, ezkeyword, ezboolean, ezinteger, ezfloat, ezdate, ezdatetime, ezemail, ezurl | The datatypes the tools and field actions may write. Others (images, files, relations, ...) are skipped and reported. |
| `ListLimit` | `25` | The most children / siblings one lookup returns. |
| `FieldValueMaxLength` | `3000` | The longest field value a lookup returns, in characters. |

`[Tool_<name>] Class=` names the class of each tool (`expAiToolGetNode`, `expAiToolListChildren`,
`expAiToolGetTreeContext`, `expAiToolListCreatableClasses`, `expAiToolGetClassDefinition`, `expAiToolCreateDraft`,
`expAiToolUpdateDraft`).

## [PanelSettings]: the Intelligence tab

| Setting | Default | Effect |
| --- | --- | --- |
| `Sections[]` | `conversation`, `drafts` | The sections of the panel, in this order; each `[PanelSection_<name>] Template=` (a template path below design:). |

## [EditViewSettings]: the edit form

| Setting | Default | Effect |
| --- | --- | --- |
| `Enabled` | `true` | The Exponential Intelligence area and the field actions in content/edit. |
| `Sections[]` | `assistant` | The sections of the edit form area; each `[EditSection_<name>] Template=`. |
| `FieldActions[]` | write, rewrite, shorten, translate, suggest | The buttons next to the fields, in this order. |

Each `[FieldAction_<name>]`:

| Setting | Effect |
| --- | --- |
| `Label` | The button text (translated in the context `extension/expai/fieldaction`). |
| `Handler` | A class implementing `expAiFieldAction`; `expAiPromptFieldAction` uses `Instruction`. |
| `Datatypes[]` | Where the button is offered (and only if the datatype is in `WritableDatatypes[]`). |
| `Input` | `none` (runs at once), `instruction` (asks what to write), `optional_instruction`, or `language` (asks for one of the site's languages). |
| `Instruction` | The task given to the model; the handler adds the object, the field, its value, the other fields and the editor's input. |

## [DashboardSettings]

| Setting | Default | Effect |
| --- | --- | --- |
| `DefaultNodeID` | (empty) | The location "Start writing" uses by default: the request then works there and anywhere below it, within the editor's permissions. Empty: `content.ini [NodeSettings] RootNode`. Users who may not read it or create below it pick a location instead. |

## [LabelSettings] and [LogSettings]

| Setting | Default | Effect |
| --- | --- | --- |
| `[LabelSettings] Enabled` | `enabled` | The "Exponential Intelligence" label in lists, version views, edit forms and approval items. |
| `[LogSettings] MaxPromptSummaryLength` | `200` | The longest request summary stored in the log (characters, at most 255). |

## [UsageSettings]: the usage log

| Setting | Default | Effect |
| --- | --- | --- |
| `RetentionDays` | `365` | Rows of `expai_usage_log` older than this are removed by the cronjob part `expai` (`0` keeps everything). |
| `LogRequests` | `disabled` | `enabled` stores the full request with each row (debugging only: it contains content and the editors' words), and lets "Prompt again" fill in the earlier request. |

See [usage-tracking.md](usage-tracking.md).

## [Limits]: optional limits

Every limit is optional: empty or `0` is no limit. Tokens are input + output + cache write + cache read; costs are the
`[Pricing]` estimate. They are checked before every request to the model; the editor is told which limit was reached
and when it resets. Days and months follow the site's time zone. The dashboard shows a bar for each limit that is set.

| Setting | Resets |
| --- | --- |
| `MaxTokensPerConversation` | with "New conversation" in the panel |
| `MaxTokensPerSession` | when the user logs in again |
| `MaxTokensPerUserPerDay`, `MaxCostPerUserPerDay` | at midnight |
| `MaxTokensPerUserPerMonth`, `MaxCostPerUserPerMonth` | on the 1st |
| `MaxTokensPerSitePerMonth`, `MaxCostPerSitePerMonth` | on the 1st (all users together) |
| `MaxRequestsPerTurn` | the most model calls one message may lead to; empty: `[AssistantSettings] MaxToolIterations` (a retry of the prompted tool protocol counts) |
| `ProviderMaxTokensPerMonth[<provider>]`, `ProviderMaxCostPerMonth[<provider>]` | on the 1st: all users together on one provider; only requests to that provider are stopped |

Anthropic's own limits apply as well: the rate limits of the key (the dashboard shows the last values the API
reported) and the spend limit of the organization, set in the Claude Console.

## [Pricing]: the estimated cost

| Setting | Default | Effect |
| --- | --- | --- |
| `Enabled` | `true` | `false` hides costs everywhere (dashboard, log, CSV) and the cost limits are not checked. |
| `Currency` | `USD` | The currency of the prices; shown with its symbol. |
| `PricesAsOf`, `Source` | the release date, Anthropic's pricing page | Shown under the cost so readers know how old the table is. |
| `Multiplier` | `1.0` | Applied to every price (regional inference, fast mode, a reseller's margin). |
| `Default*PerMTok` | Claude Sonnet 5.5 | Prices for a model without an entry; such costs are shown as approximate (`≈`). |
| `InputPerMTok[<model>]`, `CacheWritePerMTok[...]`, `CacheReadPerMTok[...]`, `OutputPerMTok[...]` | the shipped table | Prices per million tokens. Cache write is the 5-minute cache price. |
| `LongContextThreshold[<model>]` and `*PerMTokLong[<model>]` | Claude Haiku 5.5 | A request whose prompt (input + cache write + cache read) is above the threshold uses the `Long` prices. |

A model id is matched exactly, then without a provider prefix (`anthropic.`, `us.anthropic.`), a date (`-20261001`,
`@20261001`), `-latest` or a `-v1:0` suffix, then by family and version (the longest known id it starts with). The
unit test `php bin/php/ezexec.php extension/expai/tests/expaicost_test.php` checks every shipped id.
The estimate is not an invoice: Anthropic's invoice is authoritative.

## [DataPolicy]: where content may go

See [data-policy.md](data-policy.md). Without any rule every active provider may receive content.

| Setting | Default | Effect |
| --- | --- | --- |
| `DefaultRegions[]` | (none) | Regions any content may go to (`us`, `eu`, `local`). |
| `SectionRegions[<section id>]` | (none) | Regions the content of a section may go to, separated by `;` (e.g. `eu;local`). |
| `ClassRegions[<class identifier>]` | (none) | The same per class. Section, class and default narrow each other. |
| `ZeroRetention` | `disabled` | `enabled`: every request needs a provider with `Retention=none` or `zdr`. |
| `ZeroRetentionSections[]`, `ZeroRetentionClasses[]` | (none) | Sections and classes whose content needs zero retention. |

## [ProbeSettings]: the write gate

| Setting | Default | Effect |
| --- | --- | --- |
| `WritePassRate` | `90` | The pass rate (percent) of a live probe a model needs to write drafts ([probe.md](probe.md)). |
| `NodeID` | (empty) | The node the probe tasks name (nothing is read or written); empty: the content root. |
| `ClassIdentifier` | `folder` | The class the probe tasks name. |

## [LocalModelSettings]: a local Ollama

| Setting | Default | Effect |
| --- | --- | --- |
| `SpoolDirectory` | `/var/lib/expai-llm/spool` | Where expai/models writes requests for the root-owned helper ([local-models.md](local-models.md)). |
| `StatusFile` | `/var/lib/expai-llm/status.json` | The status the helper writes (read by expai/models). |
| `Models[]` | `qwen3.5:4b` | The models the view offers to pull and remove (the helper has its own allowlist). |

## Lanes

### [Lane_<provider>.<lane>]: one lane

A way of sending requests to a provider ([lanes.md](lanes.md)). Every provider has the lane `standard`, with or without
a group. Shipped: `anthropic.standard`, `anthropic.batch`, `anthropic.fast` (disabled), `openai.flex`, `openai.batch`,
`gemini.batch`, `gemini.flex` (disabled), `mistral.batch`, `openrouter.batch` and `openrouter.flex` (disabled).

| Setting | Default | Effect |
| --- | --- | --- |
| `Type` | `sync` (`batch` for a lane named batch) | `sync`: one call answered in the request; `batch`: submitted with others, polled, results fetched later. |
| `Enabled` | `true` with a group (standard always) | `false` keeps the lane out of every choice. |
| `PriceMultiplier` | `1` for standard, else empty | The factor on the list price (batch `0.5`, fast `2.0`). Empty: the price is not confirmed and the lane is never used. |
| `ExpectedLatency`, `MaxLatency` | 60 / 3600 s (batch), MaxLatency 86400 for batch | Typical and longest seconds until the answer, for deadlines. |
| `Retention` | `inherit` | What the provider keeps of a request on this lane: `inherit` (the provider's), `none`, `zdr`, `30d`, `29d`, `6w`, `unknown`. Only `none` and `zdr` meet a zero-retention rule. |
| `RequestParams[<name>]` | (none) | Values merged into the request body (`speed=fast`, `service_tier=flex`). |
| `Headers[]` | (none) | Extra request headers (`anthropic-beta: fast-mode-2026-02-01`); key headers are refused. |
| `FallbackLane` | (none) | The lane a request moves to when this one answers 429, 503 or 529. |
| `OnUnavailable` | `retry` | `escalate`: a flex lane's "resource unavailable" moves the request to `FallbackLane` at once. |
| `Timeout` | the provider's | Seconds of one call on a sync lane (flex: 900). |
| `Models[]` | (all) | Only these model aliases (or ids) use the lane. |
| `BatchApi` | the provider type | `anthropic`, `openai`, `gemini`, `mistral` or `openrouter`. |
| `BatchURL` | derived from the provider | The batch API base where it is not the provider's URL (Gemini: its native API). |
| `MaxRequestsPerBatch` | `2000` | Requests per provider batch (Mistral at most 9999 inline). |
| `BatchCollectSeconds` | `300` | How long waiting requests are collected before a batch is submitted. |
| `PollSeconds` | `300` | Seconds between two polls of a batch. |
| `DeleteAfterFetch` | `enabled` | Delete the batch (and its files) at the provider after its results were fetched. |
| `ExpiredLane` | `standard` | Where an expired or cancelled batch request goes. |
| `Verified`, `Source`, `Label` | | When and where the offer was read; a label. |

`[Provider_<id>] Lanes[]` and `[Model_<alias>] Lanes[]`, when set, restrict the lanes of a provider and a model. The
concurrency of a lane is `expqueue.ini [QueueResource_<provider>:<lane>] MaxConcurrent`.

### [LanePolicy]: which lane a queued request takes

| Setting | Default | Effect |
| --- | --- | --- |
| `Order[<purpose>]` | `default` standard, `bulk` batch;flex;standard, `translate` flex;batch;standard, `translate_full` batch;flex;standard | The lanes a request of that purpose tries, in order (`;` separated). |
| `ClassOrder[<queue class>]` | per class of expqueue | The order for a request whose purpose has none. |
| `PurposeClass[<purpose>]` | `default` editorial, `bulk` bulk, `translate` translation_patch, `translate_full` translation_full | The queue class of a request of that purpose. |
| `UrgentPriority` | `80` | From this priority on, flex and batch are skipped (0: never). |
| `StrictDeadline` | `disabled` | `enabled`: a deadline is checked against a batch lane's `MaxLatency`. |

## [Budgets]: budgets of queued requests

See [budgets.md](budgets.md).

| Setting | Default | Effect |
| --- | --- | --- |
| `SiteMonthly` | (empty: `[Limits] MaxCostPerSitePerMonth`) | The monthly budget of the site; requests of the month plus reservations must stay below it, else queued requests are held. |
| `DefaultGroupBudget` | (none) | The budget of a new job group whose producer names none. |
| `ReserveFactor` | `1.25` | A queued request reserves its estimate times this. |
| `CharsPerToken` | `3.5` | Characters per token for the estimate of the input. |
| `OutputShareOfMax` | `0.5` | The share of `max_tokens` estimated as output (unless the producer gives `est_output`). |

## [QueueSettings]: queued requests

| Setting | Default | Effect |
| --- | --- | --- |
| `Appliers[<name>]` | (none) | Classes implementing `expAiRequestApplier` that write the answer of a queued request somewhere (option `apply`). |
| `MaxStoredAnswerBytes` | `200000` | The longest answer kept in a job's checkpoint. |

## [PackageSettings]: content packages

See [packages.md](packages.md).

| Setting | Default | Effect |
| --- | --- | --- |
| `MaxObjects` | `2000` | Objects in one package (and in one subtree export). |
| `MaxFiles`, `MaxBytes`, `MaxFileBytes`, `MaxJsonBytes` | 5000, 500 MB, 50 MB, 50 MB | Files, total size, size of one file, size of package.json. |
| `MaxCompressionRatio` | `200` | A zip entry above 1 MB compressed better than this is refused. |
| `MaxUploadBytes` | 50 MB | The largest upload on expai/packages. |
| `QueueAbove`, `ChunkSize` | `20`, `5` | A package with more objects is imported as a job group, `ChunkSize` objects per job. |
| `RequireChecksum` | `enabled` | A package without a manifest checksum is refused. |
| `AllowedMimeTypes[]` | images, PDF, text, CSV, zip, Office, mp3, mp4 | File types a package may carry (SVG never). |
| `StringDatatypes[]` | `ezisbn`, `ezselection`, `ezcountry` | Datatypes beyond the built-in ones carried as their toString() text. |

## [McpBridge], [McpSettings], [McpServer_<id>]: MCP tools

See [mcp.md](mcp.md).

| Setting | Default | Effect |
| --- | --- | --- |
| `[McpBridge] Enabled` | `disabled` | `enabled`: audited read tools of the extension expmcp, in process as the current user (`exp__<tool>`, policy `expai/mcp` with Server `exp`). |
| `[McpBridge] Tools[]` | `exp_content_query` | The expmcp tools offered; only the audited ones are accepted. |
| `[McpSettings] Servers[]` | (none) | External MCP servers (streamable HTTP), each `[McpServer_<id>]` (id: a-z, 0-9, at most 20 characters). |

| `[McpServer_<id>]` setting | Effect |
| --- | --- |
| `Label` | The name shown with its tools. |
| `URL` | The endpoint: https, or http to a loopback address with `AllowLoopback=enabled`. |
| `Token` | Sent as a Bearer token; ONLY in settings/override. |
| `AllowedTools[]` | The tools the panel may call (`mcp__<id>__<tool>`); everything else the server offers is ignored. |
| `Region`, `Retention` | Checked against the panel node's data policy before every call. |
| `MaxResultBytes` | The longest result passed to the model (default 16000). |
| `Timeout` | Seconds per request (default 20). |
| `ToolsCacheTTL` | Seconds the tool list is cached (default 3600; 0: no cache). |
| `ProtocolVersion` | The MCP protocol version offered in `initialize` (default `2025-06-18`; the server's answer is used). |
| `AllowLoopback`, `AllowPrivate` | `enabled` lets the URL resolve to a loopback, or a private / link-local / reserved address (refused by default). |

## The other settings files of the extension

| File | What it sets |
| --- | --- |
| `module.ini` | `ModuleList[]=expai` (policies `expai/write`, `expai/log`, `expai/settings`, `expai/model_use`, `expai/model_manage`, `expai/mcp`; views expai/dashboard, expai/log, expai/models) |
| `ezjscore.ini` | `[ezjscServer] FunctionList[]=expai`, `[ezjscServer_expai] Class=expAiServerFunctions`, `Functions[]=expai` |
| `design.ini` | `DesignExtensions[]=expai`, `BackendJavaScriptList[]=expai.js`, `BackendCSSFileList[]=expai.css` |
| `admininterface.ini` | the tabs `expai` (content navigation part) and `expai_media` (media), header template `expai_header.tpl`, body `expai.tpl` |
| `content.ini` | `[EditSettings] AdditionalTemplates[]=content/edit/expai.tpl` |
| `menu.ini` | `[Leftmenu_my] Links[expai]=expai/dashboard` and `Links[expai_log]=expai/log`, shown with `expai/write` |
| `cronjob.ini` | the cronjob part `expai` (`expai_prune.php`: removes usage rows older than `RetentionDays`) |
| `dashboard.ini` | the dashboard block `expai_drafts` (Priority 35, 5 items, `expai/write`) |
| `browse.ini` | `[ExpAiStartWriting]`: the content browser of "Change location" (single node) |
| `site.ini` | `TranslationExtensions[]=expai`, `[TemplateSettings] ExtensionAutoloadPath[]=expai` (the template operators `expai_money`, `expai_tokens`, `expai_number`) |
