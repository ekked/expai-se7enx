# Demo: Exponential Intelligence on Melious

A script for showing expai with [Melious](https://melious.ai) (EU inference of open-weight models) to someone who uses
Melious: what to click, in which order, what they see and how long it takes. Measured on the test installation on 2026-10-12 on both
servers (Apache on :443 and Velocity on :8080) at 960 px and 200 % zoom; the setup is in [providers.md](providers.md#melious).

The screenshots named below (`<port>-<nn>-<name>.png`) were taken during that run; they are kept on the test installation in
`var/tmp/t385-demo-screenshots/` (not part of the extension).

## Before the demo

- `melious` is in `[ProviderSettings] Providers[]` and its key is in settings/override (expai/models shows
  "Key: present"). The admin has the policy `expai/model_use` for the provider (the Administrator role has it).
- A hidden test folder with an article for the panel and the edit form, and an article with a German translation
  for the translation sync.
- The chain "Article on Melious: write, review, approve" (DeepSeek V4 Flash 0731 writes, GLM 5.3 Flash reviews, an
  editor approves) is defined in the test installation's settings/override (`[Chain_melious]`, see [chains.md](chains.md)).
- For the translation sync on Melious set `[TranslationSync] Model=melious.deepseek-v4-flash-0731` for the demo
  (otherwise it uses the site default) and clear the INI cache.
- The job queue's worker runs every minute (cron): chains, plan items and translations are queued jobs.
- Choose the model once: in the Intelligence tab or on the dashboard pick **DeepSeek V4 Flash 0731 (Melious)**. The
  picker shows "Melious · EU", the relative cost (`$`) and "read only" (see step 1). Put the editor's own model back
  afterwards.

Cost of the whole run below on both servers: about $0.04 on Melious (EUR prices converted at 1.16), plus $0.035 for
the four fallback answers of Claude Sonnet in step 8. The probe of all four models cost about $0.03 of that.

## 1. The models (Setup: Exponential Intelligence > Models, `expai/models`)

The card **Melious (melious)**: type `openai_chat / melious`, host `api.melious.ai`, Region **EU**, Retention
**zdr**, terms checked 2026-10-12, key present. Four models, each with native tools, "context 1.0M tokens", the
probe result and the relative cost (`443-01-models.png`).

| Model | Probe (15 tasks) | Drafts |
| --- | --- | --- |
| GLM 5.3 Flash | 13/15, 86 % (80.7 s) | read only |
| GLM 5.3 | 13/15, 86 % (29.3 s) | read only |
| DeepSeek V4 Flash 0731 | 13/15, 86 % (29.0 s) | read only |
| DeepSeek V4.1 Flash | 13/15, 86 % (29.7 s) | read only |

What to say: a model writes drafts only after it passed the probe with at least 90 %. All four miss the same kind of
task: asked to create or change a draft "now", they first read the class definition or the object (or list the
children with another read tool). That is careful, but the probe grades the first call, so they stay read only:
they answer, plan and fill the edit form, and the editor saves.

Press **Test prompt** next to GLM 5.3 Flash: a short poem, "in: 32 tokens, out: 15 tokens, total 1.7 s, cost < $0.01"
(3.3 s from the click on :443; 6.3 s on :8080 under load) (`443-02-models-test-prompt.png`).

## 2. A question in the Intelligence tab

Open the test article, tab **Exponential Intelligence**, ask *"In one sentence: what is this page about, and when
does the garden open?"*. The answer comes in 1.8 to 3.3 s, it read the page with one lookup, and the run line says
"DeepSeek V4 Flash 0731 (Melious) · 2 step(s) · 2.8 s · 12k tokens · < $0.01" (`443-03-panel-question.png`).

Then *"Rewrite the intro of this article to sound friendlier (one sentence) and save it as a draft."* (2.4 to 2.9 s):
the model says it cannot save drafts itself and writes the friendlier intro into its answer to copy into the edit
form (`443-04-panel-rewrite.png`). This is the write gate at work.

## 3. The edit form

Edit the article. On the title:

- the preset **Suggest title**: a title in 1.9 s (11.6 s once on :8080 while the server was busy), with "View record"
  and Undo (`443-05-edit-preset-title.png`);
- **Improve > More formal**: 3.1 s (7.5 s on :8080) (`443-06-edit-improve-formal.png`);
- the field action **Suggest**: 1.8 to 2.0 s, "Filled in by Exponential Intelligence. Review it before you store the
  draft." with its reason (`443-07-edit-field-action.png`).

Undo each, then Discard the draft. These work with every model: they only fill the form, the editor stores it.

## 4. Vector

Open `expai/vector`, a new thread on the article, model DeepSeek V4 Flash 0731 (Melious), mode **Plan**, and ask
*"Make a plan with exactly one item: update the intro of this article so that it sounds friendlier."* The plan comes
in about 6 s; the side panel's **Plan** tab shows the item (update, the article, intro) with the estimate
(`443-08-vector-plan.png`). **Approve**: the plan is "approved, running" at once and its item ran in the queue
within 6 s (`443-09-vector-plan-approved.png`). Being read only, the item proposes no change; with a model that
passed the probe the Changes tab would show the staged diff to accept or reject.

A thread in **Write** mode with staged review answers a change request in about 5.5 s with the new text, not a
staged change, for the same reason (`443-10-vector-staged.png`).

## 5. A chain

On the test folder's Intelligence tab, section **Write with a chain**: pick "Article on Melious: write, review,
approve", write the brief (*"A short article (about 120 words) for families: the town library opens a reading garden
on 1 June, forty seats, open daily nine to six in summer."*) and **Start the chain** (1 to 2.6 s; `443-11-chain-start.png`).
The run page shows the steps (`443-12-chain-run-started.png`). With the worker running every minute, after about
3 minutes: Write (DeepSeek V4 Flash, 1017 / 1039 tokens), Review (GLM 5.3 Flash, score 85, pass), **Editor approves**
waiting, with the draft (version 1) and its title, intro and body, cost so far < $0.01 of at most $0.10
(`8080-14-chain-run-checkpoint.png`). The chain writes the draft through its own code, so the write gate does not
apply. **Approve and continue**, **Ask for changes** or **Stop the run** decide.

## 6. The translation sync

Open the bakery article (English with a German translation) after its English text was changed and published. In the
Intelligence tab, section **Translations**: **Check translations** (1.6 s) says "Waits for an editor (patch), changed
ratio 0.39" (`443-13-translations-check.png`); **Approve** queues the patch (`443-13-translations-approve.png`); within
a minute the row says "Draft written (patch)" with **View draft**: the changed sentence translated by DeepSeek V4
Flash on Melious (349 tokens in, 836 out) (`443-13-translations-show.png`).

## 7. What it cost and where it went

- **Requests and usage** (`expai/log/(provider)/melious`): every request with its action (panel, field, preset,
  improve, vector, queue.chain, queue.translate, test, probe), "melious EU", tokens, cached tokens, reasoning tokens and
  the cost Melious reported ("0.0002 USD (reported)") (`443-16-usage-log.png`).
- **Dashboard** (`expai/dashboard`, 7 days, site): the Models card lists each Melious model with "melious EU", its
  requests and success rate, median latency and speed (DeepSeek V4 Flash 0731: 1.6 s, 67.6 tokens/s, 79 % of the prompt
  from the cache; GLM 5.3 Flash: 2.3 s, "replaced by the fallback 4 times: unreachable" from step 8), cost and the
  probe (`443-17-dashboard.png`).
- **EI: Summary** (`expai/summary`): the provider Melious with Region EU, Retention zdr, terms checked, the key's
  place, who may use it, this month's requests and cost, and the write gate per model (`443-18-summary.png`).
- **Audit > Console, channel ai**: one "Model request" event per request, e.g.
  "expai_request 3019 melious.glm-5.3-flash → node 34481", the server (apache or velocity) (`8080-19-audit-ai.png`).

## 8. When Melious cannot answer

Shown by making the provider unreachable for a minute (a test-only BaseURL with a host that does not resolve, then
put back): the same question with GLM 5.3 Flash is answered by the site default, with the line *"Claude Sonnet 5.5
answered instead: GLM 5.3 Flash (Melious) could not be reached."* (1.7 to 8.5 s; `443-15-fallback-fallback.png`). The
usage log keeps the failed request with its error and the answer's row names the model asked for and the reason
(unreachable). After the restore the next question is answered by Melious again (`443-15-fallback-melious.png`).

## The partner's own machine

The Melious command line tool (`melious auth login`, `melious run "..." --model glm-5.3`, `melious launch` for a coding
tool) installs into the user's home directory; it is meant for the partner's own computer next to expmcp (the MCP
server of this installation), not for the server, and was not installed there.

## Open points

- The probe grades the first call of a task: the four Melious models look things up first and stay read only. The
  task `create_draft` is marked `follow_ok` in the probe but a lookup first is still graded as a failure; answering
  the lookup with a scripted result and grading the next call would measure what the panel does. A decision for the
  owner, not changed for this demo.
- `Retention=zdr` follows Melious' documentation; it is not a data processing agreement.
- The EUR to USD rate (1.16) is an approximation for the estimate; set the real one in `[Pricing] ExchangeRates[EUR]`.
- Rotate the key after the demo (it was shared in a chat).
