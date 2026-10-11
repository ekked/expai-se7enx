# Providers and models

expai speaks one internal format (the shape of the Anthropic Messages API: `tool_use` and `tool_result` blocks)
everywhere: the panel's tool loop, the stored conversations, the limits and the usage log. A provider is an adapter
that translates both ways. Two adapters are built in:

| Type | For | Class |
| --- | --- | --- |
| `anthropic` | the Anthropic Messages API (and Anthropic-compatible servers with `AnthropicExtras=disabled`) | `expAiAnthropicProvider` |
| `openai_chat` | OpenAI Chat Completions and every compatible server: Hugging Face Inference Providers (router), OpenRouter, Mistral, xAI, Ollama, llama.cpp `llama-server` (with `--jinja`), vLLM, LM Studio, Azure OpenAI | `expAiOpenAiChatProvider` |
| `custom` | your own class implementing `expAiProvider` ([extending.md](extending.md)) | `Class=` |

What is proven against which provider is in [provider-support.md](provider-support.md).

## Registry

```ini
# settings/override/expai.ini.append.php
[ProviderSettings]
Providers[]=openrouter
DefaultModel=anthropic.claude-sonnet-5-5

[Provider_openrouter]
ApiKey=sk-or-...
TermsCheckedAt=2026-10-11
```

`anthropic` is always built from `[AISettings]` unless you add a `[Provider_anthropic]` group: an installation of
0.1.0 keeps working without a change, its models become the aliases `anthropic.<id>`, and a stored model choice with a
bare id (`claude-opus-5-5`) is read as that alias. The extension ships example groups for Hugging Face, OpenRouter,
Mistral and a local Ollama (`[Provider_huggingface]` ...) with example models; they are inactive until listed in
`Providers[]`. Every setting is in [configuration.md](configuration.md).

Keys belong in `settings/override` only (mode 0640). expai/models shows "key present", never the key, and warns about
a key in any other file. Error texts and the log never contain the key; a key-like string in a provider's error
message is replaced by `[redacted]`.

## How the openai_chat adapter translates

| expai (canonical) | Chat Completions |
| --- | --- |
| `system` | the first message `{role: system}` |
| assistant `text` + `tool_use {id, name, input}` | `{role: assistant, content, tool_calls: [{id, type: function, function: {name, arguments: "<JSON>"}}]}` (thinking blocks of another provider are not sent) |
| user `tool_result {tool_use_id, content, is_error}` | one `{role: tool, tool_call_id, content}` each (`Error: ` in front of an error; Mistral: with `name`), then the user's text |
| `tools [{name, description, input_schema}]` | `[{type: function, function: {name, description, parameters}}]` |
| `tool_choice none / any / auto / tool` | `"none"` / `"required"` (Mistral `"any"`) / `"auto"` / `{type: function, function: {name}}`; a server without tool_choice (Ollama) gets no tools on a call that must answer in words |
| `output_schema` | `response_format: {type: json_schema, ...}` (`Capabilities[]=json_schema`) or `{type: json_object}` (`json_object`); otherwise the instructions ask for JSON and the answer is read tolerantly (code fences, text around the object) |
| image blocks (base64, alt text) | user content parts `{type: image_url, image_url: {url: "data:<type>;base64,..."}}` for models with `Capabilities[]=vision` ([alt-text.md](alt-text.md)) |
| `Effort` | `reasoning_effort` (OpenRouter: `reasoning: {effort}`), with `Capabilities[]=reasoning_effort`; `none` switches thinking off |

The answer: `choices[0].message` becomes text and `tool_use` blocks. Arguments are decoded; arguments that are not a
JSON object are answered with an error asking for valid JSON (the call is never run). A call without an id gets one
(Mistral: 9 letters and digits). `finish_reason` `tool_calls` → `tool_use`, `length` → `max_tokens`, `stop` →
`end_turn` (or `tool_use` when calls came with it, as Ollama does), `content_filter` → `refusal`, `error` → an error.
An error object inside an HTTP 200 answer (OpenRouter) is an error. `<think>...</think>` in the text is removed.

Usage: `prompt_tokens` minus `prompt_tokens_details.cached_tokens` and `cache_write_tokens` is the input (as Anthropic
counts it), the cached tokens are cache reads, `completion_tokens` the output (it includes reasoning),
`completion_tokens_details.reasoning_tokens` is stored as reasoning tokens, and `usage.cost` (OpenRouter) as the
reported cost.

The message as the provider sent it is kept as `native_json` next to the canonical `content_json`, and sent back
unchanged only to the same provider and model (some providers need their reasoning fields back); arguments that were
not JSON are sent back as `{}`.

## A conversation belongs to its model

A panel conversation is bound to the model alias and provider it runs on (`expai_conversation.model_alias`,
`provider`). When the editor switches model, the conversation goes on from a fresh window: the earlier exchanges as
plain words ([full-view-panel.md](full-view-panel.md), the context window), no thinking blocks or provider state, and
instructions and tools for the new model. The history the panel shows is unchanged.

## Choosing a model

