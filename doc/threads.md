# Threads

A **thread** is one unit of work of one editor: what they asked, everything the model read and wrote for it, its
state, and what it has cost. Every conversation in the Intelligence tab of a page is a thread, and Vector
([vector.md](vector.md)) starts threads of its own.

## What a thread has

| Part | Meaning |
| --- | --- |
| Owner | the editor who started it; only the owner changes or runs it |
| Title | the first request, cut to `[ThreadSettings] TitleLength` (80); can be renamed in Vector |
| Origin | `panel` (a conversation of the Intelligence tab), `vector` (started in Vector); `package` and `chain` are reserved for later |
| Scope | the nodes, subtrees, content classes and languages it may touch; a panel thread's scope is its page |
| Mode | `plan` (read, then answer with a plan; nothing is written) or `write` |
| Review | `staged` (changes are proposed and reviewed before anything is written) or `direct` (drafts are written at once) |
| Model, lane | the model of the thread (only models the editor may use, `expai/model_use`) and the lane (`auto`, `standard`, ...) |
| State | `idle`, `queued`, `running`, `waiting_input`, `review`, `done`, `failed`, `stopped` |
| Shelf | `active`, `pinned`, `settled`, `archived` |
| Cost | spent, tokens and requests, from the usage log rows of the thread |

A thread has one **lead run** with its own conversation (table `expai_conversation`). Later steps add child runs
for approved plans; each run keeps its own message list, so parallel runs never share one.

### States

| State | When |
| --- | --- |
| `idle` | nothing runs; the last turn ended without drafts |
| `running` | a turn is under way (in the browser: the next step is called while the model uses tools) |
| `review` | the last turn wrote drafts: they wait for the editor (Save as drafts, Send for approval) |
| `failed` | the last step failed (the reason is an `error` event) |
| `stopped` | the editor stopped the thread, or a run stopped reporting: after `[ThreadSettings] StaleRunSeconds` (900) without a change a `running` thread is shown as stopped |
| `queued`, `waiting_input`, `done` | used by background runs and plans |

### Shelves

*Active* and *Pinned* threads are in Vector's list, grouped as **Working** (queued, running), **Needs you** (review,
waiting for an answer, failed), **Pinned** and **Recent**. *Settled* and *Archived* threads have their own lists.
Settle and archive wait five seconds before they are sent, so **Undo** costs no request. A settled or archived
thread that gets a new message is active again. When the Intelligence tab starts a new conversation on a page, the
earlier panel thread of that page is settled.

## Who sees a thread

Owner decision: a thread is **private to its owner**, plus the **approvers of its changes**:

- the owner reads, renames, runs and shelves it;
- a user who takes part in the approval (the `ezapprove` collaboration item) of a version the thread wrote may open
  it **read only**, in Vector's list under *Waiting for your approval*;
- nobody else sees it, administrators included: another editor's thread id answers "does not exist or you may not
  open it", the same as a thread that does not exist.

## Events

Every thread has an append-only event stream (table `expai_thread_event`): `status` (a state change), `step_end`
(a tool step, with its text and whether it wrote), `text` (the model's answer), `draft` (a draft written), `cost`
(the thread's totals after a request), `followup` (a queued message; `steered` when a note reached the turn), `error`,
`run` (a child run of a plan started or ended) and `held` (a field another run holds, [parallel-runs.md](parallel-runs.md)). Payloads are JSON of at most
`[ThreadSettings] MaxEventPayloadBytes` (16 KiB); bigger ones keep their keys with long strings cut.

The browser reads them with `expaivector::events` (polling) or `expai/stream` (Server-Sent Events where the
server streams; [vector.md](vector.md#how-events-reach-the-browser)). Foreground runs also return their steps in
the answer of each step.

## Retention

Owner decision: events, staged proposals and checkpoints are kept **30 days**:

| Table | Removed after |
| --- | --- |
| `expai_thread_event` | `[ThreadSettings] EventKeepDays` (30) |
| `expai_change` | `ProposalKeepDays` (30) |
| `expai_checkpoint` | `CheckpointKeepDays` (30) |
| `expai_plan` (not running) | `ProposalKeepDays` |
| `expai_followup` (sent or dropped) | `EventKeepDays` |

The cronjob part `expai` does it (`cronjobs/expai_thread_prune.php`), and so does

    php extension/expai/bin/php/expaithreads.php --prune [--dry-run] -s <siteaccess>

0 keeps the rows. The threads, their runs and conversations stay until the editor removes them (the panel's
"New conversation" removes its thread with its events), or until the retention of `[RetentionSettings]` removes them:

| What | Removed after |
| --- | --- |
| a thread with its runs, events, follow-ups, checkpoints, plans, staged changes and conversations; not a pinned one, not one in review or waiting for an answer, not one that runs (a run silent for 4 x `[ThreadSettings] StaleRunSeconds` does not count) | not modified for `ThreadKeepDays` (365) |
| a conversation that is not part of a thread that stays | not modified for `ConversationKeepDays` (365) |
| the request summary of a draft log row (blanked, the row stays) | `PromptSummaryKeepDays` (365) |
| the threads and conversations of a user that no longer exists, whatever their shelf | the next run (a thread that runs the run after it ends) |

`cronjobs/expai_prune.php` and `expaithreads.php --prune` do it (`expAiThreadRetention::pruneRecords()`, at most
1000 threads and 1000 conversations per run, read in pages by id so that rows that stay never hold up the rest; a
thread that cannot be removed is logged and skipped). 0 keeps the rows. `[RetentionSettings] Mode=report` makes the
cronjob only say what it would remove; the cronjob prints its counts on every run, also in quiet mode.

## From conversations to threads

An installation of an earlier expai has conversations but no threads. `bin/php/expaischema.php --install` adds the
tables and turns every panel conversation into a thread (origin `panel`, scope: its page, review `direct`), links
the conversation, its usage rows and its draft log rows, and caches the cost. It is idempotent: a conversation that
has a thread is left alone. Separately:

    php extension/expai/bin/php/expaithreads.php --migrate --dry-run -s <siteaccess>   # what would be created
    php extension/expai/bin/php/expaithreads.php --migrate -s <siteaccess>
    php extension/expai/bin/php/expaithreads.php --report -s <siteaccess>             # counts per origin, state, shelf (no content)

The conversations are unchanged apart from `thread_id`/`run_id`; dropping the thread tables gives the earlier state
back.

## Where threads appear

- **Vector** (`expai/vector`): every thread of the editor ([vector.md](vector.md)).
- **The Intelligence tab**: the section *Threads on this page* ([PanelSection_threads]) lists the editor's threads
  on the page, with *Open in Vector* and *New thread in Vector for this page* for users with `expai/vector`.
- **The dashboard** (`expai/dashboard`): the card *Threads* with the latest threads, how many need the editor, and
  *Open Vector*. Template fetch: `fetch( 'expai', 'my_threads', hash( 'limit', 6 ) )`.
- **The command palette** (Ctrl/Cmd+K): recent and matching threads ([keyboard.md](keyboard.md)).

## Privacy

Thread titles, events and proposals hold content text: the same handling as conversations. They are never written
to the debug or request logs (`[UsageSettings] LogRequests` is unaffected), are pruned as above, and carry user ids
only.
