# Usage tracking, costs and limits

expai records every request it sends to the model, shows the usage on the dashboard and in the log, estimates its
cost and can stop requests when a limit is reached.

## What is recorded

One row in `expai_usage_log` per request (columns in [database.md](database.md)):

- who and where: the user object id (no names, no e-mail addresses), the node, the panel conversation and turn, the
  action (`panel`, `fill`, `field:<action>`), a hash of the login session;
- what: the provider, the model that answered, the tokens the API reported (input, output, cache write, cache read),
  the stop reason;
- how it went: HTTP status, retries, time of the call, the tools the answer asked for (names, outcome and time; never
  their input), drafts written, the error of a failed request;
- the rate limit headers of the response (`anthropic-ratelimit-*`, `retry-after`).

The texts of requests and answers are **not** stored. Only with `[UsageSettings] LogRequests=enabled` is the full
request kept with each row, for debugging; it contains content and the editors' words, so leave it disabled in
production. The request summary of the draft log (`[LogSettings] MaxPromptSummaryLength`) is separate.

Failed requests are recorded too, with their HTTP status and error. A request stopped by a limit of `[Limits]` is not
sent and not recorded: the editor sees the limit and when it resets.

### Turns

A turn is one panel message, one field action or one "Fill the fields". One turn can lead to several requests (each
tool round is one; at most `[Limits] MaxRequestsPerTurn` or `[AssistantSettings] MaxToolIterations`). The log shows
turns with their totals; opening a turn shows its requests.

### Providers

The provider normalises the usage it reports into the four token counts, and the row stores the provider id
(`expAiProvider::providerId()`), so a custom provider's rows are counted the same way (extending.md).

## Where to see it

- **Dashboard** (`expai/dashboard`): the figures, the chart, the limits of the chosen period; the user's own usage,
  or with `expai/log` the whole site and the top users. See [dashboard.md](dashboard.md).
- **Log** (`expai/log`): every turn and request, filtered by date, user, node, action, model and errors, sortable,
  with totals per day and per user, and a CSV export (no request texts, user ids only).

## The estimated cost

The cost is computed when it is shown, from the tokens and the `[Pricing]` table (configuration.md):

```
cost = ( input × Input + output × Output + cache write × CacheWrite + cache read × CacheRead ) / 1 000 000 × Multiplier
```

with the prices per million tokens of the model's row. A model is matched by its id, then without a provider prefix,
date or `-latest` suffix, then by family and version; a model without a row uses the `Default*` prices and its cost
is shown as approximate (`≈`). Claude Haiku 5.5 has a second tier: a request whose prompt (input + cache write + cache
read) is over `LongContextThreshold` uses the `*Long` prices.

Because nothing is stored as money, correcting the price table corrects every past figure too. The estimate is not
an invoice: Anthropic's invoice is authoritative. `[Pricing] Enabled=false` hides costs everywhere.

Check the matcher against the shipped table:

```
php bin/php/ezexec.php extension/expai/tests/expaicost_test.php
```

### Checking the numbers

- A row's tokens are the `usage` object of the API response, unchanged: compare a request in the log with the
  Usage page of the Claude Console for the same minute and model.
- Compute one row by hand with the formula above. Example, Claude Sonnet 5.5 (2 / 2.50 / 0.10 / 10 per million): 4
  input, 136 output, 6 324 cache write tokens = (4 × 2 + 136 × 10 + 6 324 × 2.50) / 1 000 000 = $0.017178.
- The dashboard's total for a period equals the sum of the log's "Estimated cost" for the same dates.

## Limits

`[Limits]` (configuration.md) sets optional limits per conversation, per login session, per user and day or month,
and for the whole site per month, in tokens or in estimated cost, plus the most requests one turn may make. Each is
checked before a request is sent; when one is reached the editor sees which limit and when it resets, and the request
is not sent. The dashboard shows a bar for every limit that is set.

Anthropic's own limits apply in addition: the rate limits of the key (the dashboard shows what the last response
reported; a rate-limited request fails with "busy or over its limit, try again later" and is recorded with its `retry-after`; there is no automatic retry) and the organisation's
monthly spend limit, which an API key cannot read: see it in the Claude Console.

## Retention

The cronjob part `expai` removes rows older than `[UsageSettings] RetentionDays` (365; `0` keeps everything):

```
php runcronjobs.php -s <admin siteaccess> expai
```

Add it to the server's crontab next to the other cronjob parts, e.g. once a night.

## Privacy

- User ids only, never names or e-mail addresses, in the table, the CSV and the dashboard's top users (the name shown
  there is read from the user object when the page is rendered, for those who may see it).
- No request or answer texts unless `LogRequests` is enabled.
- The session is stored as a hash, not as the session id.
- Users with `expai/write` see only their own rows; everyone's needs `expai/log`.