The picker (dashboard, panel, edit form) groups the models by provider with the region, the relative cost (`free`,
`$`, `$$`, `$$$` by the output price), "slow" for a local model, "prompted tools" / "no tools", and "read only" for a
model that may not write. It offers the site default, the models of `[ProviderSettings] ProvidersWithoutPolicy[]`
(default `anthropic`), and the models of the providers the policy `expai/model_use` allows (limitation Provider).
A model posted that is not on that list is refused ([permissions.md](permissions.md)).

A model whose provider cannot be reached right now is shown with its state and cannot be chosen (the site default
always can): "stopped — start it under Models" for a local server (`Managed=ollama`) that does not run, with a link to
expai/models for users with `expai/model_manage` ("stopped" for everyone else), and "unreachable" for a provider on
the loopback address that refuses connections or a provider whose last request failed at the connection. The state is
read the way expai/models reads it (Ollama's `/api/version` on the loopback address; never by running a command) and
kept `[AISettings] HealthCacheSeconds` (default 30) per process, so the picker adds no noticeable time. The panel's
select is part of the cached full view: expai.js marks it from `expai::load`; Vector's select from `expai::states`.

## When a model cannot answer

With `[AISettings] FallbackToDefaultModel=enabled` (the default), a request whose model (the editor's choice, a
thread's model, the model a conversation is bound to) cannot answer goes to the site default model instead:

| Reason | When |
| --- | --- |
| stopped | a local server (`Managed=ollama`) does not run: seen before the request, or its connection is refused |
| unreachable | connection refused, host not resolved, TLS failure (curl errors), or the provider was marked unreachable |
| timeout | not a single byte came back within the provider's `Timeout` |
| not allowed | the user may not use the model any more (`expai/model_use`, `AllowUserModelChoice`, `AllowedModels[]`) |
| removed | the model is not in the registry any more |
| data policy | the data policy keeps the content away from the model's provider |

When the default may not take the content either (the user's permissions, the data policy), the first of the user's
allowed models that may answers; when none can, the editor gets a message naming the model and why ("Qwen3.5 4B
(local) is stopped, and no other model you may use can answer for this content."). A request that asked for the site
default itself never moves to another provider. Failures that would repeat on any model (the default's own provider
refusing access, a request or answer that is too long, a refusal, any HTTP error of a reachable provider) are shown as
before.

The conversation goes on with the default as after a change of model (the earlier exchanges compacted into a fresh
window, see above). The panel, the field actions, "Fill the fields", Vector and a request started from the dashboard
("Start writing") show one line: "Claude Sonnet 5.5 answered instead: Qwen3.5 4B (local) is stopped." The exchange keeps
it in the history. The usage log row of the answer names the model asked for (`requested_model_alias`) and the reason
(`fallback_reason`); a request that failed at the connection keeps its own row with the error
([usage-tracking.md](usage-tracking.md)). The choice of model stays as it was: once the local server runs again, the
next request goes to it.

## Tool modes and the write gate

| `ToolUse` | The panel | Drafts |
| --- | --- | --- |
| `native` | the API's tool calling | with `WriteTools=enabled` and a passed probe (or `WriteGate=trusted`) |
| `prompted` | the tools are described in the instructions; the model answers with exactly one JSON object `{"tool", "input"}` or `{"answer"}`, which is checked against the tool's schema; one retry with the error (it counts against `MaxRequestsPerTurn`), then the text is the answer | never |
| `none` | no tools; the instructions carry a read-only snapshot of the node | never |

The gate is enforced twice: the write tools are not in the definitions sent to the model, and a write tool the
model names anyway is refused when it is run. Field actions only fill the edit form and are offered with every model.
See [probe.md](probe.md).

## Errors, retries, rate limits

401/403 "refused the request (access)", 402 "no credit left", 404 "does not know the configured model", 408/504 "did
not answer in time", 413 "too long", 429/503/529 "busy or over its limit": each names the provider's `Label`; the log
row has the status, the provider's error type and message. One retry: after a 429/503/529 whose `retry-after` is at
most `RetryAfterMax` seconds, and after a 500/502/504 or a failed connection; never after a timeout. The response
headers of `RateLimitHeaders[]` are stored with the row.

## Examples

Hugging Face Inference Providers: `BaseURL=https://router.huggingface.co/v1`, a fine-grained token with "Make calls to
Inference Providers", model ids such as `openai/gpt-oss-120b:fastest` (`:cheapest`, `:preferred`, `:<provider>`).
Pin one inference provider (`:<provider>`) when you need a known data processor; the router does not report a cost,
so give the model prices or the log shows "cost unknown".

OpenRouter: `BaseURL=https://openrouter.ai/api/v1`, `Headers[]=X-OpenRouter-Title: ...`, and
`ExtraBody={"provider":{"data_collection":"deny","zdr":true}}` to keep requests away from endpoints that store or
train on prompts. Every answer reports its cost, which the log shows as "reported".

Mistral (EU): `Flavor=mistral`, `BaseURL=https://api.mistral.ai/v1`, `Region=eu`.

A local Ollama: `Flavor=ollama`, `BaseURL=http://127.0.0.1:11434/v1`, `ApiKeyHeader=none`, `Region=local`,
`Retention=none`, a longer `Timeout`; see [local-models.md](local-models.md).
