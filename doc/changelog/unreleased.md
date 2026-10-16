# Unreleased

## Provider-neutral

expai works with any provider (Anthropic, every OpenAI-compatible service, local models); it no longer presents itself
as one provider's product.

- The line "Powered by Claude from Anthropic" is gone from the Intelligence tab, the dashboard, the log and the edit
  form, and from the tooltips and the version label ("This version was written with Exponential Intelligence."). The
  translations follow; the messages that only carried the line are removed.
- The extension's name in `ezinfo.php`, `extension.xml` and `composer.json` is "Exponential Intelligence".
- The system prompt names the model that answers and its provider ("powered by the model ... via ..."), also for the
  Anthropic models; when the model is not known it names none, instead of claiming Claude.
- Draft log entries, chain drafts, Vector drafts and the dashboard record the provider of the model actually used
  (`expAiProviderRegistry::providerOf()`), never a hard-coded `anthropic`. The data policy of a queued Intelligence
  tab turn is checked for the model's own provider; an unknown model meets no region requirement.
- The dashboard shows the Anthropic-specific notes ("Anthropic's invoice is authoritative", the spend limit in the
  Anthropic Console) only where an Anthropic provider is set up; otherwise the price note names "the provider".
- Unchanged on purpose: the Anthropic provider itself, the pricing table, the 0.0.1.0 settings (an empty
  `Providers[]` still builds the provider `anthropic` from `[AISettings]`), and the column defaults of the schema.

## Robust calls to reasoning models and OpenAI-compatible services

Measured with gpt-oss-120b and Qwen models on an OpenAI-compatible service (mittwald AI Hosting) and with long-running
integrations of the same models: reasoning models spend output tokens thinking, answer with reasoning only, leak
reasoning markup, and gateways reject optional parameters.

- **Reasoning leftovers**: `expAiText::stripReasoning()` handles only real reasoning markup: the final channel of a
  text in the Harmony format (known chat template tokens in it end or are removed), complete `<think>` blocks at the
  start, an open `<think>` at the start (no answer), and a lone `</think>` followed by a line break (Qwen; the text
  after it is the answer). A text that mentions `<think>` or a `<|...|>` token keeps it. Used by the
  OpenAI-compatible, prompted-tool and Ollama providers. Reasoning fields never become the answer.
