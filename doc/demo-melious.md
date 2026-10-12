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
  picker shows "Melious · EU" and the relative cost (`$`). Put the editor's own model back afterwards.

Cost of the whole run below on both servers (2026-10-12, second run after the probe change): about $0.04 on Melious
(EUR prices converted with the ECB reference rate), of which the probe of all four models was about $0.03, plus $0.03
for the three fallback answers of Claude Sonnet in step 8.

The screenshots show no real person: the users' names are replaced by "Demo Editor", e-mail addresses by a
placeholder address and IP addresses by 203.0.113.0, in the browser that took them (the product is not changed).

## 1. The models (Setup: Exponential Intelligence > Models, `expai/models`)

The card **Melious (melious)**: type `openai_chat / melious`, host `api.melious.ai`, Region **EU**, Retention
**zdr**, terms checked 2026-10-12, **Prices: 1 EUR = 1.1206 USD, ECB reference rate of 2026-10-09** (refreshed once a
day), key present. Four models, each with native tools, "context 1.0M tokens", the probe result, "may write" and the
relative cost (`443-01-models.png`).

| Model | Probe (15 tasks) | Drafts |
| --- | --- | --- |
| GLM 5.3 Flash | 14/15, 93 % (31.2 s) | may write |
| GLM 5.3 | 14/15, 93 % (24.9 s) | may write |
| DeepSeek V4 Flash 0731 | 14/15, 93 % (28.2 s) | may write |
| DeepSeek V4.1 Flash | 15/15, 100 % (29.0 s) | may write |

What to say: a model writes drafts only after it passed the probe with at least 90 %. Asked to create or change a
draft "now", these models first read the class definition or the object, as a careful editor would; the probe
answers that lookup with a scripted result and grades the call that follows ("create_draft (after 1 answered
lookup)"). The one task three of them miss is `paging`: they list the children with another read tool. (The first
run on 2026-10-12, which graded the first call only, gave 86 % and kept them read only.)

Press **Test prompt** next to GLM 5.3 Flash: a short poem, "time to first token 1.20 s · in: 32 tokens · out: 16
tokens, 70.50 tokens/s · total 1.40 s · cost < $0.01" (1.2 to 1.4 s to the first token, 3.2 to 3.5 s from the click
on both servers; the test prompt is streamed to measure it) (`443-02-models-test-prompt.png`).

## 2. A question and a draft in the Intelligence tab

Open the test article, tab **Exponential Intelligence**, ask *"In one sentence: what is this page about, and when
does the garden open?"*. The answer comes in 2.7 to 3.3 s, it read the page with one lookup, and the run line names
"DeepSeek V4 Flash 0731 (Melious)", the steps, the time, the tokens and "< $0.01" (`443-03-panel-question.png`).

Then *"Rewrite the intro of this article to sound friendlier (one sentence) and save it as a draft."* (3.1 s on both
servers): "Done — I rewrote the intro ... draft version 2". The draft list of the tab shows it with **What changed**
(the old and the new intro) (`443-04-panel-rewrite.png`). Nothing is published.

## 3. The edit form

Edit the article. On the title:

- the preset **Suggest title**: a title in 1.6 to 1.8 s, with "View record" and Undo (`443-05-edit-preset-title.png`);
- **Improve > More formal**: 1.6 to 2.0 s (`443-06-edit-improve-formal.png`);
- the field action **Suggest**: 1.7 to 1.9 s, "Filled in by Exponential Intelligence. Review it before you store the
  draft." with its reason (`443-07-edit-field-action.png`).

Undo each, then Discard the draft. These work with every model: they only fill the form, the editor stores it.

## 4. Vector

Open `expai/vector`, a new thread on the article, model DeepSeek V4 Flash 0731 (Melious), mode **Plan**, and ask
*"Make a plan with exactly one item: update the intro of this article so that it sounds friendlier."* The plan comes
in 6 to 8 s; the side panel's **Plan** tab shows the item (update, the article, intro) with the estimate
(`443-08-vector-plan.png`). **Approve**: the plan is "approved, running" at once (3.1 s) and its item runs in the queue
(`443-09-vector-plan-approved.png`).

