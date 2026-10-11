# Vector

Vector (`expai/vector`) is the controlled way to command changes with Exponential Intelligence: a workspace of
threads ([threads.md](threads.md)) where the editor sets what a thread may touch, how it works (plan or write,
staged or direct review), which model and lane it uses, runs and stops it, queues follow-ups, and sees what it has
cost. It is in the left menu of the Dashboard tab, next to *Exponential Intelligence*, for users with
`expai/write` and `expai/vector`.

## The layout

| Pane | Content |
| --- | --- |
| Left: threads | *New thread*, a filter, the sections Working, Needs you, Pinned, Recent and *Waiting for your approval*; links to Settled and Archived |
| Center: the thread | title and state; pin, settle, archive; the scope chips with *Change*; mode (Plan/Write), model, lane, review; the exchanges with their steps; the queue; the message field with Run, Stop and Queue |
| Right: plan, changes, cost | the panes of `[VectorSettings] Panes[]`: *Plan* (the plan of plan mode), *Changes* (staged changes to review, and the drafts the thread wrote), *Cost* (spent, tokens, requests, model, lane) |

The layout follows the width of the workspace (the admin's left menu takes its share): three columns from 1040
pixels, below that the right pane opens as a drawer (the side button or 1/2/3), below 680 the thread list is a
drawer too. Light and dark follow the admin design (admin4l, admin4, editor and the older admin designs).

## View parameters

Only view parameters, no GET parameters:

| Address | Opens |
| --- | --- |
| `expai/vector` | the list; no thread open |
| `expai/vector/(thread)/<uuid>` | that thread (switching threads changes the address the same way) |
| `expai/vector/(node)/<node id>` | the editor's newest Vector thread on that node, or a new one with the node as its scope |
| `expai/vector/(tab)/plan`, `diff`, `cost` | the right pane's tab |
| `expai/vector/(shelf)/settled`, `archived` | the settled or archived threads |

## Scope

*Change* (Alt+S) opens the scope dialog: search nodes by name or id (only what the editor may read), make a node a
**subtree** (the node and everything below it), and tick **classes** and **languages**. The scope is checked
against the editor's read access and the limitations of `expai/vector` (Subtree, Section, Scope, Review;
[permissions.md](permissions.md)). The model is told the scope once per change, as data.

**Bulk work** is more than one object or more than one language (`[VectorSettings] BulkObjectThreshold`,
`BulkLanguageThreshold`), or any subtree or class entry. Owner decision: bulk work starts in **plan mode** and is
always **staged**; Write is only offered once a plan is approved.

## Mode and review

| | Plan | Write |
| --- | --- | --- |
| Staged | reads, answers with a plan; writes nothing | changes are proposed for review field by field before they are written; until staged changes are available the thread reads only and describes the exact changes |
| Direct | (as plan) | drafts are written at once, never published; one object in one language only, and only where the role allows `Review=direct` |

A thread of the Intelligence tab keeps the panel's behavior (direct drafts for small edits). Changing mode, like
changing the model, starts a fresh window for the model with the earlier exchanges as a short summary.

## Model and lane

The model list is the one of the Intelligence tab: only models the editor may use (`expai/model_use`), grouped by
provider, with "reads only" for models that may not write (the write gate, [probe.md](probe.md)). The lane list
comes from the lanes of the thread's model where lanes are installed (`expAiLane`), else *Automatic* and *Standard*;
batch lanes are listed but run approved plans only. Runs in the browser window (foreground) use the standard lane;
the chosen lane applies to background runs.

## Run, stop, queue

- **Run** (Ctrl/Cmd+Enter) sends the message. The turn runs in the browser's request path, like the Intelligence
  tab: one model call per request, the next step called while the model uses tools, each step shown as it ends.
  It runs as the editor, with the thread's model; the tools refuse what the editor may not do.
