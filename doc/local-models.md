# A local open-weight model

Nothing leaves the server: a pinned Ollama on the loopback address, capped so it cannot crowd out the sites on the
same host, started on demand and stopped when idle. expai uses it through the same `openai_chat` adapter as any hosted
provider.

## Sizing

On a CPU, small models only: a 4-5 billion parameter model in Q4_K_M needs about 3-4 GB of disk and 4-6 GB of memory
at a 16k context. Qwen3.5 4B (Apache-2.0, native tool calling) is the example; Gemma 4 E4B (Apache-2.0) and Granite
are alternatives. Expect a few tokens per second on four cores, and a first panel call of a minute or more (the
instructions and tool definitions are several thousand tokens): give the provider a longer `Timeout` (and check the
web server's, PHP-FPM's and Velocity's request limits), and keep `ContextWindowTurns` small. For many editors use a
separate CPU host or a hosted open-model provider (the same adapter).

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
install -m 0644 share/systemd/exp-qwen3.5-4b.Modelfile /etc/expai-llm/modelfiles/
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
sets 4 threads and the context length; after a pull the helper creates `exp-qwen3.5:4b` from it through Ollama's `/api/create` (no command is run).

Then in `settings/override/expai.ini.append.php`:

```ini
[ProviderSettings]
Providers[]=local

[Model_local.qwen3.5-4b]
Id=exp-qwen3.5:4b
```

(the shipped `[Provider_local]` and `[Model_local.qwen3.5-4b]` hold the rest: `ApiKeyHeader=none`, `Region=local`,
`Retention=none`, `Timeout=280`, `ToolUse=native`, `Effort=none` (thinking off), prices 0.)

## Managing it from expai/models

The view (policy `expai/model_manage`) shows the server's state (running, version, the loaded and installed models,
read from Ollama's `/api/version`, `/api/ps`, `/api/tags` on the loopback address), the helper's last run, and offers
Start, Stop, Pull, Remove and "Refresh the status", and per model **Test prompt** and **Load model** (below).

The web process never runs a command. A button writes a request `<id>.json` into `/var/lib/expai-llm/spool`
(`{action, model, requested_by_user_id, ts}`) and logs it to `var/log/expai-models.log`; `expai-llm-helper.path` starts
the root-owned helper, which handles one request at a time: fixed actions only (start, stop, pull, remove,
refresh_status), the model must be in `/etc/expai-llm/models.allow` (root-owned, not writable by others), requests
older than 10 minutes, larger than 4 KB or malformed are dropped, a pull needs 20 GB free, and every action goes to the
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

## Probe before writing

A local model reads only until it passes the probe ([probe.md](probe.md)):

```
php extension/expai/bin/php/expaiprobe.php --model=local.qwen3.5-4b --live -s admin --login=admin
```
