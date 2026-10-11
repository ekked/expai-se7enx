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
Start, Stop, Pull, Remove and "Refresh the status", and a test prompt that measures tokens per second.

The web process never runs a command. A button writes a request `<id>.json` into `/var/lib/expai-llm/spool`
(`{action, model, requested_by_user_id, ts}`) and logs it to `var/log/expai-models.log`; `expai-llm-helper.path` starts
the root-owned helper, which handles one request at a time: fixed actions only (start, stop, pull, remove,
refresh_status), the model must be in `/etc/expai-llm/models.allow` (root-owned, not writable by others), requests
older than 10 minutes, larger than 4 KB or malformed are dropped, a pull needs 20 GB free, and every action goes to the
journal (`journalctl -u expai-llm-helper`). The helper writes `/var/lib/expai-llm/status.json` for the view.

`expai-llm-idle.timer` runs every 5 minutes and stops Ollama after 30 minutes without a loaded model (Ollama itself
unloads a model after `OLLAMA_KEEP_ALIVE`).

## Probe before writing

A local model reads only until it passes the probe ([probe.md](probe.md)):

```
php extension/expai/bin/php/expaiprobe.php --model=local.qwen3.5-4b --live -s admin --login=admin
```