- **Stop** (Esc twice, or the button) stops calling the next step: the state becomes `stopped`, the steps so far are
  kept. A call already sent to the model finishes first.
- **Queue** (Alt+Enter, or Enter on the Queue button while a turn runs) keeps the message in the thread's queue
  (`expai_followup`); when the turn ends the first one is sent automatically. A queued message can be removed (x).

Background runs in the queue (expqueue), parallel runs of an approved plan and steering a running turn come with the
next steps; the thread data, events and the stream are ready for them.

## How events reach the browser

| Transport | Where | How |
| --- | --- | --- |
| Polling | everywhere | `expaivector::events` (POST with the form token) every `[VectorSettings] PollActiveMs` (1 s) while the open thread runs, `PollWorkingMs` (3 s) while another thread works, `PollIdleMs` (15 s) otherwise; doubled while the host is busy, none while the browser tab is hidden |
| Server-Sent Events | `Transport=auto` under PHP-FPM/Apache | `expai/stream/(thread)/<uuid>/(after)/<event id>`: the session lock is released first, a first line is sent at once, the stream is held at most `StreamSeconds` (25) and resumed with Last-Event-ID; at most `MaxStreams` streams at once (one PHP process each), over it the answer is 204 and the browser polls. Without a first line within 2 seconds the browser polls for the rest of the session. |

Under Velocity `auto` means polling (its pool workers do not stream yet). On Apache the proxy to PHP-FPM must flush
each packet (`flushpackets=on`). Every Vector function that only reads the session releases its lock at once, so a
poll or a long turn never holds up the editor's other pages. A poll costs one kernel bootstrap and writes nothing.

## The ezjscore functions

`POST <root>/ezjscore/call` with `ezjscServer_function_arguments=expaivector::<function>` and the form token (the
same permission as `expai::*`: `ezjscore/call` with the function list `expai`):

| Function | Policy | Does |
| --- | --- | --- |
| `threads` | expai/write | the editor's threads (shelf, search, node), the threads shared for approval, the counts |
| `thread` | expai/write | one thread: header, scope names, the last exchanges, drafts, queue, lanes; clears its unread mark |
| `history` | expai/write | older exchanges of a thread |
| `events` | expai/write | events after a cursor, thread states, the next poll interval |
| `nodes` | expai/write | readable nodes by name or id (scope picker, palette) |
| `palette` | expai/write | the palette script, texts, key bindings, models ([keyboard.md](keyboard.md)) |
| `keybindings` | expai/write | stores or resets the editor's shortcuts |
| `bootstrap` | expai/vector | models, classes, languages, the scope kinds and review policies the role allows, defaults, transport, poll intervals |
| `thread_save` | expai/vector | creates a thread or changes title, scope, mode, review, model, lane, shelf, position |
| `turn` | expai/vector | one step of the thread (message, or continue=1) |
| `stop` | expai/vector | stops the thread |
| `followup` | expai/vector | the queue: add, drop, take, list |

Every function checks POST, the form token and the policies itself, and what the thread allows
([threads.md](threads.md#who-sees-a-thread)).

## Settings

`[VectorSettings]` (Enabled, Panes[], DefaultMode, DefaultReview, BulkObjectThreshold, BulkLanguageThreshold,
PollActiveMs, PollWorkingMs, PollIdleMs, Transport, MaxStreams, StreamSeconds), `[VectorPane_<name>] Template=`,
`[ThreadSettings]` and `[KeyBindings]`: [configuration.md](configuration.md#threads-vector-and-the-keyboard).

## Extending

- A pane of your own: `[VectorSettings] Panes[]=mypane` and `[VectorPane_mypane] Template=...`; fill it from
  JavaScript on the `vector:open`, `vector:step` and `vector:done` events (`ExpAi.on( ... )`).
- A command of your own in the palette: `ExpAi.commands.register( 'my_command', { label: 'Do it', run: function () { ... } } )`
  ([keyboard.md](keyboard.md)).
