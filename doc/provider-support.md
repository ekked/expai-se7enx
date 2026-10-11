# Provider support: what is proven

State of 2026-10-11 (expai after 0.1.0, the provider registry). Three levels:

- **proven live**: run against the real service with a real answer.
- **proven against mock (docs-derived)**: run end to end against the mock servers of `tests/mock/server.php`, whose
  request validation and answers follow the provider's documentation (sources and assumptions are listed in that
  file); plus a real HTTPS request to the provider without a valid key.
- **not yet**: not run.

Live with the local model means: a real model running on the server, called through the same `openai_chat` adapter.

No keys exist for Hugging Face or OpenRouter yet. Nothing about them below is "proven live".

## Matrix

| Feature | Anthropic | Hugging Face router | OpenRouter | Local (Ollama + Qwen3.5 4B) |
| --- | --- | --- | --- | --- |
| Chat (the panel's step loop) | proven live | proven against mock | proven against mock | proven live (CLI step loop, provider timeout raised to 600 s) |
| Tools, native (incl. parallel calls) | proven live | proven against mock | proven against mock | proven live (incl. two calls in one answer) |
| Tools, prompted JSON | n/a | proven against mock (generic openai_chat model) | same adapter as HF | not needed (native works) |
| Malformed tool arguments | n/a | proven against mock | unit test (golden JSON) | not seen live |
| Drafts (create_draft in a test folder) | proven live | proven against mock (`WriteGate=trusted` in the test) | proven against mock (same) | proven live (after the probe opened the gate) |
| Write gate (no write tools without a passed live probe; forged calls refused) | trusted by configuration | proven against mock | proven against mock | proven live (opened by a 93% live probe) |
| Probe (15 tasks) | not run (trusted) | 15/15 against mock (mock results never open the gate) | 15/15 against mock | 14/15 live (93%; update_draft wrote the wrong value) |
| Field actions and "Fill the fields" | proven live | proven against mock | proven against mock | field action proven live (147 s); fill not run |
| Usage (input, output, cache, reasoning tokens) | proven live (unchanged) | proven against mock (no cache fields: not documented) | proven against mock (cached, cache write, reasoning) | proven live (Ollama reports cached prompt tokens) |
| Cost | proven live ([Pricing] by model id) | proven against mock (alias prices; the router reports none) | proven against mock (`usage.cost` stored as "reported") | proven live (0, alias prices) |
| Rate limits and retries | proven live (headers logged) | proven against mock (429 + long retry-after reported at once; 503) | proven against mock (429 + retry-after 1 retried once; 500 retried; X-RateLimit-* kept) | n/a (none sent); a timeout was reported correctly |
| Errors | proven live | 401 proven live (keyless and invalid key: `{"error":"Invalid username or password."}`, text names the provider); 402/429/503/timeout against mock | 401 proven live (`{"error":{"message":"No cookie auth credentials found","code":401}}` and "User not found."); 402, 502, error inside HTTP 200 against mock | timeout proven live |
| Data policy (region, retention) | proven in process | proven against mock (refused before any request) | same | unit test (local meets every region) |
| MCP: expmcp bridge (`exp__exp_content_query`) | proven live in process (permission-filtered, data policy, no tools without `expai/mcp`) | provider independent | provider independent | provider independent |
| MCP: external server (JSON and event stream, allowlist, SSRF guard, no writes after its result) | provider independent: proven against the mock MCP server; the SSRF checks by unit test | | | |

## The local model, measured (2026-10-11)

Ollama 0.40.3 (pinned release, sha256 checked), loopback only, the unit of `share/systemd/` (CPUQuota 400%,
CPUWeight 20, Nice 15, MemoryHigh 8G), model `qwen3.5:4b` (Apache-2.0, Q4_K_M, 3.3 GB) as `exp-qwen3.5:4b` (4 threads,
16k context), thinking off. Host: 12 vCPU KVM guest (AVX2), shared with busy sites, swapping (about 9.8 of 12 GB swap
used); 1-minute load 12 to 28 during the runs.

| Measure | Value |
| --- | --- |
| CPU the server got | 0.9 to 1.6 cores (Nice 15 on a loaded host; CPUWeight 100 for one test changed little: 0.59 instead of 0.50 tok/s) |
| Prompt processing | 5 to 7.5 tokens/s (fresh prompt) |
| Generation | 0.5 to 0.65 tokens/s |
| First call (model load + about 2.5k tokens of instructions and tools) | 381 s (probe), 213 s (panel, with a partly cached prefix) |
| Later calls (cached prefix) | 55 to 268 s per step |
| Probe | 14/15 (93%), 2840 s, about 190 s per task |
| Panel conversation (get_node, list_creatable_classes, create_draft, answer) | 4 steps, 653 s, draft written |
| Field action (rewrite of a title) | 147 s |
| Usage | in/out/cached tokens logged, region local, cost 0 |

Consequence: the adapter, the tools, the probe and the write gate work with a local open-weight model, but on this
host a step takes minutes. The panel's web request has a 280 s budget, and the first call can exceed it; the tests
above ran from the command line with the timeout raised to 600 s. The model picker and the panel say that the model is
slow, with the measured time per step. For editors, run the model on a separate CPU host with dedicated cores, or use
a hosted open-model provider (the same adapter). Ollama is stopped after the tests and is not started at boot.

## What the live keyless requests prove, and what they do not

Both services check authentication before the body: a deliberately broken body without a key got the same 401 as a
correct one. So the keyless round trip proves the URL, TLS, the method and the header placement, not that the body is
accepted. The body's shape is proven only against the docs-derived mocks until keys exist. With keys, run
`php extension/expai/bin/php/expaiprobe.php --model=<alias> --live -s <siteaccess>` and one panel conversation per
provider; then the columns above can become "proven live".

## Mock assumptions (not stated in the documentation)

- Hugging Face: the answer's `model` carries no routing suffix, `object` is `chat.completion`, no
  `prompt_tokens_details` / `completion_tokens_details`; bodies of 402/429/5xx and rate limit headers.
- OpenRouter: the top-level `provider` field on non-streaming answers; the error when no ZDR endpoint matches.

## How to repeat

```
php bin/php/ezexec.php extension/expai/tests/expaiprovider_test.php -s <siteaccess>        # unit, no network
php -S 127.0.0.1:18358 extension/expai/tests/mock/server.php &                             # the mocks
EXPAI_MOCK_URL=http://127.0.0.1:18358 EXPAI_TEST_NODE=<folder node> EXPAI_TEST_LOGIN=<editor> \
  php bin/php/ezexec.php extension/expai/tests/expaimock_e2e_test.php -s <siteaccess>
php bin/php/ezexec.php extension/expai/tests/expaicost_test.php -s <siteaccess>
```

Results of 2026-10-11: unit 135/135, end to end 100/100, cost 92/92, live probe of the local model 14/15.

## Lanes and batches (proof of 2026-10-11)

| What | Proof |
| --- | --- |
| Anthropic Message Batches (lane batch) | **live**: 2 requests on Claude Haiku 5.5 submitted as one batch, polled every 30 s, ended after 5.5 minutes, both answered, usage rows with lane batch, tier batch and price factor 50, the batch deleted at Anthropic right after its results were fetched (cost: about $0.000004) |
| Anthropic fast lane, its fallback to standard, a 529 with retry-after | mock (`tests/mock/batch_server.php`) |
| OpenAI Batch API (file upload, batch, output and error files, deletion of the files), flex and its "Resource Unavailable" escalation | mock, from the documentation |
| Gemini batchGenerateContent (inline), Mistral batch jobs (inline), OpenRouter batches | mock, from the documentation; the shape of one Mistral inline output and of an OpenRouter result item are not shown in the documentation (both readings are accepted) |

```
php -S 127.0.0.1:18359 extension/expai/tests/mock/batch_server.php &
EXPAI_BATCH_MOCK_URL=http://127.0.0.1:18359 EXPAI_MOCK_STATE=<dir> EXPAI_TEST_LOGIN=<editor> \
  php bin/php/ezexec.php extension/expai/tests/expaiqueue_e2e_test.php -s <siteaccess>
```

Results of 2026-10-11: lanes, batches and budgets end to end 167/167; packages 48/48 and the 200-object queued import
5/5.
