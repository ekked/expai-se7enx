# Provider support: what is proven

State of 2026-10-11 (expai after 0.1.0, the provider registry). Three levels:

- **proven live**: run against the real service with a real answer.
- **proven against mock (docs-derived)**: run end to end against the mock servers of `tests/mock/server.php`, whose
  request validation and answers follow the provider's documentation (sources and assumptions are listed in that
  file); plus a real HTTPS request to the provider without a valid key.
- **not yet**: not run.

No keys exist for Hugging Face or OpenRouter yet. Nothing about them below is "proven live".

## Matrix

| Feature | Anthropic | Hugging Face router | OpenRouter | Local (Ollama + Qwen3.5 4B) |
| --- | --- | --- | --- | --- |
| Chat (the panel's step loop) | proven live | proven against mock | proven against mock | not yet |
| Tools, native (incl. parallel calls) | proven live | proven against mock | proven against mock | not yet |
| Tools, prompted JSON | n/a | proven against mock (generic openai_chat model) | same adapter as HF | not yet |
| Malformed tool arguments | n/a | proven against mock | unit test (golden JSON) | not yet |
| Drafts (create_draft in a test folder) | proven live | proven against mock (`WriteGate=trusted` in the test) | proven against mock (same) | not yet |
| Write gate (no write tools without a passed live probe; forged calls refused) | trusted by configuration | proven against mock | proven against mock | not yet |
| Probe (15 tasks) | not run (trusted) | 15/15 against mock (mock results never open the gate) | 15/15 against mock | not yet |
| Field actions and "Fill the fields" | proven live | proven against mock | proven against mock | not yet |
| Usage (input, output, cache, reasoning tokens) | proven live (unchanged) | proven against mock (no cache fields: not documented) | proven against mock (cached, cache write, reasoning) | not yet |
| Cost | proven live ([Pricing] by model id) | proven against mock (alias prices; the router reports none) | proven against mock (`usage.cost` stored as "reported") | priced 0 by configuration |
| Rate limits and retries | proven live (headers logged) | proven against mock (429 + long retry-after reported at once; 503) | proven against mock (429 + retry-after 1 retried once; 500 retried; X-RateLimit-* kept) | not yet |
| Errors | proven live | 401 proven live (keyless and invalid key: `{"error":"Invalid username or password."}`, text names the provider); 402/429/503/timeout against mock | 401 proven live (`{"error":{"message":"No cookie auth credentials found","code":401}}` and "User not found."); 402, 502, error inside HTTP 200 against mock | not yet |
| Data policy (region, retention) | proven in process | proven against mock (refused before any request) | same | unit test (local meets every region) |
| MCP: expmcp bridge (`exp__exp_content_query`) | proven live in process (permission-filtered, data policy, no tools without `expai/mcp`) | provider independent | provider independent | provider independent |
| MCP: external server (JSON and event stream, allowlist, SSRF guard, no writes after its result) | provider independent: proven against the mock MCP server; the SSRF checks by unit test | | | |

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

Results of 2026-10-11: unit 135/135, end to end 100/100, cost 92/92.
