# Lanes and provider batches

A **lane** is one way of sending a request to a provider: at the standard price and speed, slower and cheaper
(flex), together with many others and answered within hours at half the price (batch), or faster at a premium
(fast). The panel and the field actions always use the standard lane, in the editor's request. Requests that run in
the job queue (the extension expqueue) take the lane the lane policy chooses.

## What the providers offer (read 2026-10-11)

| Provider | Lane | Price | Latency | Retention of the lane | Shipped |
| --- | --- | --- | --- | --- | --- |
| Anthropic | `standard` | list price | seconds | the provider's (30 days unless zero data retention) | enabled |
| Anthropic | `batch` (Message Batches) | 50 % | most within 1 h; requests expire after 24 h (not billed) | requests and results up to 29 days; expai deletes the batch after fetching | enabled |
| Anthropic | `fast` (research preview, access on request) | 2x on Opus 5.5 ($8/$40 instead of $4/$20) | faster | the provider's | disabled; Opus 5.5 only |
| OpenAI | `flex` (`service_tier: flex`) | 50 % | minutes; "429 Resource Unavailable" is not charged | the provider's | enabled (when the provider is active) |
| OpenAI | `batch` (Batch API) | 50 % | within 24 h | files until deleted (expai deletes them) | enabled |
| Google Gemini | `batch` (inline `batchGenerateContent`) | 50 % | 24 h target | results 6 weeks; expai deletes the batch | enabled |
| Google Gemini | `flex` | 50 % | 1-15 min, can be shed (429/503) | the provider's | disabled: documented for the native API only |
| Mistral | `batch` (inline batch jobs) | 50 % | within the job's timeout (24 h) | files until deleted | enabled |
| OpenRouter | `batch` | "typically 50 %"; the `:batch` model variants have their own prices | 24 h | results 30 days unless deleted (expai deletes) | disabled until you confirm the price of your model |
| OpenRouter | `flex` (`service_tier: flex`, OpenAI and Google endpoints) | depends on the endpoint | slower | the provider's | disabled (no confirmed price) |
| Hugging Face router, local models | `standard` only | | | | |

Where a provider's page gives no price, the lane ships with `PriceMultiplier` empty or `Enabled=false`: expai never
uses a lane whose price it would have to guess. Sources are in each `[Lane_<provider>.<lane>]` group (`Source`,
`Verified`).

## Settings

