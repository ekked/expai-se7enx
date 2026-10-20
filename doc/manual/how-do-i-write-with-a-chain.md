# How do I write with a chain?

A chain writes in steps: one model writes, another reviews, the text is revised until the review passes, title,
summary and keywords are prepared, and then the run stops at a checkpoint where you decide. The result is one draft.

## Who can do this

Users whose role has `expai/write` and `expai/chain` (possibly limited to some chains). The site needs the job queue
running.

## Steps

1. Open the folder in **Content structure**, choose the **Intelligence** tab and find **Write with a chain** under the
   conversation. (Or use **Start writing** on the Exponential Intelligence dashboard.)
2. Choose the chain, for example **Use chain: Article**, and the number of items.
3. Write the brief and click **Start the chain**.
   The run page opens; several items open the list **EI: Chains**.
4. Wait while the steps run. The run page shows each step with its model, verdict, score and cost.
5. At the checkpoint, read the draft (you may change it in the edit form), then click **Approve and continue**,
   **Ask for changes** (with a note), or **Stop the run**.
6. When the run has ended, send the draft for publishing as usual.

## What can go wrong

- **The run stays "queued".** The job queue worker does not run. Tell your administrator.
- **The run is held by its budget.** Click **Continue with a higher run budget** if you may, or stop it.
- **Stop the run** keeps the draft. Discard it like any other draft if you do not need it.

## Related pages

- [AI chains](../chains.md)
- [User manual](README.md)
