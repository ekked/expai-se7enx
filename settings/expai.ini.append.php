<?php /* #?ini charset="utf-8"?

# expai - Exponential Intelligence (Powered by Claude from Anthropic)
# The lists below (Tools[], WritableDatatypes[], Sections[], FieldActions[], Datatypes[]) are not emptied first, so
# another extension can add to them whatever its place in the extension order; to replace a list, empty it in
# settings/override (e.g. "FieldActions[]" alone on a line, then the entries you want).
# Every setting is described in doc/configuration.md. Put the API key ONLY in
# settings/override/expai.ini.append.php (never committed).

[AISettings]
# Off by default. When enabled, users with the policy expai/write see the
# "Exponential Intelligence" tab on the content full view and the controls in
# the content edit form. The browser never talks to the provider.
Enabled=false

# anthropic: the Anthropic Messages API (the only built-in provider)
# custom:    the class named in ProviderClass (implements expAiProvider)
Provider=anthropic
ProviderClass=

# The provider's API URL.
Endpoint=https://api.anthropic.com/v1/messages
AnthropicVersion=2023-06-01

# The model name the provider expects.
Model=claude-sonnet-5-5

# Users may choose another model from AllowedModels[] (dashboard, panel, edit form; preference expai_model).
# The site default (Model) is always allowed. Each entry was checked against the Anthropic Models API for 0.1.0.
AllowUserModelChoice=enabled
AllowedModels[]=claude-sonnet-5-5
AllowedModels[]=claude-opus-5-5
AllowedModels[]=claude-haiku-5-5
ModelLabels[claude-sonnet-5-5]=Claude Sonnet 5.5
ModelLabels[claude-opus-5-5]=Claude Opus 5.5
ModelLabels[claude-haiku-5-5]=Claude Haiku 5.5

# The API key. Put it ONLY in settings/override/expai.ini.append.php.
ApiKey=
# Only for a key that is not scoped to a workspace: the workspace every request names.
AnthropicWorkspaceId=

# The longest answer (tokens) one call may produce.
MaxTokens=8000

# How much the model thinks before it answers: low, medium, high (empty: the model's default).
Effort=medium

# enabled: a request the model declines for a safety reason is retried by the API on the model
# Anthropic recommends for that case (server-side fallback, Claude API only).
RefusalFallback=enabled

# enabled: the API caches the stable start of each request (tools, instructions, earlier turns).
PromptCaching=enabled

# Seconds to wait for one provider call, and for the connection. Keep Timeout below the web
# server's and PHP's request time limits (see doc/architecture.md, "Long turns").
Timeout=115
ConnectTimeout=10

# The longest answer accepted from the provider, in bytes.
MaxResponseBytes=4194304

# Where Anthropic processes the requests (us, eu or local) and how long it keeps them (none, zdr, 30d or unknown),
# for the data policy ([DataPolicy]) and the region shown per request. 30d: the commercial terms keep API data up
# to 30 days for abuse monitoring unless zero data retention was agreed. Check your own contract and write the day
# you did into TermsCheckedAt (YYYY-MM-DD; empty: not checked).
Region=us
Retention=30d
TermsCheckedAt=

[ProviderSettings]
# The providers the model picker offers, in this order. "anthropic" without a [Provider_anthropic] group is built
# from [AISettings] above (the settings of 0.1.0), with one model anthropic.<id> per [AISettings] AllowedModels[].
# Add another with Providers[]=<id> and a [Provider_<id>] group; its models are the [Model_<alias>] groups that name
# it. The groups below are examples that are not active until listed here (and given a key in settings/override).
Providers[]=anthropic

# The site default model alias (empty: anthropic.<[AISettings] Model>).
DefaultModel=

# The models of these providers can be chosen by every user with expai/write (as in 0.1.0). The models of every
# other provider need the policy expai/model_use (limitation Provider). Empty it ("ProvidersWithoutPolicy[]" alone)
# to require the policy for every choice but the site default.
ProvidersWithoutPolicy[]=anthropic

# A provider: Type=anthropic | openai_chat | custom (Class=<class implementing expAiProvider>)
#   BaseURL         the API (openai_chat: the base, /chat/completions is added; anthropic: the messages URL)
#   ApiKey          ONLY in settings/override; ApiKeyHeader authorization (Bearer, default of openai_chat) |
#                   x-api-key (default of anthropic) | api-key (Azure) | x-goog-api-key | none (a local server)
#   Headers[]       extra request headers ("Name: value"; key headers are refused)
#   Flavor          openai | huggingface | openrouter | mistral | xai | ollama | llamacpp | generic (small differences)
#   Region          us | eu | local; Retention none | zdr | 30d | unknown; TermsCheckedAt YYYY-MM-DD
#   ExtraBody       a JSON object merged into every request body (e.g. OpenRouter provider routing)
#   Timeout, ConnectTimeout, MaxResponseBytes, RateLimitHeaders[] (glob patterns of headers kept for the log),
#   RetryAfterMax (seconds of retry-after waited for once, default 5), RetryOnServerError (enabled)
#   Label           the name in the picker and in error texts; Managed=ollama: a local server expai/models manages
#
# Example: Hugging Face Inference Providers (router). The model id may end in :fastest, :cheapest, :preferred or
# :<provider> to choose the inference provider; pin one provider for a known data processor.
[Provider_huggingface]
Type=openai_chat
Flavor=huggingface
Label=Hugging Face
BaseURL=https://router.huggingface.co/v1
ApiKey=
Region=us
Retention=unknown
TermsCheckedAt=
Timeout=115

# Example: OpenRouter. It reports the cost of every request (usage.cost), which the log shows as "reported".
# ExtraBody keeps requests away from endpoints that store or train on data.
[Provider_openrouter]
Type=openai_chat
Flavor=openrouter
Label=OpenRouter
BaseURL=https://openrouter.ai/api/v1
ApiKey=
Headers[]
Headers[]=X-OpenRouter-Title: Exponential Intelligence
ExtraBody={"provider":{"data_collection":"deny","zdr":true}}
Region=us
Retention=zdr
TermsCheckedAt=
Timeout=115

# Example: Mistral (EU hosting).
[Provider_mistral]
Type=openai_chat
Flavor=mistral
Label=Mistral
BaseURL=https://api.mistral.ai/v1
ApiKey=
Region=eu
Retention=30d
TermsCheckedAt=
Timeout=115

# Example: a local Ollama on this server (see doc/local-models.md). No key; nothing leaves the server.
[Provider_local]
Type=openai_chat
Flavor=ollama
Label=Local model
BaseURL=http://127.0.0.1:11434/v1
ApiKeyHeader=none
Region=local
Retention=none
TermsCheckedAt=
Timeout=280
Managed=ollama

# A model: Provider, Id (what the API expects), Label, ToolUse=native | prompted | none, WriteTools=enabled (drafts;
# also needs a passed probe unless WriteGate=trusted), Capabilities[] (json_schema, json_object, reasoning_effort,
# tool_choice_none, no_tool_choice, vision), MaxOutput, Effort, prices per million tokens (InputPerMTok,
# OutputPerMTok, CacheWritePerMTok, CacheReadPerMTok; 0 = free; none: [Pricing] by Id, else "cost unknown"),
# Slow=true ("slow" in the picker; default for Region=local), MaxTokensParameter, ParallelToolCalls, ExtraBody.
# The ids below are the examples of the providers' documentation on 2026-10-11: check the provider's model list.
[Model_huggingface.gpt-oss-120b]
Provider=huggingface
Id=openai/gpt-oss-120b:fastest
Label=gpt-oss-120b (Hugging Face)
ToolUse=native
WriteTools=enabled
Capabilities[]=json_schema
Capabilities[]=reasoning_effort
Effort=low
MaxOutput=8000

[Model_openrouter.gpt-oss-120b]
Provider=openrouter
Id=openai/gpt-oss-120b
Label=gpt-oss-120b (OpenRouter)
ToolUse=native
WriteTools=enabled
Capabilities[]=json_schema
Capabilities[]=reasoning_effort
Effort=low
MaxOutput=8000

[Model_local.qwen3.5-4b]
Provider=local
Id=qwen3.5:4b
Label=Qwen3.5 4B (local)
ToolUse=native
WriteTools=enabled
Capabilities[]=json_object
Capabilities[]=reasoning_effort
Capabilities[]=no_tool_choice
# thinking off: much faster on a CPU
Effort=none
MaxOutput=2000
InputPerMTok=0
OutputPerMTok=0
Slow=true

# Examples of providers with lanes (not active until listed in [ProviderSettings] Providers[] and given a key).
[Provider_openai]
Type=openai_chat
Flavor=openai
Label=OpenAI
BaseURL=https://api.openai.com/v1
ApiKey=
Region=us
Retention=30d
TermsCheckedAt=
Timeout=115

# Gemini through its OpenAI-compatible endpoint; its batch lane uses the native API ([Lane_gemini.batch] BatchURL).
[Provider_gemini]
Type=openai_chat
Flavor=generic
Label=Google Gemini
BaseURL=https://generativelanguage.googleapis.com/v1beta/openai
ApiKey=
Region=us
Retention=unknown
TermsCheckedAt=
Timeout=115

# Lanes: [Lane_<provider>.<lane>] (doc/lanes.md). Every provider has the lane standard; the others are defined here.
# Type sync | batch; Enabled; PriceMultiplier (the factor on the list price; empty: not confirmed, the lane is not
# used); ExpectedLatency and MaxLatency (seconds); Retention inherit | none | zdr | 30d | 29d | 6w | unknown;
# RequestParams[<name>]=<value> merged into the request; Headers[]; FallbackLane; OnUnavailable retry | escalate;
# Timeout; Models[] (only these aliases); batch lanes: BatchApi anthropic | openai | gemini | mistral | openrouter,
# BatchURL, MaxRequestsPerBatch, BatchCollectSeconds, PollSeconds, DeleteAfterFetch, ExpiredLane. Verified and Source
# say when and where the offer was read. The concurrency of a lane is expqueue.ini [QueueResource_<provider>:<lane>].
[Lane_anthropic.standard]
Type=sync
PriceMultiplier=1.0
ExpectedLatency=60

# Message Batches: 50% of the standard prices; most batches end within an hour, unfinished requests expire after 24
# hours (not billed); requests and results are kept up to 29 days unless the batch is deleted, which expai does after
# fetching the results.
[Lane_anthropic.batch]
Type=batch
BatchApi=anthropic
Enabled=true
PriceMultiplier=0.5
ExpectedLatency=3600
MaxLatency=86400
MaxRequestsPerBatch=2000
BatchCollectSeconds=300
PollSeconds=300
Retention=29d
DeleteAfterFetch=enabled
ExpiredLane=standard
Verified=2026-10-11
Source=https://platform.claude.com/docs/en/build-with-claude/batch-processing

# Fast mode (research preview, needs access): Opus 5.5 at 2x ($8/$40 instead of $4/$20); falls back to standard when
# busy. Never chosen by the lane policy on its own: a request asks for it, and the user needs expai/lane_fast.
[Lane_anthropic.fast]
Type=sync
Enabled=false
PriceMultiplier=2.0
ExpectedLatency=20
RequestParams[]
RequestParams[speed]=fast
Headers[]
Headers[]=anthropic-beta: fast-mode-2026-02-01
FallbackLane=standard
Models[]
Models[]=anthropic.claude-opus-5-5
Verified=2026-10-11
Source=https://platform.claude.com/docs/en/build-with-claude/fast-mode

# Flex processing: batch prices (0.5x) for synchronous requests, slower; "429 Resource Unavailable" is not charged.
[Lane_openai.flex]
Type=sync
Enabled=true
PriceMultiplier=0.5
RequestParams[]
RequestParams[service_tier]=flex
Timeout=900
ExpectedLatency=300
OnUnavailable=escalate
FallbackLane=standard
Verified=2026-10-11
Source=https://developers.openai.com/api/docs/guides/flex-processing

[Lane_openai.batch]
Type=batch
BatchApi=openai
Enabled=true
PriceMultiplier=0.5
ExpectedLatency=3600
MaxLatency=86400
MaxRequestsPerBatch=2000
BatchCollectSeconds=300
PollSeconds=300
Retention=30d
DeleteAfterFetch=enabled
ExpiredLane=standard
Verified=2026-10-11
Source=https://developers.openai.com/api/docs/guides/batch

[Lane_gemini.batch]
Type=batch
BatchApi=gemini
BatchURL=https://generativelanguage.googleapis.com/v1beta
Enabled=true
PriceMultiplier=0.5
ExpectedLatency=3600
MaxLatency=86400
MaxRequestsPerBatch=500
BatchCollectSeconds=300
PollSeconds=300
Retention=6w
DeleteAfterFetch=enabled
ExpiredLane=standard
Verified=2026-10-11
Source=https://ai.google.dev/gemini-api/docs/batch-api

# Flex inference (50%, 1-15 minutes, sheddable with 429/503) is documented for the native API; whether the
# OpenAI-compatible endpoint takes service_tier was not shown: disabled until it is tested.
[Lane_gemini.flex]
Type=sync
Enabled=false
PriceMultiplier=0.5
RequestParams[]
RequestParams[service_tier]=flex
Timeout=900
ExpectedLatency=900
OnUnavailable=escalate
FallbackLane=standard
Verified=2026-10-11
Source=https://ai.google.dev/gemini-api/docs/flex-inference

[Lane_mistral.batch]
Type=batch
BatchApi=mistral
Enabled=true
PriceMultiplier=0.5
ExpectedLatency=3600
MaxLatency=86400
MaxRequestsPerBatch=2000
BatchCollectSeconds=300
PollSeconds=300
Retention=30d
DeleteAfterFetch=enabled
ExpiredLane=standard
Verified=2026-10-11
Source=https://docs.mistral.ai/studio/batch-processing

# OpenRouter: "typically billed at 50%"; the :batch model variants carry their own prices. Confirm the price of the
# model you use, then enable.
[Lane_openrouter.batch]
Type=batch
BatchApi=openrouter
Enabled=false
PriceMultiplier=0.5
ExpectedLatency=3600
MaxLatency=86400
MaxRequestsPerBatch=2000
BatchCollectSeconds=300
PollSeconds=300
Retention=30d
DeleteAfterFetch=enabled
ExpiredLane=standard
Verified=2026-10-11
Source=https://openrouter.ai/docs/batch-quickstart

# service_tier flex (OpenAI and Google endpoints only; never falls back); the price depends on the endpoint.
[Lane_openrouter.flex]
Type=sync
Enabled=false
PriceMultiplier=
RequestParams[]
RequestParams[service_tier]=flex
Timeout=900
ExpectedLatency=300
OnUnavailable=escalate
FallbackLane=standard
Verified=2026-10-11
Source=https://openrouter.ai/docs/guides/features/service-tiers

[LanePolicy]
# The lanes a queued request tries, in order, by its purpose (expAiQueue::request() option purpose), else by its
# queue class, else Order[default]. Lanes separated by ";". standard is always the last resort; fast is never taken
# from an order (only when a request asks for it).
Order[]
Order[default]=standard
Order[bulk]=batch;flex;standard
Order[translate]=flex;batch;standard
Order[translate_full]=batch;flex;standard
ClassOrder[]
ClassOrder[interactive]=standard
ClassOrder[editorial]=standard;flex
ClassOrder[chain]=standard;flex
ClassOrder[translation_patch]=flex;batch;standard
ClassOrder[translation_full]=batch;flex;standard
ClassOrder[bulk]=batch;flex;standard
# The queue class of a purpose (default: editorial)
PurposeClass[]
PurposeClass[default]=editorial
PurposeClass[bulk]=bulk
PurposeClass[translate]=translation_patch
PurposeClass[translate_full]=translation_full
# A request of this priority or higher skips flex and batch lanes (0: never)
UrgentPriority=80
# enabled: a deadline is checked against a batch lane's MaxLatency instead of its ExpectedLatency
StrictDeadline=disabled

[Budgets]
# The monthly budget of the whole site (in [Pricing] Currency) for queued requests: what every request of the month
# cost plus what queued requests reserved must stay below it, or new and waiting requests are held. Empty: [Limits]
# MaxCostPerSitePerMonth, else none.
SiteMonthly=
# The budget of a new job group when its producer names none (empty: none)
DefaultGroupBudget=
# A queued request reserves its estimate times this factor
ReserveFactor=1.25
# Estimates: characters per token of the request text, and the share of max_tokens expected as output
CharsPerToken=3.5
OutputShareOfMax=0.5

[QueueSettings]
# Classes that apply the answer of a queued request: Appliers[<name>]=<class implementing expAiRequestApplier>
Appliers[]
# The longest answer kept in a job's checkpoint, in bytes
MaxStoredAnswerBytes=200000

[PackageSettings]
# Content packages (doc/packages.md). Limits of one package:
MaxObjects=2000
MaxFiles=5000
MaxBytes=524288000
MaxFileBytes=52428800
MaxJsonBytes=52428800
MaxCompressionRatio=200
# The largest upload in expai/packages (larger packages: ./console ext:expai:package import)
MaxUploadBytes=52428800
# A package with more objects is imported as a queue job group, ChunkSize objects per job
QueueAbove=20
ChunkSize=5
# enabled: a package without a manifest checksum is refused
RequireChecksum=enabled
# The file types a package may carry (finfo); SVG is always refused
AllowedMimeTypes[]
AllowedMimeTypes[]=image/jpeg
AllowedMimeTypes[]=image/png
AllowedMimeTypes[]=image/gif
AllowedMimeTypes[]=image/webp
AllowedMimeTypes[]=application/pdf
AllowedMimeTypes[]=text/plain
AllowedMimeTypes[]=text/csv
AllowedMimeTypes[]=application/zip
AllowedMimeTypes[]=application/vnd.openxmlformats-officedocument.wordprocessingml.document
AllowedMimeTypes[]=application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
AllowedMimeTypes[]=audio/mpeg
AllowedMimeTypes[]=video/mp4
# Datatypes beyond the built-in ones whose value travels as their toString() text (and is written with fromString())
StringDatatypes[]
StringDatatypes[]=ezisbn
StringDatatypes[]=ezselection
StringDatatypes[]=ezcountry

[AssistantSettings]
# The most provider calls one message of the editor may lead to (each tool round is one call).
# The last allowed call is made without tools, so the assistant always answers in words.
MaxToolIterations=10

# The longest result of one tool, in bytes (longer results are shortened and say so).
MaxToolResultBytes=16000

# The longest stored conversation, in bytes. Longer conversations must be started anew.
MaxConversationBytes=400000

# The longest message of the editor, in characters.
MaxMessageLength=4000

# The most drafts the assistant may create or change in one conversation.
MaxDraftsPerConversation=10

# Seconds a conversation is kept after its last message (older ones start anew).
ConversationLifetime=604800

# The exchanges (an editor message and what followed) sent to the provider. When the window is full, the older
# exchanges become a short summary (cut from their text, no extra call) and the last half are kept word for word;
# the panel still shows the whole history.
ContextWindowTurns=8

# Extra instructions appended to the built-in system prompt (one line per entry), e.g.
# ExtraInstructions[]=Write in British English.

[ToolSettings]
# The tools the assistant can call, in this order. Each is described in [Tool_<name>].
Tools[]=get_node
Tools[]=list_children
Tools[]=get_tree_context
Tools[]=list_creatable_classes
Tools[]=get_class_definition
Tools[]=create_draft
Tools[]=update_draft

# Datatypes the assistant may write. Everything else (images, files, relations, ...) is skipped and
# reported back to the assistant.
WritableDatatypes[]=ezstring
WritableDatatypes[]=eztext
WritableDatatypes[]=ezxmltext
WritableDatatypes[]=ezkeyword
WritableDatatypes[]=ezboolean
WritableDatatypes[]=ezinteger
WritableDatatypes[]=ezfloat
WritableDatatypes[]=ezdate
WritableDatatypes[]=ezdatetime
WritableDatatypes[]=ezemail
WritableDatatypes[]=ezurl

# The most children / siblings one lookup returns.
ListLimit=25

# The longest field value the lookup tools return, in characters.
FieldValueMaxLength=3000

[Tool_get_node]
Class=expAiToolGetNode

[Tool_list_children]
Class=expAiToolListChildren

[Tool_get_tree_context]
Class=expAiToolGetTreeContext

[Tool_list_creatable_classes]
Class=expAiToolListCreatableClasses

[Tool_get_class_definition]
Class=expAiToolGetClassDefinition

[Tool_create_draft]
Class=expAiToolCreateDraft

[Tool_update_draft]
Class=expAiToolUpdateDraft

[PanelSettings]
# The sections of the full view panel, in this order: [PanelSection_<name>] Template=
Sections[]=conversation
Sections[]=drafts

[PanelSection_conversation]
Template=expai/panel/conversation.tpl

[PanelSection_drafts]
Template=expai/panel/drafts.tpl

[EditViewSettings]
# The controls in the content edit form (content.ini [EditSettings] AdditionalTemplates[]).
Enabled=true

# The sections of the edit view area, in this order: [EditSection_<name>] Template=
Sections[]=assistant

# The actions offered next to each writable field, in this order: [FieldAction_<name>]
FieldActions[]=write
FieldActions[]=rewrite
FieldActions[]=shorten
FieldActions[]=translate
FieldActions[]=suggest

[EditSection_assistant]
Template=expai/edit/assistant.tpl

# A field action: Label (translated in context extension/expai/fieldaction), Handler (a class implementing
# expAiFieldAction), Datatypes[] (where it is offered), Input (none, instruction, optional_instruction or language)
# and Instruction (what the model is asked to do; the handler adds the field, the object and the rules).
[FieldAction_write]
Label=Write
Handler=expAiPromptFieldAction
Datatypes[]=ezstring
Datatypes[]=eztext
Datatypes[]=ezxmltext
Input=instruction
Instruction=Write a new value for this field following the editor's instruction. Use the other fields of the object as context.

[FieldAction_rewrite]
Label=Rewrite
Handler=expAiPromptFieldAction
Datatypes[]=ezstring
Datatypes[]=eztext
Datatypes[]=ezxmltext
Input=optional_instruction
Instruction=Rewrite the current value of this field: clearer and better written, same meaning, same language, about the same length. Follow the editor's instruction when one is given.

[FieldAction_shorten]
Label=Shorten
Handler=expAiPromptFieldAction
Datatypes[]=eztext
Datatypes[]=ezxmltext
Input=none
Instruction=Shorten the current value of this field to about half its length. Keep the key facts and the language.

[FieldAction_translate]
Label=Translate
Handler=expAiPromptFieldAction
Datatypes[]=ezstring
Datatypes[]=eztext
Datatypes[]=ezxmltext
Input=language
Instruction=Translate the current value of this field into the target language. Keep the meaning, the tone and the formatting.

[FieldAction_suggest]
Label=Suggest
Handler=expAiPromptFieldAction
Datatypes[]=ezstring
Datatypes[]=ezkeyword
Input=none
Instruction=Suggest a good value for this field based on the other fields of the object (for a keyword field: five to eight comma separated keywords).

[DashboardSettings]
# The location "Start writing" on expai/dashboard uses by default: the editor's request then works on this node
# and everything below it (always within the editor's own permissions). Empty: content.ini [NodeSettings] RootNode.
# Users who may not read it or create below it choose a location instead.
DefaultNodeID=

[LabelSettings]
# enabled: draft lists, version views, edit forms and approval items show the
# "Exponential Intelligence" label on versions the assistant wrote.
Enabled=enabled

[LogSettings]
# The longest stored summary of the editor's request, in characters.
MaxPromptSummaryLength=200


[UsageSettings]
# Every request to the model is recorded in expai_usage_log (tokens, latency, tools, errors; user ids, no names).
# Rows older than this many days are removed by the cronjob part "expai" (0 keeps everything).
RetentionDays=365
# enabled: the full request (instructions, messages, tool definitions) is stored with each row, for debugging.
# It contains content and the editors' words: leave it disabled in production.
LogRequests=disabled

[Limits]
# Every limit is optional: empty or 0 means no limit. Tokens are input + output + cache write + cache read; costs are
# the [Pricing] estimate. Checked before every request to the model; the editor sees which limit and when it resets.
# Days and months follow the site's time zone.
# Per panel conversation (resets with "New conversation") and per login session (resets when the user logs in again):
MaxTokensPerConversation=
MaxTokensPerSession=
# Per user and day (reset at midnight) and month (reset on the 1st):
MaxTokensPerUserPerDay=
MaxCostPerUserPerDay=
MaxTokensPerUserPerMonth=
MaxCostPerUserPerMonth=
# All users together per month:
MaxTokensPerSitePerMonth=
MaxCostPerSitePerMonth=
# The most model calls one editor message may lead to; empty: [AssistantSettings] MaxToolIterations.
MaxRequestsPerTurn=
# All users together on one provider per month, e.g. ProviderMaxCostPerMonth[openrouter]=20
ProviderMaxTokensPerMonth[]
ProviderMaxCostPerMonth[]

[Pricing]
# Prices per million tokens per model (USD) for the ESTIMATED cost on expai/log and the dashboard. Anthropic's invoice
# is authoritative. Cache write is the 5-minute cache price (expai uses the default 5-minute cache). Not applied unless
# you configure them: the US-only inference_geo multiplier (1.1x), fast mode premiums (use Multiplier=).
# Models are matched by id, then without a date or -latest suffix, then by family and version; a model without an
# entry is priced with the Default* prices and shown as approximate. Override any entry in settings/override.
# Enabled=false hides costs everywhere.
Enabled=true
Currency=USD
PricesAsOf=2026-10-11
Source=https://platform.claude.com/docs/en/about-claude/pricing
Multiplier=1.0

# Fallback for models without an entry (the prices of the default model, Claude Sonnet 5.5)
DefaultInputPerMTok=2
DefaultCacheWritePerMTok=2.50
DefaultCacheReadPerMTok=0.10
DefaultOutputPerMTok=10

InputPerMTok[claude-fable-5-1]=10
CacheWritePerMTok[claude-fable-5-1]=12.50
CacheReadPerMTok[claude-fable-5-1]=0.25
OutputPerMTok[claude-fable-5-1]=50

InputPerMTok[claude-opus-5-5]=4
CacheWritePerMTok[claude-opus-5-5]=5
CacheReadPerMTok[claude-opus-5-5]=0.20
OutputPerMTok[claude-opus-5-5]=20

InputPerMTok[claude-opus-5]=5
CacheWritePerMTok[claude-opus-5]=6.25
CacheReadPerMTok[claude-opus-5]=0.50
OutputPerMTok[claude-opus-5]=25

InputPerMTok[claude-sonnet-5-5]=2
CacheWritePerMTok[claude-sonnet-5-5]=2.50
CacheReadPerMTok[claude-sonnet-5-5]=0.10
OutputPerMTok[claude-sonnet-5-5]=10

InputPerMTok[claude-sonnet-5]=2
CacheWritePerMTok[claude-sonnet-5]=2.50
CacheReadPerMTok[claude-sonnet-5]=0.20
OutputPerMTok[claude-sonnet-5]=10

InputPerMTok[claude-sonnet-4-6]=3
CacheWritePerMTok[claude-sonnet-4-6]=3.75
CacheReadPerMTok[claude-sonnet-4-6]=0.30
OutputPerMTok[claude-sonnet-4-6]=15

# Claude Haiku 5.5: two tiers; a request whose prompt (input + cache write + cache read tokens) is over the threshold
# is priced with the *Long prices
InputPerMTok[claude-haiku-5-5]=0.10
CacheWritePerMTok[claude-haiku-5-5]=0.125
CacheReadPerMTok[claude-haiku-5-5]=0.01
OutputPerMTok[claude-haiku-5-5]=0.50
LongContextThreshold[claude-haiku-5-5]=100000
InputPerMTokLong[claude-haiku-5-5]=0.50
CacheWritePerMTokLong[claude-haiku-5-5]=0.625
CacheReadPerMTokLong[claude-haiku-5-5]=0.05
OutputPerMTokLong[claude-haiku-5-5]=2.50

InputPerMTok[claude-haiku-4-5-20251001]=1
CacheWritePerMTok[claude-haiku-4-5-20251001]=1.25
CacheReadPerMTok[claude-haiku-4-5-20251001]=0.10
OutputPerMTok[claude-haiku-4-5-20251001]=5

[DataPolicy]
# Where content may be sent (doc/data-policy.md). Every provider has a Region (us, eu, local) and a Retention (none,
# zdr, 30d, unknown). A requirement names the regions content may go to, separated by ";"; a local provider meets
# every region requirement. Zero retention is met by Retention=none or zdr. Without any rule everything is allowed.
# Regions any content may go to (empty: every region):
DefaultRegions[]
# SectionRegions[<section id>]=eu;local   ClassRegions[<class identifier>]=local
SectionRegions[]
ClassRegions[]
# enabled: every request needs zero retention
ZeroRetention=disabled
ZeroRetentionSections[]
ZeroRetentionClasses[]

[ProbeSettings]
# The pass rate (percent) of a live probe (bin/php/expaiprobe.php --model=<alias> --live) a model needs before it may
# write drafts (WriteTools=enabled, ToolUse=native, and not WriteGate=trusted).
WritePassRate=90
# The node and class the probe tasks name (nothing is read or written: the tools are not run). Empty: the content root.
NodeID=
ClassIdentifier=folder

[LocalModelSettings]
# A local Ollama managed through expai/models (doc/local-models.md). The web process never runs commands: it writes a
# request into the spool directory, which a root-owned helper (share/systemd/) carries out.
SpoolDirectory=/var/lib/expai-llm/spool
StatusFile=/var/lib/expai-llm/status.json
# The models the view offers to pull and remove (the helper has its own allowlist, /etc/expai-llm/models.allow).
Models[]=qwen3.5:4b

[McpBridge]
# enabled: the panel can use audited read tools of the extension expmcp in process, as the current user, named
# exp__<tool> (policy expai/mcp with Server "exp"). Only tools audited in expAiMcp::BRIDGE_TOOLS are accepted.
Enabled=disabled
Tools[]=exp_content_query

[McpSettings]
# External MCP servers (streamable HTTP), each [McpServer_<id>] (id: a-z and 0-9, at most 20 characters).
Servers[]

# Example (not active until listed in Servers[]):
[McpServer_docs]
Label=Documentation search
# https only; http only for a loopback address with AllowLoopback=enabled
URL=https://mcp.example.org/mcp
# ONLY in settings/override
Token=
# The tools the panel may call; everything else the server offers is ignored.
AllowedTools[]=search_docs
Region=us
Retention=unknown
MaxResultBytes=16000
Timeout=20
ToolsCacheTTL=3600
ProtocolVersion=2025-06-18
AllowLoopback=disabled
AllowPrivate=disabled

*/ ?>
