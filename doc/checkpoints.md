# Checkpoints and restore

Every Vector thread keeps **checkpoints**: the state at the start of each turn, before accepted changes are written to
drafts and before an approved plan runs. The editor can **restore** the thread to one of them: "undo what the AI did
since turn 3" without cleaning up drafts by hand. Nothing is published either way.

## What a checkpoint holds

Table `expai_checkpoint` ([database.md](database.md), class `expAiCheckpoint`):

| Column | Meaning |
| --- | --- |
| `turn`, `label` | the turn it was written before, and "Before: <the message>", "Before writing accepted changes to drafts", "Before the approved plan runs", "Before restore to: ..." |
| `conversation_len` | the messages of the lead conversation at that point |
| `drafts_json` | every draft this thread wrote that was still a draft: object, version, language, and the values of its writable fields |
| `changes_json` | the staged changes (decision, proposal and parts), the waiting follow-ups, the number of exchanges, a hash of the messages, the last change, draft log and follow-up ids; for "Before restore" also the conversation itself |

Values, not version numbers: expai writes into the editor's draft in place within a thread, and version history
limits may remove old versions, so a version number alone is no snapshot. A checkpoint larger than
`[VectorSettings] MaxCheckpointBytes` (1 MiB) keeps no values or proposals: "conversation only", it restores the
conversation and the decisions.

## The strip and restore

Under the conversation, the strip lists the newest checkpoints (◇ label) with **Restore** for the owner. Restore first
shows what will happen (`checkpoint_restore` with `preview=1`, nothing is done):

- **drafts discarded**: drafts this thread wrote after the checkpoint, when they are still drafts of the editor; a new
  object that is only a draft goes with it (its draft log says `discarded`);
- **drafts set back**: drafts that existed then get the values back of the fields that changed since;
- **left alone**: a draft that was published or sent for approval meanwhile, or a draft of another user;
- **staged changes set aside** (stale) when they were made after it, the others **back** to their decision and proposal of then;
- **exchanges removed**: the lead conversation is cut back to the checkpoint (the same first messages, which keeps
  signed thinking blocks valid; when the conversation was summarized meanwhile, the exchanges up to it as a summary);
- **queued messages dropped**: follow-ups queued after it.

Before it restores, the thread writes a checkpoint **Before restore to: ...** with the conversation itself, so a
restore can be undone by restoring that one (a discarded draft does not come back).

Rules: only the **owner** restores (`expai/vector`); nothing may run (Stop the thread first); approvers who may read
the thread see the strip without Restore.

## Settings and retention

`[VectorSettings] Checkpoints` (enabled), `MaxCheckpointBytes` (1048576), `MaxCheckpointsPerThread` (50: older ones
go) ([configuration.md](configuration.md#threads-vector-and-the-keyboard)). Checkpoints are removed after
`[ThreadSettings] CheckpointKeepDays` (30) by the cronjob part `expai` and `expaithreads.php --prune`
([threads.md](threads.md#retention)); they hold content text and are handled like conversations.

## Functions

| Function | Policy | Does |
| --- | --- | --- |
| `checkpoints` | expai/write | the checkpoints of a thread, newest first (whoever may read it), and whether the user may restore |
| `checkpoint_restore` | expai/vector | `preview=1`: what a restore would change; else the restore (owner only, nothing running) |
| `thread` | expai/write | the newest checkpoints (`checkpoints`) for the strip |

## Tests

`php bin/php/ezexec.php extension/expai/tests/expaivectorcheckpoint_test.php -s <admin siteaccess>`: checkpoints of
the conversation, drafts and changes, the preview, a restore that sets a draft back, one that discards a later draft
and sets later changes aside, the undo, owner only, nothing running, the size cap and the limit per thread. The
browser flow (Apache and Velocity, Claude Haiku) runs two turns that write a draft and restores to the first one.
