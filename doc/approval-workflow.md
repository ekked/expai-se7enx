# Drafts, approval and the label

## Drafts

Everything expai writes is a draft of the editor:

- a new object: version 1 with status draft, owner and version creator the editor, placed (as a node assignment) under
  the chosen parent, in the parent's section;
- a change: a new version with status draft (the published version stays as it is), or the draft expai wrote for
  this editor earlier in the same conversation.

These are ordinary drafts: they are in **My drafts** (dashboard block and content/draft), open in the normal edit form
(Online Editor or Tiptap, autosave) and can be discarded there. Publishing one (from the panel or the edit form) runs
the same publish operation as always, with its notifications and audit records; creating the draft itself is not an
audited event of its own. Each is recorded in `expai_draft_log` (database.md), which is what the label and the dashboard read.

## What "Send for approval" does

1. Checks that the version is in the log for this editor, is still the editor's own draft, that the editor may edit
   it, and that every required field has content ("Required fields are empty: ... Open the draft in the edit form").
2. Runs the regular publish operation as the editor:
   `eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => ..., 'version' => ... ) )`, with
   asynchronous publishing switched off for this call.
3. Reads the version's status: **published** (no workflow held it), **pending** (a workflow holds it, typically an
   approval), or still a **draft** (a workflow stopped it). The panel says which.

## Whether an approval applies

Before the editor presses the button, expAiApprovalCheck says per draft what will happen. It reads the triggers
before `content/publish` and evaluates each approve event (`event_ezapprove`) as eZApproveType::execute() does:

- the object's section, or the section of a parent of one of its locations, is one of the event's sections (or the
  event covers every section);
- the editor is not in one of the event's excluded user groups;
- the editor is not one of the approving users or in an approving group (approvers publish without approval);
- the version's language is in the event's language mask (or the mask is empty);
- the version option (only the first version, all but the first, or all) matches.

| Result | Shown as |
| --- | --- |
| `approval` | Waits for approval when sent |
| `possible` | May wait for approval when sent (an approve event behind a multiplexer, whose conditions are not evaluated) |
| `direct` | Published at once when sent (no approval applies) |

## The approval path

1. The editor sends the draft. The approve event creates a collaboration item (type `ezapprove`) for the approvers
   and defers the workflow to the cronjob; the version is **pending**. The panel and the dashboard show "Waiting for
   approval".
2. The approver sees the item in the collaboration inbox (the label next to it), in "Waiting for my approval" on
   expai's dashboard page, and opens it: the item page shows the note "Exponential Intelligence (Powered by Claude
   from Anthropic) - This version was written with Exponential Intelligence ... Model: ..." above the decision.
3. **Approve**: the workflow cronjob (the `frequent` part runs `workflow.php`) continues the workflow and publishes
   the version. **Deny**: the cronjob ends the workflow; the version is a draft of the editor again and the panel
   shows it as Draft; it can be edited and sent again (a new approval).

The label in the approval views is drawn by expai.js: on `collaboration/item/full/<id>` it asks `expai::labels` for
that item (only participants get an answer) and shows the note; in lists it adds the label next to links to items and
versions it wrote. No collaboration template is overridden.

## Tested

The path above was run end to end with real model calls in a copy of a live installation (its own database copy,
mail to files) with an approve event for every section: an editor asked for one article, sent it (pending, item
created, label shown to the approver), the approver approved it, the workflow cronjob published it; a second article
was denied and came back as the editor's draft. On the live installation (where the approver's own requests and
other sections publish directly) "Send for approval" published at once, as the hint had said.
