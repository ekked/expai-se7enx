# Staged review: review the changes before they are written

In a Vector thread with **Review: Staged** (the default, and always for bulk work) the write tools do not write
drafts. `create_draft` and `update_draft` **stage proposals**: one per object, language and field, new objects
included (owner decision). The editor reviews them in the *Changes* tab, part by part and field by field, and only the
accepted parts are written into drafts. Nothing is ever published. Small edits in the Intelligence tab keep writing
drafts directly ([full-view-panel.md](full-view-panel.md)).

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

## Permissions

- Staging, deciding and applying need `expai/write` and `expai/vector` (and its `Review` limitation allows `staged`
  or `direct`, [permissions.md](permissions.md)); only the **owner** of a thread decides and applies. The approvers
  who may open a thread see its changes read-only.
- Writing checks the editor's own **edit** (or create) permission per object and language at apply time; without it
  the object is listed with the reason and nothing is written.

## Tests

`php bin/php/ezexec.php extension/expai/tests/expaivectorreview_test.php -s <admin siteaccess>`: staging through
both tools, hunks, re-staging, the bulk guard, unchanged and skipped fields, edits, decisions, apply (accepted and
rejected parts, the same draft reused, the draft log), conflicts and restage, a new object, permissions, plan mode and
the master switch and veto for background turns. It creates a folder with two articles under Media (node 43) and
removes it at the end. The browser flows (Apache and Velocity, 960 px at scale 2) run against a real model.
