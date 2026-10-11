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

*/ ?>
