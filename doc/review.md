# Staged review: review the changes before they are written

In a Vector thread with **Review: Staged** (the default, and always for bulk work) the write tools do not write
drafts. `create_draft` and `update_draft` **stage proposals**: one per object, language and field, new objects
included (owner decision). The editor reviews them in the *Changes* tab, part by part and field by field, and only the
accepted parts are written into drafts. Nothing is ever published. Small edits in the Intelligence tab keep writing
drafts directly ([full-view-panel.md](full-view-panel.md)); what such a draft changed can be seen, and reverted part
by part, afterwards ([Direct mode: what changed, and revert](#direct-mode-what-changed-and-revert)).

## What a proposal is

Table `expai_change` ([database.md](database.md), class `expAiChange`):

| Column | Meaning |
| --- | --- |
| `contentobject_id` | the object; 0 for a proposed new object, whose fields share a `new_key` (with `parent_node_id`, `class_identifier`) |
| `language_code`, `attribute_identifier`, `datatype` | the field |
| `base_version`, `base_hash` | the published version the proposal was compared with, and the sha256 of its value |
| `proposed` | the value in storage form: rich text (`ezxmltext`) is converted through the kernel's input parser when it is staged, so invalid markup is a tool error the model can fix at once; other datatypes are checked as the datatype would check them |
| `hunks_json` | the parts of the change (from the diff engine, [diff.md](diff.md)) with the editor's decision per part |
| `decision` | `pending` (a part waits), `accepted`, `rejected`, `partial` (some parts accepted), `applied`, `conflict`, `stale` (replaced) |
| `applied_version` | the draft the change went into |

Rules when staging (`expAiChangeStager`):

- the target must lie in the thread's **scope** (a scope node, inside a scope subtree, a scope class and language),
  and the editor must be allowed to **edit** it in that language (or to **create** the class under the parent);
- a value equal to the published one is not staged ("unchanged");
- staging the same object, language and field again **replaces** the open proposal and keeps the decisions of the
  parts that did not change (part ids are stable); `create_draft` with the returned `new_key` revises a new object;
- without an approved plan a thread stages **one object in one language** (`[VectorSettings] BulkObjectThreshold`,
  `BulkLanguageThreshold`); more is bulk work and the tool asks the model to call `propose_plan` first
  ([plan-mode.md](plan-mode.md));
- at most `[VectorSettings] MaxStagedChanges` (500) open changes per thread;
- a field another run still works on (an open proposal of a queued or running run, in any thread) is **held**: it is
  not staged, the tool result says "held by run N" ([parallel-runs.md](parallel-runs.md#one-field-one-run));
- datatypes the assistant does not write (images, relations, files) are skipped, as with drafts.

The tool result tells the model the change ids and that nothing is written yet; the step reads "Proposed changes to
... for review".

## The Changes tab

Grouped by object (or **New** object, with its class and the location it goes to) and language, then field in class
order, then **parts** (hunks): each part shows the text around it, what goes (red) and what comes (green), word by
word. Rich text is shown as readable text, never as markup; links, formatting and embeds are kept by the merge.

| Action | Mouse | Keys |
| --- | --- | --- |
| next / previous part | | `j` / `k` (while the tab is shown) |
| accept / reject a part | Accept, Reject (and Undecide) | `a` / `x`; the next waiting part comes next |
| accept / reject a field | Accept field, Reject field | `Shift+A` / `Shift+X` |
| edit the proposed text of a part | Edit, Save | `e` (plain text fields; rich text: accept, then edit the draft in the edit form) |
| everything open | Accept all, Reject all (5 seconds to undo) | |
| write | Write accepted changes to drafts | |

The tab label counts the parts that wait; while any wait, the thread is in *Needs you*. A part that inserts or removes
a table cell alone cannot be accepted alone (accept the parts of the whole table).

## Conflicts

Before anything is written, the published value of every field is compared with the base the proposal was made
against. When it differs (a colleague edited and published meanwhile), the change becomes a **conflict** and is not
written. The tab also marks a field whose base changed before you apply. Three ways out:

- **Keep current**: the change is rejected;
- **Compare with the current version** (`change_restage`): the proposal is compared again with the current version;
  parts that are still the same keep their decision;
- **Ask the AI to rebase** (`rebase`): the changes are set aside and the model is asked, as the next turn, to read
  the current version and stage its changes again.

## Apply

`expAiChangeApplier` writes per object and language: the accepted parts are merged into the published value at block
level (`expAiDiff::apply()` with the accepted part ids; rejected parts keep the published text) and written as the
editor with `expAiDiff::apply( ..., array( 'write' => true, 'source' => 'vector', 'thread_id' => ..., 'change_id' => ... ) )`:
into the draft this thread wrote for that object before, else a new draft. A proposed new object is created as a draft
under its parent first (content/create of the editor). Every draft is logged in `expai_draft_log` (source `vector`,
the thread and the change) and carries the Exponential Intelligence label; it appears under *Drafts written* with
*Edit* and *View version*, and is published or sent for approval as usual. Required fields that are still empty are
named.

## Direct mode: what changed, and revert

When the assistant writes a draft **directly** (the Intelligence tab, a Vector thread with **Review: Direct**), there
is no review before the write. The review comes afterwards: every draft in the tab's draft list and under *Drafts
written* of a thread has **What changed**. It opens a dialog with the diff of the draft against the version it was
based on, field by field and part by part, with the same diff engine as the Changes tab ([diff.md](diff.md)): what
goes in red, what comes in green, word by word; rich text as readable text, never as markup (every text is escaped in
the browser). **Revert** takes one part back, **Revert field** a whole field; both write into the **same draft**.
Nothing is published, and the published version stays as it is.

| Action | Mouse | Keys (while the dialog is open) |
| --- | --- | --- |
| next / previous part | a click on the part | `j` / `k` |
| revert the part | Revert | `x` |
| revert the field | Revert field | `Shift+X` |
| close | Close | `Esc` |

- **The base** is the newest published (or archived) version below the draft, which is the version a new draft is
  copied from; for a new object it is an empty object (every part is an insert). When another version was published
  after the draft was written, the dialog says so: reverting brings back the text of the base, not of the newer
  version.
- **Reverting a part** (`expAiDraftRevert`, classes/diff/expaidraftrevert.php): the diff is computed again from the
  draft as it is now; the field becomes the base with every other part of the field accepted
  (`expAiDiff::apply()` with the parts the editor keeps), so the part taken back has the base text and the others keep
  the draft's text. **Reverting a field** writes the base value of the field as it is. The values are written with
  `expAiDiff::writeDraft()` into the same version (`reuse_version`), logged in `expai_draft_log` with source
  `revert` (the thread of the draft, "Reverted N part(s) of ..."). A part that inserts or removes a table cell alone
  cannot be taken back alone: revert the field.
- **Conflicts**: the dialog keeps a fingerprint of every field's value. When a field to revert changed in the draft
  since the dialog was opened (edited in the edit form, written again by the assistant), or the part is no longer
  there, nothing is written; the dialog shows the diff as it is now and says so. Revert again from there.
- **Who**: the editor the draft was written for; a draft of a thread only the thread's **owner** (the approvers who
  may open a thread do not get the dialog). Reverting also needs, at that moment, the version to be still a draft of
  the editor and content/edit of the object in its language; otherwise the dialog shows the diff with the reason and
  without Revert. A version that was published or sent for approval shows what it changed and cannot be reverted.
- The browser calls `expairevert::changes` (read only: `object_id`, `version`) and `expairevert::revert` (`hunks`
  and `fields` as JSON lists, `seen`: identifier => fingerprint), both POST with the form token and the policy
  `expai/write` (ezjscore.ini `[ezjscServer_expairevert]`, `expAiDraftRevertServerFunctions`). The dialog is
  expai_whatchanged.js with expai_whatchanged.css (design.ini `BackendJavaScriptList[]`, `BackendCSSFileList[]`);
  any button with `data-exp-ai-what-changed="<object id>:<version>"` opens it, and the event `draft:reverted`
  (`ExpAi.on()`) follows each revert.

Drafts that came from the staged review have the same dialog; their parts were accepted already, so it is mostly
useful for drafts written directly.

## Permissions

- Staging, deciding and applying need `expai/write` and `expai/vector` (and its `Review` limitation allows `staged`
  or `direct`, [permissions.md](permissions.md)); only the **owner** of a thread decides and applies. The approvers
  who may open a thread see its changes read-only.
- Writing checks the editor's own **edit** (or create) permission per object and language at apply time; without it
  the object is listed with the reason and nothing is written.
- An approver who opens a thread of someone else sees only what they may **read** themselves: the Changes tab leaves
  out the changes of an object the viewer may not read (for a proposed new object: its parent node; a new object
  without a parent is left out too) and says how many (`withheld` in the answer); a plan item on such a target, or
  one whose target cannot be told, keeps its action and state but shows `#<id>` (or nothing) instead of the name,
  and no instruction or fields; the drafts of an exchange on such objects, or with a key that cannot be read (and
  the notes of what became of them), are left out of the conversation. The owner sees the thread unfiltered. The
  Drafts list and the restore preview of a checkpoint name only readable objects, for everyone
  (`expAiVector::changeGroups()`, `changeHidden()`, `visiblePlanItems()`, `visibleHistory()`, `drafts()`).
- The words of a thread cannot be told apart by object, so an approver does not get them at all (fail closed): not
  the thread's title (the owner's first request), not the requests, answers, steps and turn records of the
  conversation, not the queued follow-ups (only how many wait), not the labels and last steps of the plan's runs, not the labels of the checkpoints, not the plan's
  assumptions, questions and risks. Of the events (poll `events` and the stream `expai/stream`) an approver gets
  states, ids, counts and costs (`status`, `cost`, `plan`, `run`, `checkpoint`, `followup`, only their known keys),
  `draft`, `change` and `held` only for objects they may read, and nothing of `text`, `step_end`, `error` or any
  other kind (`expAiVector::visibleEvent()`, `visibleRuns()`, `visibleCheckpoints()`, `visiblePlan()`). Every entry
  point checks first that the user may open the thread at all (owner, or approver of a version it wrote).

## Tests

`php bin/php/ezexec.php extension/expai/tests/expaivectorreview_test.php -s <admin siteaccess>`: staging through
both tools, hunks, re-staging, the bulk guard, unchanged and skipped fields, edits, decisions, apply (accepted and
rejected parts, the same draft reused, the draft log), conflicts and restage, a new object, permissions, plan mode and
the master switch and veto for background turns. It creates a folder with two articles under Media (node 43) and
removes it at the end. The browser flows (Apache and Velocity, 960 px at scale 2) run against a real model.

`php bin/php/ezexec.php extension/expai/tests/expaidraftrevert_test.php -s <admin siteaccess>`: What changed and
revert of a draft written directly, without a model call: the base version and the parts, revert of one part (the
other parts stay, the same draft, the published version unchanged, the log entry with source `revert`), revert of
whole fields, the conflicts (a part gone, a field changed since), who may see and revert (the editor, a thread's
owner only, a version the assistant did not write) and a published version (diff shown, revert refused). It creates
a folder with an article under Media (node 43) and moves it to the trash at the end.
