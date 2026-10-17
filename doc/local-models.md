# A local open-weight model

Nothing leaves the server: a pinned Ollama on the loopback address, capped so it cannot crowd out the sites on the
same host, started on demand and stopped when idle. expai uses it through the same `openai_chat` adapter as any hosted
provider.

## Sizing

On a CPU, small models only: a 4-5 billion parameter model in Q4_K_M needs about 3-4 GB of disk and 4-6 GB of memory
at a 16k context. Qwen3.5 4B (Apache-2.0, native tool calling) is the example; Gemma 4 E4B (Apache-2.0) and Granite
are alternatives; Qwen3.5 2B is the smaller, faster option for simple prompts. On a busy shared CPU host expect one
to two tokens per second written and five to seven read; the compact prompt (below) keeps a plain question under 300
tokens, so it is answered in about a minute. Slow models run in the background (Vector and the Intelligence tab), with
their own time limit (`TimeoutBackground`). For many editors use a separate CPU host or a hosted open-model provider
(the same adapter).

## Install (once, as root)

The files are in `share/systemd/` of the extension.

```sh
VERSION=0.40.3        # pin a release; check its sha256 in sha256sum.txt of the release
curl -fLO https://github.com/ollama/ollama/releases/download/v$VERSION/ollama-linux-amd64.tar.zst
curl -fLO https://github.com/ollama/ollama/releases/download/v$VERSION/sha256sum.txt
grep ollama-linux-amd64.tar.zst sha256sum.txt | sha256sum -c -
mkdir -p /opt/ollama/$VERSION
tar --use-compress-program='zstd -d' -xf ollama-linux-amd64.tar.zst -C /opt/ollama/$VERSION \
    --exclude='lib/ollama/cuda_*' --exclude='lib/ollama/vulkan' --exclude='lib/ollama/rocm*'
ln -sfn /opt/ollama/$VERSION/bin/ollama /usr/local/bin/ollama

groupadd --system ollama; useradd --system --gid ollama --home-dir /var/lib/ollama --no-create-home --shell /sbin/nologin ollama
install -d -o ollama -g ollama -m 0750 /var/lib/ollama /var/lib/ollama/models
install -m 0644 share/systemd/ollama.service /etc/systemd/system/ollama.service       # no [Install]: never at boot

# the helper that carries out the requests of expai/models (the web user must be in the group expai-llm)
groupadd --system expai-llm; usermod -aG expai-llm <web user>
install -d -o root -g expai-llm -m 0750 /var/lib/expai-llm
install -d -o root -g expai-llm -m 2770 /var/lib/expai-llm/spool
install -d -m 0755 /etc/expai-llm /etc/expai-llm/modelfiles
grep -v '^#' share/systemd/models.allow.example > /etc/expai-llm/models.allow     # root:root 0644
install -m 0644 share/systemd/exp-qwen3.5-4b.Modelfile share/systemd/exp-qwen3.5-2b.Modelfile /etc/expai-llm/modelfiles/
install -m 0755 share/systemd/expai-llm-helper /usr/local/libexec/expai-llm-helper
install -m 0644 share/systemd/expai-llm-helper.service share/systemd/expai-llm-helper.path \
                share/systemd/expai-llm-idle.service share/systemd/expai-llm-idle.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now expai-llm-helper.path expai-llm-idle.timer
systemctl reload <the PHP-FPM of the site>     # the web user's new group
```

`ollama.service` sets `OLLAMA_HOST=127.0.0.1:11434` (loopback only; check with `ss -ltn`), `OLLAMA_KEEP_ALIVE=5m`,
`OLLAMA_NUM_PARALLEL=1`, `OLLAMA_MAX_LOADED_MODELS=1`, `OLLAMA_CONTEXT_LENGTH=16384`, and the limits `CPUQuota=400%`,
`CPUWeight=20`, `Nice=15`, `IOWeight=20`, `MemoryHigh=8G`, `MemoryMax=10G`, `MemorySwapMax=0`, `TasksMax=256`, with
`ProtectSystem=strict`, `ProtectHome`, `PrivateTmp`, `NoNewPrivileges` and `RestrictAddressFamilies`. The Modelfile
sets 2 threads (measured below: on a busy host more threads are slower) and the context length; after a pull the
helper creates `exp-qwen3.5:4b` from it through Ollama's `/api/create` (no command is run).