A lane is `[Lane_<provider>.<lane>]` in expai.ini ([configuration.md](configuration.md#lanes)). Every provider has the
lane `standard`, with or without a group. `[Provider_<id>] Lanes[]` (when set) restricts the lanes of a provider,
`[Model_<alias>] Lanes[]` those of a model, and a lane's `Models[]` restricts it to some models (the Anthropic fast
lane names Opus 5.5).

```ini
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
```

The concurrency of a lane is the expqueue resource `<provider>:<lane>`: `expqueue.ini [QueueResource_anthropic:standard]
MaxConcurrent=4` (the extension ships values for its lanes). The rate-limit headers of every answer
(`anthropic-ratelimit-*`, `x-ratelimit-*`, `retry-after`) are handed to that resource: a 429 or 529 cools it down for
the retry-after and halves its concurrency for a while, a low remaining count lowers it, a 401/403 pauses it.

## Which lane a request takes

`expAiLanePolicy::choose()`, in this order:

1. The order of `[LanePolicy] Order[<purpose>]` (the request's purpose, e.g. `bulk`, `translate`), else
   `ClassOrder[<queue class>]`, else `Order[default]`. `standard` is always the last resort.
2. Only usable lanes of the model: enabled, with a price.
3. `fast` is never taken from an order: the request must ask for it (`lane` option) and the user needs the policy
   `expai/lane_fast`.
4. A priority at or above `UrgentPriority` (80) skips `flex` and `batch`.
5. A deadline skips lanes whose `ExpectedLatency` (with `StrictDeadline=enabled`: a batch lane's `MaxLatency`) does not
   fit.
6. The data policy: the content of the request (its objects) must be allowed on the lane. A lane has its own
   retention: a batch lane that keeps requests for 29 days never takes content that needs zero retention, even when
   the provider's standard lane has zero data retention.

`./console ext:expai:queue choose --model=<alias> --purpose=bulk` shows the choice and why the other lanes were not
taken; `./console ext:expai:queue lanes` lists every lane of every model.

## Queued requests

```php
$r = expAiQueue::request( 'anthropic.claude-haiku-5-5',
    array( 'system' => 'You summarize articles.', 'messages' => array( array( 'role' => 'user', 'content' => $text ) ), 'max_tokens' => 400 ),
    array( 'purpose' => 'bulk', 'objects' => array( $objectID ), 'group' => $group, 'label' => 'Summary of ' . $name ) );
// $r['job'] the expqueue job (expai.request), $r['lane'], $r['estimate'] and $r['reserved'] (micros), $r['held'] (why, or '')
```

A queued request is one structured call without tools (what batch and flex lanes can run): `system`, `messages` (role
and text), `max_tokens`, `output_schema`. Options: `purpose`, `class`, `priority`, `group`, `lane` (a name or `auto`),
`deadline`, `allow_fast`, `objects` (content object ids the request contains, for the data policy), `node_id`,
`label`, `est_output`, `idempotency_key`, `user_id`, `apply` and `apply_params` (an applier of `[QueueSettings]
Appliers[]` that writes the answer somewhere, implementing `expAiRequestApplier`).

The job's result is the answer: `text`, `value` (the decoded JSON when the request had an output schema),
`stop_reason`, `model`, `lane`, `service_tier`, `tokens`, `cost_micros`, `usage_log_id`. The answer is written to the
job's checkpoint before it is applied, so a job that runs again after a crash never pays twice.

### On a sync lane (standard, flex, fast)

The worker checks the budget again ([budgets.md](budgets.md)) and the data policy, then calls the provider with the
lane's request parameters (`speed: fast`, `service_tier: flex`) and headers. The usage row records the lane, the tier
the provider reported (`usage.speed`, `usage.service_tier`, `service_tier`), the job and the billed price factor: a
fast or flex request the provider served at its standard tier is billed at 100 %.

- 429, 503, 529: retried after the provider's retry-after (with the queue's backoff); with a `FallbackLane` the job
  moves to it at once (the fast lane falls back to standard; a flex lane with `OnUnavailable=escalate` too)
- 401, 402, 403: retried after 5 minutes; the resource is paused until someone resumes it (the key or the billing
  needs a person)
- 400, 404, 413: the job ends dead (the request itself is wrong)
- a `[Limits]` limit that is reached: the job is held like a budget hold

### On a batch lane

1. The request job waits (deferred, without using an attempt) and queues an `expai.batch.submit` job for its model and
   lane, `BatchCollectSeconds` later (one per period).
2. The submit job takes up to `MaxRequestsPerBatch` waiting requests of that model and lane (compare-and-set: a request
   is never in two batches), builds each body with the provider's own adapter, submits the batch with `custom_id` =
   the job uuid, records it in `expai_batch`, marks the requests (`external_ref expai-batch:<id>`) and queues
   `expai.batch.poll`. More waiting requests: the next submit job is queued at once.
3. The poll job asks for the batch every `PollSeconds`. When it ended it fetches the results and, per request:
   - succeeded: a usage row (lane `batch`, the lane's price factor, the batch id, the job), the reservation settled,
     the answer stored in the request job's checkpoint, the job woken to apply it;
   - errored: a request the provider calls invalid ends dead; any other error waits for the next batch;
   - expired or canceled: the job moves to `ExpiredLane` (standard) and runs there.
   Then the batch is deleted at the provider (`DeleteAfterFetch`), before its retention ends.
4. A batch whose requests were all canceled is canceled at the provider.

The batch API shapes (classes `expAiBatchAnthropic`, `expAiBatchOpenAi`, `expAiBatchGemini`, `expAiBatchMistral`,
`expAiBatchOpenRouter`):

| Shape | Submit | Poll | Results | Delete |
| --- | --- | --- | --- | --- |
| anthropic | `POST /v1/messages/batches` `{requests: [{custom_id, params}]}` | `GET .../batches/{id}`: `processing_status` | JSONL at `results_url` | `DELETE .../batches/{id}` |
| openai | JSONL file `POST /v1/files` (purpose batch), `POST /v1/batches` | `GET /v1/batches/{id}`: `status` | `GET /v1/files/{output_file_id}/content` and the error file | the input, output and error files |
| gemini | `POST {BatchURL}/models/{model}:batchGenerateContent` (inline, `metadata.key`) | `GET {BatchURL}/batches/{id}`: `metadata.state` | `response.inlinedResponses` | `DELETE {BatchURL}/batches/{id}` |
| mistral | `POST /v1/batch/jobs` (inline `requests`) | `GET /v1/batch/jobs/{id}?inline=true` | `outputs`, else the output file | the job and its files |
| openrouter | `POST /api/v1/batches` (`requests`, `model`, `endpoint`) | `GET /api/v1/batches/{id}` | `results` of the batch | `DELETE /api/v1/batches/{id}` |

Gemini's batch API is its native API; its key is sent as `x-goog-api-key` (the provider's `ApiKey`).

## Admin and command line

- Setup > EI: Budgets (`expai/budgets`): the lanes of every model, the provider
  batches with their state and whether they were deleted at the provider.
- The expqueue pages show the jobs (`expai.request`, `expai.batch.submit`, `expai.batch.poll`) with their lane, events
  and results.
- `./console ext:expai:queue lanes|choose|jobs|batches|budget|resume|request` ([cli](#command-line)).

### Command line

| Command | Does |
| --- | --- |
| `ext:expai:queue lanes [--model=<alias>]` | the lanes of every model |
| `ext:expai:queue choose --model=<alias> [--purpose] [--class] [--priority] [--deadline=<s>]` | the lane a request would take |
| `ext:expai:queue jobs [--state] [--limit]` | expai jobs with lane, model, estimate, reservation and cost |
| `ext:expai:queue batches` | the provider batches |
| `ext:expai:queue request --login=<user> --model=<alias> --prompt=<text> [--lane] [--group=<label>] [--group-budget=<amount>]` | queues one request |

## What is proven

- Live: the Anthropic Message Batches round trip (submit, poll, results, delete) with two requests on Claude Haiku 5.5
  ([provider-support.md](provider-support.md)).
- Against mock servers built from the documentation (`tests/mock/batch_server.php`): every batch shape (submit, poll,
  results with a succeeded, an expired and an invalid request, delete), the fast lane and its fallback, flex and its
  "Resource Unavailable" escalation, a 529 with retry-after cooling the resource down
  (`tests/expaiqueue_e2e_test.php`).