- **Empty answers**: an answer without text and without a tool call is asked for once more ("Answer the editor in words
  now, or call a tool."), then reported as "The model gave no answer"; it no longer ends a turn as done. After tools
  ran in the turn (lookups, drafts written), an empty final answer ends the turn without words. An empty answer cut off
  at the token limit is not asked again with the same limit; the editor is told to raise MaxTokens or lower Effort.
  The second request fails like the first (a model that cannot be reached hands the step to the site default).
- **Cut answers**: chain steps, alt text, field actions and "Fill the fields" do not parse an answer cut off at the
  token limit; the editor is told to raise MaxTokens or lower Effort. A queued answer cut off at the token limit
  stores no value, so the translation sync and the Vector plan items report the cut instead of a missing part.
- **JSON**: `expAiText::extractJson()` finds the JSON in prose (the whole text or a fenced block, which may be a list;
  else the longest balanced object that decodes) instead of cutting from the first `{` to the last `}`; also for answers stored by the queue.
- **Token budgets**: reasoning models (capability `reasoning_effort`, an `Effort`, or `Reasoning=true`) get at least
  1000 output tokens in the model test, alt text, plan batches and chains; the model test used 120.
- **Retries**: up to two retries within one deadline on 408/425/429 (also without `retry-after`), 5xx and 529, and on a
  connection that was never made; never after a timeout or an answer that broke off, nor after a gateway timeout
  (408/502/504) of an attempt that took longer than 30 seconds (the request may already be billed). `retry-after` as an HTTP date is understood. The Anthropic provider uses the same retries.
- **Optional parameters**: a 400 that refuses `reasoning_effort`, `reasoning` or `stream_options` as unknown or
  unsupported is sent once more without it (with a debug notice); a 400 for an invalid value of the field is a
  configuration error and is shown as it is.
- **Error texts of the Anthropic provider** name the provider (its `Label`, otherwise "Anthropic"), like those of every
  other provider: "Anthropic is busy or over its limit." instead of "Exponential Intelligence is busy ...".
- **Streams** that end without `[DONE]` and without a finish reason are incomplete; error objects in a stream are
  reported.
- **Error texts** mask keys, tokens, secrets, passwords and authorization values in more forms.
- **Write gate**: the probe tasks `use_result`, `injection`, `create_draft` and `update_draft` are mandatory
  (`[ProbeSettings] MandatoryTasks[]`); a high pass rate with one of them failed keeps the gate closed. "empty answer"
  is reported as its own reason.
- **Locale**: the translation sync reason and the probe output use `%F` (a decimal comma under a German locale broke
  the stored reason).

## Structure is never lost silently

Field actions, presets, the Improve menu and "Fill the fields" replaced the whole value of a rich text field with the
answer, which only has `p, h2-h4, strong, em, ul, ol, li, a href`: embedded objects and images, tables, custom tags,
anchors, literals, internal links and formatting attributes were lost, and ezautosave stored the loss.

- `expai_structure.js` (new, loaded before expai.js) counts the structure-bearing elements of an ezxmltext field and of
  the answer, in the HTML of the Online Editor and of Tiptap and in the simplified XML alike: embedded objects, tables,
  table cells, header cells, merged cells, custom tags, anchors, literals, internal links, and each formatting or link
  attribute (`target`, `title`, a class ... also on external links). CDATA and the content of `script`, `style` and
  literals are not read as markup; an unclosed embed does not hide the rest of the value.
- Without the detector, every answer to a rich text field is shown for review; it is never written unseen.
- When the answer has fewer of any kind, it is not written. The field's bar shows a review instead: the answer, what
  would be lost ("2 embedded objects, 1 table would be lost."), and **Apply anyway**, **Keep the original** and
  **Copy text**. Only **Apply anyway** writes, with Undo as before.
- **Stale guard**: when a field changed while the answer was written, the answer is not written either; the same
  review says so. This applies to every datatype, also to the parts of an xrowmetadata field.
- `ExpAi.guardedApply()` is the extension point for scripts that write answers into fields themselves.
- New strings in the context `extension/expai/edit`; a node test of the detector in `tests/js/`
  ([edit-view-integration.md](../edit-view-integration.md#structure-is-never-lost-silently)).

## Conversations, limits and the Anthropic request

- **Data policy on a change of model**: a conversation notes every object whose content a tool returned. When it goes
  on with another model (the editor's choice, the fallback), the objects are checked against the new provider's data
  policy; when one may not go there, the new model gets the editor's words only, and the exchange says so. The
  fallback prefers a model whose provider may take everything the conversation read.
- **Fallback in the middle of a tool loop**: the next model gets the drafts the open exchange wrote so far and is told
  not to create them again.
- **One step at a time per conversation**: a second step while one runs is refused ("A step of this conversation is
  already running.") instead of running the tools twice; the lock (`expai_conversation.busy_owner`, `busy_until`) ends
  with the step or expires on its own.
- **Limits under parallel requests**: with a limit set, every request reserves its estimate before it is sent
  (`expai_usage_log.reserved_tokens`, `reserved_micros`) and is settled after it; the check counts the reservations,
  so parallel requests cannot pass a limit together. `[Limits] UnpricedCost=block` (default) refuses a model without
  a price while a cost limit applies; `ignore` counts it as free. Every failure leaves a usage row, also one that is
  not a provider error.
- **Prices**: Claude Sonnet 5.5 cache reads cost 0.20 per million tokens (was 0.10), and so does the fallback price
  `DefaultCacheReadPerMTok`.
- **Refusal fallback** (`fallbacks: "default"`) only for the models that accept it (Claude Fable 5.1, Mythos 5.1,
  Opus 5.5, Opus 5, Sonnet 5.5), never for Claude Haiku 5.5; capabilities `refusal_fallback` and
  `no_refusal_fallback` decide for other models. The usage log records the category of a refusal.
- **Tool calls**: `[AISettings] MaxTokens` is 16000 (was 8000); Anthropic tool definitions go with `strict: true` where
  the schema allows it (`StrictTools`); the arguments of every call are checked against the tool's schema before it
  runs. The probe asks with the panel's instructions (with `ExtraInstructions[]`); `read_node` and `two_nodes` require
  the node ids they name.
- **Writes**: after an external tool's result (web, MCP), drafts stay locked while the result is part of the
  conversation's window (until a new conversation, or a window with the editor's words only), not only for one turn. The write tools write only
  inside the panel's subtree or the thread's scope (`[AssistantSettings] WriteScope=subtree`; `site` lifts it).
- **search_content**: a new read tool, full-text search over the content (eZ Find when active, else the kernel's
  search), as the current user and through the data policy ([tools.md](../tools.md#search_content)).
- **Storage**: the `*_micros` columns of expai are `bigint`; a long queue payload is bound as a value on Oracle instead
  of a string literal (which ends at 4000 bytes).

## Upgrading

- Live probe results stored before this update in which a mandatory task (`use_result`, `injection`, `create_draft`,
  `update_draft`) failed now close the write gate of that model, also when the pass rate was high. Run the probe again
  for those models: `php extension/expai/bin/php/expaiprobe.php --model=<alias> --live` (`--list` shows the stored
  results; [probe.md](../probe.md)).
- Reasoning models get at least 1000 output tokens per request in the model test, alt text, plan batches and chains;
  a lower `MaxTokens` is raised for them, which can make those requests cost more.
- Mandatory: run `php extension/expai/bin/php/expaischema.php --install -s <siteaccess>` with the update (on MySQL /
  MariaDB the widening rebuilds `expai_usage_log` and blocks writes to it meanwhile; see database.md): it adds
  `expai_conversation.busy_owner`, `busy_until`, `expai_usage_log.reserved_tokens`, `reserved_micros` (the code reads
  them) and widens the `*_micros` columns to `bigint` on MySQL / MariaDB and PostgreSQL.
- The write tools of the panel no longer write outside the open node's subtree; set `[AssistantSettings]
  WriteScope=site` for the earlier behaviour.

## Admin interface: polling, dark mode, accessibility, small fixes

- **Polling** (the panel and Vector follow a queued turn): after five failed polls in a row the loop stops with a visible
  message ("The connection to the run was lost. Reload the page to follow it.") instead of retrying silently; the
  interval grows 6, 12, 24, 48 s up to 60 s, as on the dashboard. A 401 or 403 stops at once ("Your session has
  expired ..."). A hidden tab does not poll and continues when it is shown. `ExpAi.api()` rejects with `kind: 'http'`,
  `status` and `expired: true` for 401/403.
- **Dark mode in CSS**: the dark tokens apply under `html[data-a4-theme="dark"]`, so bars
  and previews created after the page loaded are right at once; `exp-ai-on-dark` stays for other dark designs, `exp-ai-on-light` is new (admin4l). Accent text uses `--a4-accent-text`; `--exp-ai-warn`, `--exp-ai-danger`
  and `--exp-ai-ok` follow the admin4 tokens; z-indexes follow `--a4-z-*`; forced colours and reduced motion are handled
  ([customization.md](../customization.md#colors-and-look)).
- **JSON in script blocks**: the template operator `expai_json` replaces `json_encode` for every
  `<script type="application/json">` block (`<`, `>`, `&`, `'` and `"` are escaped, invalid UTF-8 is substituted, not
  dropped). A block that does not parse is reported in the browser console.
- **Accessibility**: the conversation log is no longer a live region (a long answer or a reloaded history was read out
  whole); step messages and the end of a turn go through a polite status line of their own. The palette: `aria-expanded`
  follows the results, groups are labelled, the number of results is announced, the page behind does not scroll, a key
  held down or an IME composition does not act. The palette's local-storage key is per user.
- **Cmd+K on macOS** is detected case-insensitively (`macOS`, `MacIntel`) and shows as ⌘K; Cmd/Ctrl never also fires a
  single-key shortcut.
- **Fixes**: the collapsed conversation header shows the text without Markdown marks; a preset whose model returned no
  text (a "Write summary" on an empty field) shows the model's note and marks nothing as filled (`refused` in the answer
  of `expaipreset::preset`); a draft chip in the history is checked (HEAD) before it is followed and shows a note for a
  discarded draft; the chain page does not suggest starting a chain without `expqueue`; "unknown" retentions are
  translated (`expai_retention`); "Job" and "Request in the log" of the model test are translated (they sat in the wrong
  context); the key placement warning accepts a key in any settings directory that git does not track, configurable
  with `[ProviderSettings] KeyLocations[]` (default `settings/override`, `extension/*/settings`).
- **Alternative text report**: the images without alt text under a node are read with one query per 200 objects instead
  of one data map per object and language.
- **Script cache busting**: the scripts the templates load directly carry `?v=<change time>`; for
  `BackendJavaScriptList` the packer's `AppendLastModifiedTime` does the same in production
  ([architecture.md](../architecture.md)).
- **Workers**: `expAiQueueKick` detaches with `setsid` only where the system has it (else `nohup` alone) and reports a
  start where the shell gives no process id as failed (this detects only that the shell could not be started, not a worker
  that exits at once); `--allow-root-user` is passed only with `[VectorSettings] WorkerAllowRoot=enabled`
  (default `disabled`); `worker-kick.json` is created with mode 0660.
- **web_fetch** uses `expAiHttpClient` (with its `resolve` option for the checked address) instead of a second curl
  client; the SSRF guard is unchanged. The client gained the options `user_agent`, `accept_language`, `encoding`,
  `header_max` and `truncate`.
- **list_children**: `total` counts the children of the given class, `has_more` says whether another page follows and
  `withheld` counts the children of the page that the data policy keeps away. `get_tree_context` counts through the same
  function.
- Requires PHP 8.1 or later (`composer.json` `^8.1`); `imagedestroy()` is no longer called (no effect since PHP 8.0,
  deprecated in 8.5).

## Upgrading (admin interface)

- New setting `[VectorSettings] WorkerAllowRoot` (default `disabled`): a site whose PHP runs as root and relies on kicked
  workers enables it.
- New setting `[ProviderSettings] KeyLocations[]`; a site that put a key into a git-ignored extension of secrets needs
  no change (the default accepts it).
- New strings in the contexts `panel`, `vector`, `preset`, `keyboard` and `chain` (ger-DE and eng-US included).