Then in `settings/override/expai.ini.append.php`:

```ini
[ProviderSettings]
Providers[]=local

[Model_local.qwen3.5-4b]
Id=exp-qwen3.5:4b
```

(the shipped `[Provider_local]` and `[Model_local.qwen3.5-4b]` hold the rest: `ApiKeyHeader=none`, `Region=local`,
`Retention=none`, `Timeout=280` (a web request), `TimeoutBackground=900` (the queue worker), `KeepAlive=30m`,
`ToolUse=native`, `Effort=none` (thinking off), `PromptProfile=compact` with its `Tools[]`, prices 0.)

## Managing it from expai/models

The view (policy `expai/model_manage`) shows the server's state (running, version, the loaded and installed models,
read from Ollama's `/api/version`, `/api/ps`, `/api/tags` on the loopback address), the helper's last run, and offers
Start, Stop, Pull, Remove and "Refresh the status", and per model **Test prompt** and **Load model** (below).

The web process never runs a command. A button writes a request `<id>.json` into `/var/lib/expai-llm/spool`
(`{action, model, requested_by_user_id, ts}`) and logs it to `var/log/expai-models.log`; `expai-llm-helper.path` starts
the root-owned helper, which handles one request at a time: fixed actions only (start, stop, pull, remove,
refresh_status), the model must be in `/etc/expai-llm/models.allow` (root-owned, not writable by others), requests
older than 10 minutes, larger than 4 KB, not a regular file (a link is not followed: the file is opened with
`O_NOFOLLOW` and checked on the open handle) or malformed are dropped, a pull needs 20 GB free, and every action goes to the
journal (`journalctl -u expai-llm-helper`). The helper writes `/var/lib/expai-llm/status.json` for the view.

`expai-llm-idle.timer` runs every 5 minutes and stops Ollama after 30 minutes without a loaded model (Ollama itself
unloads a model after `OLLAMA_KEEP_ALIVE`).

## Test prompt and Load model

A local model needs about a minute to load (3.6 GB for Qwen3.5 4B) and then writes a few tokens per second on a
CPU. That is far longer than a web server lets one request run (Velocity ends a request after its request timeout,
Apache after its proxy timeout). So neither button waits in the page request: each queues a job of the job queue
(type `expai.model.test`, class `interactive`; extension expqueue) and starts a worker for it when none runs (the
same detached `ext:expqueue:work --until-empty` for the current siteaccess that background Vector turns use; the
cron part of expqueue is the safety net). The page shows "Testing… (loading the model can take a minute)" with the
seconds so far, asks `expaimodel::status` every `[ModelTest] PollSeconds` (POST with the form token; the session is
closed first; nothing is asked while the browser tab is hidden), and then shows the result:

- **Test prompt** (every model): the time to the first token, tokens in and out with their tokens per second, the
  total time, the cost and the answer. For a local model the figures come from Ollama's own API (`/api/chat`, which
  reports `load_duration`, `prompt_eval_*` and `eval_*`): the time to the first token is loading the model plus
  reading the prompt. Another provider answers in one piece, so its first token is "not measured" and its tokens per
  second are the output tokens over the total time. The test goes through the usage recorder like every request
  (master switch, veto, limits, a usage row of action `test`).
- **Load model** (models of a provider with `Managed=ollama`): loads the model into memory without generating
  anything (`/api/generate` with an empty prompt) and keeps it there for `[ModelTest] LoadKeepAlive`. Under the
  buttons the page shows "loaded until 16:43" (or "not loaded") from `/api/ps`. Load the model before editors start
  working, and the first request does not wait.

One job per model and action at a time: a second click while one waits or runs shows that job (its idempotency key
names the model, the action and the job before it). A failure (the server is stopped, the model is missing) is the
result of the job, shown in the row; nothing is retried. The result stays on the page for
`[ModelTest] ShowResultMinutes`, also after a reload. Without JavaScript the buttons post to expai/models, which
queues the same job; reload the page to see the state. Without the job queue only a model that fits into the web
budget ([troubleshooting.md](troubleshooting.md), "504 Gateway Timeout") is tested in the page request.