A thread in **Write** mode with staged review: *"Change the title of this article to 'T385 reading garden opens 1
June'."* In 4.4 to 6.0 s Vector says "Staged the title change ... Review and apply", and the **Changes** tab shows the
staged change of the title (`443-10-vector-staged.png`). **Accept all**, then **Write accepted changes to drafts**:
"1 change(s) written to 1 draft(s)" in 4.6 to 4.7 s, the draft listed under "Drafts written" with Edit, View version
and What changed (`443-10b-vector-applied.png`). The published version is unchanged.

## 5. A chain

On the test folder's Intelligence tab, section **Write with a chain**: pick "Article on Melious: write, review,
approve" (the hint below now says "at most $0.10 per run", the budget of this chain, and the item count allows 2),
write the brief (*"A short article (about 120 words) for families: the town library opens a reading garden on 1 June,
forty seats, open daily nine to six in summer."*) and **Start the chain** (1.2 to 1.4 s; `443-11-chain-start.png`).
The run page shows the steps (`443-12-chain-run-started.png`). With the worker running every minute, within about
3 minutes: Write (DeepSeek V4 Flash, about 1000 tokens in, 900 to 2400 out), Review (GLM 5.3 Flash, score 78 and 88,
pass), **Editor approves** waiting with the draft (version 1) and its title, intro and body, cost so far < $0.01 of
at most $0.10 (`443-14-chain-run-checkpoint.png`). **Approve and continue**, **Ask for changes** or **Stop the run**
decide.

## 6. The translation sync

Open the bakery article (English with a German translation) after its English text was changed and published. In the
Intelligence tab, section **Translations**: **Check translations** says "Waits for an editor (patch), changed ratio
0.39" (`443-13-translations-check.png`); **Approve** (4.1 s) queues the patch (`443-13-translations-approve.png`);
within a minute the row says "Draft written (patch)" with **View draft**: the changed sentence translated by
DeepSeek V4 Flash on Melious (349 tokens in, 503 out) (`443-13-translations-show.png`).

## 7. What it cost and where it went

- **Requests and usage** (`expai/log/(provider)/melious`): every request with its action (panel, field, preset,
  improve, vector, queue.chain, queue.translate, test, probe), "melious EU", tokens, cached tokens, reasoning tokens and
  the cost Melious reported ("0.0002 USD (reported)"), converted with the ECB rate (`443-16-usage-log.png`).
- **Dashboard** (`expai/dashboard`, 7 days, site): the Models card lists each Melious model with "melious EU", its
  requests and success rate, median latency and speed, cost, the last test and the probe; "Top users" shows the
  masked name (`443-17-dashboard.png`).
- **EI: Summary** (`expai/summary`): the provider Melious with Region EU, Retention zdr, terms checked, the key's
  place, who may use it, this month's requests and cost, and the write gate per model (`443-18-summary.png`).
- **Audit > Console, channel ai**: one "Model request" event per request with the model alias and the node, the
  server (apache or velocity) (`8080-19-audit-ai.png`).

## 8. When Melious cannot answer

Shown by making the provider unreachable for a minute (a test-only BaseURL with a host that does not resolve, then
put back): the same question with GLM 5.3 Flash is answered by the site default, with the line *"Claude Sonnet 5.5
answered instead: GLM 5.3 Flash (Melious) could not be reached."* (1.4 to 5.5 s; `443-15-fallback-fallback.png`).
The usage log keeps the failed request with its error, and the answer's row names the model asked for and the reason
(unreachable). After the restore the next question is answered by Melious again (1.8 to 4.6 s;
`443-15-fallback-melious.png`).

## The partner's own machine

The Melious command line tool (`melious auth login`, `melious run "..." --model glm-5.3`, `melious launch` for a coding
tool) installs into the user's home directory; it is meant for the partner's own computer next to expmcp (the MCP
server of this installation), not for the server, and was not installed there.

## Open points

- `Retention=zdr` follows Melious' documentation; it is not a data processing agreement.
- A streamed answer of Melious carries no `billing_cost`: the test prompt is priced with the model's list prices
  instead of the reported cost.
- Rotate the key after the demo (it was shared in a chat).
