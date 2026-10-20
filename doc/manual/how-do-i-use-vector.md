# How do I use Vector for a bigger job?

Vector is a workspace of threads for jobs that touch more than one object or language. A thread says what it may
touch, whether it plans first or writes, and which model it uses. Bulk work always starts with a plan you approve.

## Who can do this

Users whose role has `expai/write` and `expai/vector`. The policy can limit the subtree, the section, the kinds of
scope and whether changes are always staged.

## Steps

1. Open **Dashboard** > **EI: Vector** and click **New thread**.
2. Set the scope with **Change** (Alt+S): search the node, make it a **subtree** if everything below it counts, and tick
   **classes** and **languages**.
3. Choose the mode: **Plan** to have a plan proposed first, or **Write** for one object in one language. Choose the
   model and the lane.
4. Write what you want and click **Run**.
5. For bulk work, read the plan and its cost estimate in the **Plan** tab and approve it, or ask for changes.
6. Review what it proposes in the **Changes** tab: see
   [How do I review the changes Vector proposes?](how-do-i-review-vector-changes.md)
7. Watch the cost in the **Cost** tab. **Stop** ends a run; **Queue** lines up a follow-up request.

## What can go wrong

- **Write is not offered.** The job is bulk work: approve a plan first.
- **The scope is refused.** It lies outside what your role may touch in Vector, or you may not read it.
- **Ctrl/Cmd+K and ?** open the command palette and the list of shortcuts, if you prefer the keyboard.

## Related pages

- [Vector](../vector.md)
- [Plan mode](../plan-mode.md)
- [Threads](../threads.md)
- [Keyboard](../keyboard.md)
- [User manual](README.md)
