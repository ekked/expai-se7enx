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
| `MaxRequestsPerTurn` | the most model calls one message may lead to; empty: `[AssistantSettings] MaxToolIterations` |

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

## The other settings files of the extension

| File | What it sets |
| --- | --- |
| `module.ini` | `ModuleList[]=expai` (policies `expai/write`, `expai/log`, `expai/settings`; views expai/dashboard, expai/log) |
| `ezjscore.ini` | `[ezjscServer] FunctionList[]=expai`, `[ezjscServer_expai] Class=expAiServerFunctions`, `Functions[]=expai` |
| `design.ini` | `DesignExtensions[]=expai`, `BackendJavaScriptList[]=expai.js`, `BackendCSSFileList[]=expai.css` |
| `admininterface.ini` | the tabs `expai` (content navigation part) and `expai_media` (media), header template `expai_header.tpl`, body `expai.tpl` |
| `content.ini` | `[EditSettings] AdditionalTemplates[]=content/edit/expai.tpl` |
| `menu.ini` | `[Leftmenu_my] Links[expai]=expai/dashboard` and `Links[expai_log]=expai/log`, shown with `expai/write` |
| `cronjob.ini` | the cronjob part `expai` (`expai_prune.php`: removes usage rows older than `RetentionDays`) |
| `dashboard.ini` | the dashboard block `expai_drafts` (Priority 35, 5 items, `expai/write`) |
| `browse.ini` | `[ExpAiStartWriting]`: the content browser of "Change location" (single node) |
| `site.ini` | `TranslationExtensions[]=expai`, `[TemplateSettings] ExtensionAutoloadPath[]=expai` (the template operators `expai_money`, `expai_tokens`, `expai_number`) |