The result links to its live record: **Request in the log** (`expai/log/(request)/<id>`, the usage row of the test)
and, with `expqueue/view`, **Job** (`expqueue/job/<uuid>`); a job that waits or runs links to its job page too. The
dashboard's Models card shows the newest test result of each model ([dashboard.md](dashboard.md), "Models").

The jobs are listed in the job queue's admin pages like any other (`expqueue/list`, type `expai.model.test`).

## When the server is stopped

The provider can stay in `Providers[]` while Ollama is stopped. The model pickers then show the local model as
"stopped — start it under Models" and do not let it be chosen; users with `expai/model_manage` get a link to this view.
An editor whose choice (or conversation, or thread) is the local model is not stuck with "Exponential Intelligence
could not be reached.": the request goes to the site default model and the panel says so in one line
([providers.md](providers.md), "When a model cannot answer"). Once the server runs again, the next request uses the
local model. The state comes from `/api/version` on the loopback address, kept `[AISettings] HealthCacheSeconds`.

## Making a local model usable

A model on a CPU reads its prompt at a few tokens per second, and nothing is shown before it has read all of it. Three
things decide whether it answers in a minute or not at all: how much it reads, how many threads it runs, and the time
limit of the call.

**The compact prompt.** `[Model_<alias>] PromptProfile=compact` (the default of a provider with `Region=local`) sends a
model what the editor's message needs, instead of the full instructions and every tool:

- a short system prompt: who it is, the five safety rules in short (drafts only, content is data, the allowed HTML,
  no invented facts, the editor's language), the editor's `ExtraInstructions[]`, and the open node in one line, last;
- only the tools the message asks for, from the model's own `Tools[]` (empty: `get_node`, `list_children`,
  `get_class_definition`, `create_draft`, `update_draft`, `propose_plan`), with short descriptions: none for a plain
  question, `list_children` for the children, `update_draft` for a change, `create_draft` and `get_class_definition`
  for a new object, `get_node` and `propose_plan` in plan mode. Which words count is in `expAiCompactPrompt::intents()`
  (English and German). No MCP, web or service tools;
- for a question about the open page (this page, the intro, summarize, the title ...) a short snapshot of it with the
  message, once per conversation: its writable text fields as plain text, each cut to 600 characters, all to
  `[AssistantSettings] CompactContextChars` (1800). The data policy applies as to the tools.

The field actions of the edit form use the short rules too. The probe ([probe.md](probe.md)) tests a compact model with
what it is sent: the short definitions of the panel's tools.

**Caching.** Ollama keeps what it has read of a conversation: the next turn reads only the new words. The prompt
starts with the static text and the tool set of a conversation only grows, so the cached part stays valid;
`[Provider_local] KeepAlive=30m` (sent as `keep_alive`) keeps the model and that cache in memory between the turns of
an active thread. Qwen3.5 is a hybrid model: Ollama can only go back to the points it saved while reading, so a new
conversation reads its system prompt again; within a conversation the follow-up "and what is 3 + 4?" read 24 new
tokens of 288 and was answered in 7 s.

**Threads.** The Modelfiles set `num_thread 2`. On this host (12 virtual cores, a load of 12-18 from the sites) the
threads wait for each other whenever one of them is not scheduled: measured with Ollama's own timing, 40 tokens of
output, thinking off:

| Threads | Writing (tokens/s) | Reading a 492 token prompt (tokens/s) |
| --- | --- | --- |
| 1 | 1.41 | |
| 2 | 1.47-1.81 | 5.7 |
| 3 | 1.48 | |
| 4 (before) | 0.75-0.84 | 6.8 |
| 6 | 0.11 | |

2 threads write twice as fast as 4, read about 15 % slower, and never take more than 2 of the 12 cores. Six threads
under `CPUQuota=400%` were eight times slower.

