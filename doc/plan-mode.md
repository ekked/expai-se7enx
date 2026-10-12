# Plan mode: a plan and an estimate before bulk work

Owner decision: bulk work in Vector (more than one object or more than one language, or any subtree or class in the
scope; `[VectorSettings] BulkObjectThreshold`, `BulkLanguageThreshold`) starts with a **plan** the editor approves,
with a cost estimate, before anything is staged. A thread in plan mode has the read tools and `propose_plan`, no write
tools. Every Vector thread may call `propose_plan`; when a staged thread tries to stage a second object or language
without an approved plan, the write tool asks it to propose one.

## propose_plan

Input ([tools.md](tools.md)):

| Key | Meaning |
| --- | --- |
| `items[]` | one per object and action: `action` (`create`, `update`, `translate`), `object_id` or `node_id` (update, translate), `parent_node_id` and `class` (create), `languages[]`, `fields[]`, `summary` (the instruction for that item) |
| `assumptions[]`, `questions[]`, `risks[]` | shown with the plan |

`expAiPlanner` checks every item against the thread's scope and the editor's permissions (read, edit in each
language, create the class under the parent) and keeps refused items with the reason: they are shown struck through
and never run. The plan is stored in `expai_plan` (state `proposed`; earlier proposed plans of the thread become
`superseded`), a `plan` event is written and the thread waits in *Needs you*. The model is told to stop and summarize.

## The Plan tab

A table of the items (Run checkbox, number, action, target, languages, fields, estimate per item) with the instruction
of each item editable, the estimate, the limits it touches, the assumptions, questions and risks, and:

- **Approve and run**: with the items still ticked and the instructions as edited;
- **Ask for changes**: the message field is prepared for a follow-up in plan mode (a new plan supersedes this one);
- **Reject**: the plan is rejected, the thread idle.

Unticking an item or editing an instruction estimates again (`plan_estimate`, nothing stored).

## The estimate

`expAiPlanEstimate`, shown as a range:

- per item and language, `r` tool rounds (`[VectorSettings] ExpectedRounds[create|update|translate]`, default 3, 3,
  2); low = `r - 1`, high = `r + HighExtraRounds` (2);
- each round sends the frame (the thread's stored instructions and tool definitions, measured; `PlanFrameTokens`
  when there are none yet), a bounded history (`PlanHistoryTokens`) and the item's context (the text of the fields it
  names in the published version, characters / `CharsPerToken`; a new object `PlanNewObjectTokens`); the context
  grows by what the rounds read and write;
- output: a new object `OutputPerField` tokens per field, an update or translation 1.1 x the source text, plus a
  little per round;
- priced with the model's prices ([usage-tracking.md](usage-tracking.md), aliases of [providers.md](providers.md))
  and each lane's price factor ([lanes.md](lanes.md)); the tab shows the thread's lane and the others (a batch lane
  is listed for comparison; the run itself uses the thread's lane);
- **calibration**: the median of actual / expected cost of the last 20 finished plans (at least 3), between 0.5 and
  3, scales the result. A local or free model shows "no price".

## Limits and reservation

Approval checks the expected cost against the editor's cost limits per day and month (`[Limits]`,
[usage-tracking.md](usage-tracking.md)) and the monthly site budget ([budgets.md](budgets.md)); a plan that would
cross one cannot be approved ("This plan needs about $0.34; $0.20 is left of the limit ...") until items are unticked.
On approval the expected cost times `[Budgets] ReserveFactor` is **reserved**: on the queue job (counted by the site
budget like every queued request) and on the thread (`reserved_micros`, shown in the Plan tab). When the run ends the
reservation is released and the actual cost is stored with the plan (the calibration learns from it).

## The run

The approved plan becomes the thread's plan (`plan_id`), the thread goes to **Write** with **staged** review, and each
runnable item becomes one turn of the thread's lead run ("Approved plan, item 2 of 4: update object ... Do this item
now and only this item ... stage the change"):

- with the job queue: **one background job** (`expai.thread.turn`) with one message per item, run one after the
  other ([vector.md](vector.md#background-runs)); the thread stays running until the last item, then waits in *Needs
  you* with the staged changes; Stop cancels the job after the current step;
- without the queue: the item messages wait as **queued follow-ups** and the browser sends them one after the other.

Every write of the run is a staged change ([review.md](review.md)), new objects too; the editor reviews and applies
them as usual. Plan states: `proposed`, `approved`, `running`, `done`, `rejected`, `superseded`.

With `[VectorSettings] ParallelRuns=enabled` (the default) and the job queue, the items run **side by side** instead:
one child run and one `expai.thread.item` job per item, translations after their source item, progress and cost per
item, and on a batch lane as one provider batch ([parallel-runs.md](parallel-runs.md)).

## Functions and settings

`expaivector::plan`, `plan_estimate`, `plan_decide` ([vector.md](vector.md#the-ezjscore-functions));
`[VectorSettings] MaxPlanItems` (50) and the estimate settings ([configuration.md](configuration.md#threads-vector-and-the-keyboard)).
Tests: `extension/expai/tests/expaivectorreview_test.php` (validation, refused items, estimate maths, priced lanes,
edit, limits, approval with the job's messages and reservation, completion, rejection).
