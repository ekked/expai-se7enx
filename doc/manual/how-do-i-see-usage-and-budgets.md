# How do I see usage, cost and budgets?

The Exponential Intelligence dashboard shows what you (or the whole site) used and what it cost; the budgets page
shows what queued work may still spend this month.

## Who can do this

Every user with `expai/write` sees their own usage. The whole site's usage and the budget figures need `expai/log`.

## Steps

1. Open **Dashboard** > **Exponential Intelligence**.
2. Choose the period at the top: **Today**, **7d**, **30d** or **Month**; with `expai/log` also **Mine** or **Site**.
   The four figures show the estimated cost, the tokens, the requests and the drafts written, compared with the period
   before.
3. Read **Limits**: how much of each limit is used and when it resets.
4. Click **Details in the log**, or open **EI: Log**, for every request with its tokens, time and estimated cost.
5. Open **Setup** > **EI: Budgets** for the monthly site budget, the job groups with their budget, spent and reserved
   amounts, and the held jobs.

## What can go wrong

- **A cost shows "≈" or none.** The model has no price set, so the cost is an estimate or unknown.
- **Jobs are held.** The monthly budget or a job group budget is used up. Raise it in the settings, or wait for the
  next month.
- **The dashboard shows no usage.** Nothing was used in the period, or the usage table is missing; see the
  troubleshooting page.

## Related pages

- [The dashboard](../dashboard.md)
- [Budgets](../budgets.md)
- [Usage tracking](../usage-tracking.md)
- [User manual](README.md)