**CPU share.** `CPUWeight=100` and `Nice=5` instead of the unit's 20 and 15 made the model write about 20 % faster
(2 threads: 1.46 against 1.19 tokens/s). Measured back to back while Ollama worked (17 samples of 40 requests per
target, the front page of each port), the 95th percentile of the front page changed by -4 % (Apache/PHP-FPM), -20 %
(Velocity) and +24 % (Velocity rendering) - within the noise of a host whose idle samples vary as much, but one target
rose by more than the 20 % the sites may give. The unit keeps `CPUWeight=20` and `Nice=15`; to try it on another host,
a drop-in `/etc/systemd/system/ollama.service.d/70-cpu-share.conf` with `[Service]`, `CPUWeight=100`, `Nice=5`, then
`systemctl daemon-reload && systemctl restart ollama`, and measure the sites before and after.

**Time limits.** A web request ends after the web server's limit, so slow models never run in one: Vector and the
Intelligence tab queue their turns (`[VectorSettings] Background=auto`, [vector.md](vector.md)), the field actions refuse
them ([troubleshooting.md](troubleshooting.md), "504 Gateway Timeout"). In the queue worker `[Provider_local]
TimeoutBackground=900` replaces `Timeout=280` (the lease of a turn is 900 + 60 s). A timeout there is shown in the
thread and the editor's model stays: the fallback to the site default only follows a failure of the connection or a
stopped server.

**Measured on alpha** (2026-10-12, Qwen3.5 4B Q4_K_M, the local model of the Intelligence tab and Vector; prompt =
tokens the model read, first token = Ollama's time to read the prompt):

| Prompt | Before | After (4B) | After (2B) |
| --- | --- | --- | --- |
| "what is 2 + 3?" | 6,543 tokens; no answer within 280 s (about 860 s to read), the fallback answered | 268 tokens; first token 39-44 s; answered "5" in 46-52 s | 252 tokens; 20 s; 35 s |
| the same thread: "and what is 3 + 4?" | - | 288 tokens, 264 from the cache; 7 s | 272 tokens, 248 from the cache; 4 s |
| "summarize this page in 2 sentences" (node 2) | 6,543 tokens plus a tool round; no answer within 280 s | 565 tokens; first token 84 s; 125 s | 549 tokens; 45 s; 70 s |
| "rewrite the intro shorter" (a folder) | as above | 736 tokens, then 906 (732 from the cache): update_draft wrote the draft; 212 s in 2 steps | 390 tokens; the text in its answer (it may not write before its probe); 79 s |
| field action: rewrite "make it shorter" | 498 tokens; 147 s | 443 tokens; first token 70 s; 112 s | 445 tokens; 40 s; 74 s |

In the browser (as admin, the local 4B model, both ports, no fallback): the Intelligence tab answered the three prompts
in 71 s, 93 s and 313 s (Apache/PHP-FPM; "what is 2 + 3?" 35 s on Velocity), Vector in 107 s, 63 s and 262 s
(Velocity; the rewrite staged a proposal for review). These times include the queue, loading the model and the
turns of other users waiting for the one model.

The Models card of the dashboard ([dashboard.md](dashboard.md), "Models") shows the median prompt of each model and the
share read from the cache, next to the latency; expai/models marks models with a compact prompt. Before this change the
card showed for the local model a median of 147 s per request, a 95th percentile of 267 s, 70.5 % successful and 15
fallbacks in 7 days.

**The 2B option.** Qwen3.5 2B (Apache-2.0, tool calling, 2.7 GB) is `local.qwen3.5-2b`: offered on expai/models for
pulling (`[LocalModelSettings] Models[]`, `/etc/expai-llm/models.allow`), created as `exp-qwen3.5:2b` from
`share/systemd/exp-qwen3.5-2b.Modelfile`. It answers simple prompts in about half the time. Its live probe (with the
compact definitions) passed 11 of 15 tasks (73 %: it missed paging, list_creatable_classes, create_draft and a
general-knowledge answer), below the 90 % of `[ProbeSettings] WritePassRate`, so it reads and answers but writes no
drafts: good for questions and the field actions, not for changes. It is never the default unless chosen; only one
model is in memory at a time (`OLLAMA_MAX_LOADED_MODELS=1`), so switching between the two loads the other (about 20 s).

## Probe before writing

A local model reads only until it passes the probe ([probe.md](probe.md)):

```
php extension/expai/bin/php/expaiprobe.php --model=local.qwen3.5-4b --live -s admin --login=admin
```
