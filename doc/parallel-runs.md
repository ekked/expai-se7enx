# Parallel runs, steering and slow models

An approved plan in Vector ([plan-mode.md](plan-mode.md)) runs its items **side by side**: each item is a child run
of the thread with its own conversation and its own queue job, translations wait for the item of their source, one
field is staged by one run at a time, and the thread shows per item what it does and what it costs. While a turn
runs, the editor can **steer** it with a note that reaches the model at its next step. A turn on a **slow model**
shows its time per step and can be switched to the default model.

## Child runs

When a plan is approved and `[VectorSettings] ParallelRuns=enabled` (and the job queue is there):

| Part | What |
| --- | --- |
| Run | one `expai_thread_run` per runnable item: `parent_run_id` = the lead run, `plan_item` = the item, `kind` = `item`, its own `expai_conversation` |
| Job | one `expai.thread.item` job per item (class `interactive`), all in one job group per plan (`expai.thread.plan`) |
| Concurrency | at most `MaxParallelRunsPerThread` (4) items of one thread at once: the queue resource `expai.thread:<thread id>`; the queue's own limits (class `interactive` MaxRunning, `[ResourceSettings] MaxConcurrent`) are the ceilings |
| Reservation | the plan's reservation is shared by the jobs in proportion to the estimate of each item |
| Message | the item's instruction ("Approved plan, item 2 of 4: ..."), in write mode with staged review, as the thread's owner |

The thread stays **running** until the last item ended; then the plan is `done`, the actual cost of every item is
stored with the plan, the reservation is released and the thread waits in *Needs you* with the staged changes.
With `ParallelRuns=disabled` the items run one after the other as turns of the thread (one job), as before.

## Dependencies

Each item may wait for earlier items:

- **automatically**: a `translate` item waits for an `update` item of the same object (in another language);
- **explicitly**: `propose_plan` takes `depends_on`, the numbers of earlier items (1 = the first item);
- a dependency on an item that does not run (unticked or refused) is dropped.

The queue holds the item behind the job of its last dependency (`depends_on` of expqueue); while another one still
works, the item is deferred and woken when the last one ends. An item starts with **what its dependencies staged**:
the proposed values (rich text as HTML) are added to its message as the current source text, so a translation
translates the updated text, not the published one. When a dependency fails or is stopped, the items that wait for
it are not run (state `stopped`, "Not run: the item it waits for did not finish").

## One field, one run

Two runs never stage the same object, language and field at once: while a run that still works (queued, running,
waiting; and has reported within `[ThreadSettings] StaleRunSeconds`) has an open proposal for a field, in this thread
or another, a second run's proposal for it is **held**: the tool result names the field "held by run N (label)",
the other fields are staged, and an event `held` is written. When every field is held the tool answers with an error
that tells the model to leave them and go on. Once the first run ended, a later run may replace its proposal (the
decisions of unchanged parts are kept, [review.md](review.md)).

## Progress and cost

- The thread list shows `3/8` (items ended of items) next to the state of a working thread.
- *Items of the plan* in the center lists one row per child run: its label, state (queued, waits for item N,
  working with "step k of about n", done, failed, stopped), its cost and its last step.
- The *Cost* tab shows **per item** the estimate and the actual cost (from the usage log rows of the run).
- The estimate learns per item: the median actual / expected of finished items with the **same action and class**
  (at least 3), else the same action, else the plan-level calibration ([plan-mode.md](plan-mode.md#the-estimate)).

## On a batch lane

When the thread's lane is a batch lane ([lanes.md](lanes.md)), an approved plan goes to the provider as a batch:
one queued request per item and language (`expai.request` on the lane, at its price) that asks for the item's fields
as JSON, with their current values in the request. When the batch returns, the applier `vector_plan_item`
(`expAiPlanItemApplier`) stages each answer as changes of the item's child run, through the same rules as the write
tools; the plan ends with the last answer. Batch items run independently: a translation translates the published
source. The lane can be chosen for bulk threads ("batch: approved plans go to the provider as a batch").

## Workers

One queue worker runs one job at a time. Right after a job is queued, expai starts detached workers
(`expAiQueueKick`, shared by every producer that queues work an editor waits for: thread turns, plan items, model
tests, field actions): when ready jobs wait and no live worker is free (none runs, or every live one is busy with a
long job), up to `[VectorSettings] MaxKickedWorkers` (4) live workers in all, cron and systemd workers included.
Each started worker leaves when nothing is ready. The start record `var/<site>/cache/expai/worker-kick.json` is shared
by PHP-FPM and Velocity (created writable for both). `KickWorker=disabled` leaves everything to the cron part.

## Steering

While a turn runs, **Steer** (Alt+Enter, or the Steer button) sends a note instead of a queued follow-up:

- it waits in the queue with the badge *Steer* and reaches the model at the **next call boundary**: as a text block
  after the tool results of the next step ("Note from the editor while you worked: ..."), which keeps the message
  list valid for every adapter and signed thinking intact;
- the step list says "Your note reached Vector: ..." and the note leaves the queue;
- a turn that answers in words before it could take the note makes it the **next queued follow-up**;
- only the lead run is steered; child runs of a plan are not.

Queued follow-ups (sent when the turn ends) move to Mod+Shift+Enter; Mod+Enter queues as well while a turn runs
([keyboard.md](keyboard.md)).

## Slow models

A model is slow when its provider is a managed local server (`Managed=ollama`), it is marked slow
(`[Model_<alias>] Slow`, `RunInBackground=always`), or the median latency of its last 20 requests is at least
`[VectorSettings] SlowStepSeconds` (20). While such a turn runs, Vector and the Intelligence tab show "Running on
<model> - about N s per step (step k)" with the time elapsed, and **Switch to <default model>** when the site default
may be used and is not slow itself.

Switching (`expaivector::switch_model`) stops the turn's job and goes on with the same message on the other model:
a new lead run whose conversation carries the earlier exchanges as a short summary (as any change of model); the
old run keeps its conversation, so a step of the slow model that was still under way ends there and changes nothing.
The browser continues the turn on the new model; a thread of the Intelligence tab also makes it the user's model.

## Functions and settings

| Function | Policy | Does |
| --- | --- | --- |
| `followup` (`delivery=steer`) | expai/vector | a note that steers the running turn |
| `events` | expai/write | per thread also `progress` ("3/8") and `slow` (the status of a turn on a slow model) |
| `thread` | expai/write | the thread's `runs` (one row per child run) and `progress` |
| `switch_model` | expai/write | the running turn goes on with another model (owner only) |

`[VectorSettings] ParallelRuns`, `MaxParallelRunsPerThread`, `MaxKickedWorkers`, `SlowStepSeconds`;
`[QueueSettings] Appliers[vector_plan_item]`; `[KeyBindings] Binding[steer]`
([configuration.md](configuration.md#threads-vector-and-the-keyboard)).

## Tests

`php bin/php/ezexec.php extension/expai/tests/expaivectorparallel_test.php -s <admin siteaccess>`: dependencies,
the fan-out (runs, jobs, group, resource, parent jobs), deferral and the source context, the field lock, the end of
runs and plans (dependants stopped, actual cost per item), Stop, the calibration per action and class, the batch
lane (requests and the applier), starting workers, the slow-model status and switch, and steering with a stub model.
The browser flows (Apache and Velocity, 960 px at scale 2, Claude Haiku) cover a 4-item plan with two items side by
side and a dependent translation, a lock conflict, steering mid-turn and a plan on the Anthropic batch lane.
