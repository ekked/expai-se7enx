# The master switch and the veto hook

Two controls decide whether a request may reach a provider at all, before any of the other rules (permissions, data
policy, limits, budgets) are looked at. Both are checked in one place, `expAiGate`, which every path that calls a
provider goes through.

## The master switch

```ini
[AISettings]
Enabled=disabled
# or: on, but off on these siteaccesses
Enabled=enabled
DisabledSiteAccesses[]=intranet_admin
```

`Enabled` is off unless it says `enabled` or `true`. `DisabledSiteAccesses[]` switches expai off on the listed
siteaccesses although `Enabled` is on. A siteaccess can also set `Enabled` in its own
`settings/siteaccess/<siteaccess>/expai.ini.append.php`.

When it is off:

| Entry point | What happens |
| --- | --- |
| The Intelligence tab of the full view | not drawn (`fetch( 'expai', 'switched_on' )`) |
| The edit form controls and field actions | not drawn; a field action request is refused ("Exponential Intelligence is switched off.") |
| The panel's ezjscore functions (`expai::*`) | refused with the same text |
| Vector (`expai/vector`, `expaivector::*`) | the page says AI is not ready; every function that needs a provider is refused |
| `expai/dashboard` | the status says "Switched off"; "Start writing" opens nothing and says why |
| AI chains | a chain does not start |
| Translation sync, chains and other queued work | nothing is queued (`expAiQueue::request()` refuses) |
| Queued requests already waiting | held (`expai.request` and `expai.batch.submit`), with the reason `switched off: ...` as their last error; the cron part `expai` (`cronjobs/expai_queue.php`) resumes them once expai is on again for the siteaccess they were queued from |
| Background thread turns already queued | the turn ends failed with "Exponential Intelligence is switched off."; the editor can run it again later |
| The model test of `expai/models`, the probe | refused |
| Any other provider call | refused by the usage recorder that wraps every provider, before the limits and before anything is sent |

Provider batches that were sent before the switch went off are still collected (`expai.batch.poll` sends no content),
so nothing already paid for is lost.

## The veto hook

A veto refuses one request with a reason that the editor reads ("This request was refused: <reason>") and that is
logged. It is checked after the master switch, before the limits, on every provider call and before a request is
queued. A queued request that meets a veto fails for good (error class `veto`) instead of being tried again.

### Rules in the settings

```ini
[RequestVeto]
Rules[]=no_ai_on_users
Rules[]=maintenance

[RequestVeto_no_ai_on_users]
Sections[]=2
Reason=Exponential Intelligence is not used for user accounts.

[RequestVeto_maintenance]
SiteAccesses[]=intranet_admin
Purposes[]=queue.*
Reason=Queued AI work is paused on the intranet until Monday.
```

The rules are checked in order; the first rule whose conditions all match refuses the request. A rule without any
condition refuses every request (a maintenance window with a message, unlike the master switch's fixed text).

| Condition | Matches when |
| --- | --- |
| `Sections[]` | the section id of the content of the request is listed |
| `Classes[]` | its class identifier is listed |
| `Subtrees[]` | its node is at or below one of the node ids |
| `SiteAccesses[]` | the siteaccess (of the request, or the one a queued request was queued from) is listed |
| `Providers[]` | the provider id is listed |
| `Regions[]` | the provider's region (`us`, `eu`, `local`) is listed |
| `Purposes[]` | the purpose is listed; a trailing `*` matches the rest (`queue.*`) |
| `Reason` | the text shown and logged (default: "This request is not allowed on this site.") |

A request without content (the model test, a probe) never matches a rule with a content condition (`Sections[]`,
`Classes[]`, `Subtrees[]`).

### Content the request reads

The content of a request is not only the node it starts on: the tools read further objects (get_node, the children of
a node, the tree context, service tools, MCP bridge results). The rules with a content condition (`Sections[]`,
`Classes[]`, `Subtrees[]`) are checked for every object read, the way the data policy is (`expAiGate::objectVeto()`,
called by `expAiContentReader`):

- the object's section, class and **every** location (a second location inside a listed subtree is enough), the
  siteaccess, and what is known of the request: the provider and its region (from the data policy of the reader) and
  the purpose (`expAiContentReader::setRequest()`: `panel` or the thread's action such as `vector` in a conversation,
  `queue.chain` for the steps of a chain, `queue.bulk` for the answers of a plan's batch; the edit form's field
  actions and the fallback's node check leave it unknown);
- a condition on a part of the request that is not known counts as met: a rule that could refuse the object does;
  so does a condition on a missing fact of the content (an object without a location, such as a new draft, meets
  `Subtrees[]`; section 0 meets `Sections[]`; no class meets `Classes[]`), unless another condition of the rule that
  is known excludes the object. The request-level check keeps its rule: a request without content matches no content
  condition;
- a rule with an invalid name or without its group is skipped, with a warning in the debug log (once per process);
- an object read on its own is refused with "This request was refused: <reason>" (the tool fails, the request goes
  on); in a list the object is withheld, with `withheld: "refused: <reason>"` and counted like the objects the data
  policy withholds;
- the names of related objects in a relation field follow the same rule: a related object the policy or the veto
  keeps away is named `(object <id> withheld)`;
- the answers of service tools and of the MCP bridge are filtered row by row as soon as a data policy is set **or** a
  rule names content (`expAiContentReader::filters()`); `search_content` withholds and counts such hits too;
- the listeners of `expai/request/veto` are not asked per object: they decide on the request as a whole.

### A listener: the ezpEvent filter `expai/request/veto`

For decisions the settings cannot express (a compliance service, office hours, a per-user flag), register a filter in
`site.ini`:

```ini
[Event]
Listeners[]=expai/request/veto@myComplianceCheck::veto
```

```php
class myComplianceCheck
{
    public static function veto( array $request )
    {
        if ( !empty( $request['data_class']['zero_retention'] ) && $request['retention'] !== 'none' )
            $request['veto'] = 'This content may only go to a provider that keeps nothing.';
        return $request;
    }
}
```

The listener receives the request context and returns it; setting `veto` to a text refuses the request. A listener
that does not return the context (an error, `null`) refuses the request too: the hook fails closed.

| Key | Value |
| --- | --- |
| `user`, `user_id` | the eZUser the request runs as (the editor, or the owner of a queued job) and its id |
| `node_id`, `object_id`, `path` | the content of the request (0 and '' when there is none) and its node path string |
| `class_identifier`, `section_id` | the class and section of that content |
| `siteaccess` | the siteaccess of the request, or the one a queued request was queued from |
| `provider`, `region`, `retention` | the provider id and what its settings declare (`[Provider_<id>] Region`, `Retention`) |
| `model`, `model_alias` | the model id and its alias in the registry |
| `purpose` | `panel`, `vector`, a field action name (`rewrite`, ...), `test`, `probe`, `queue.<purpose>` (`queue.translation_sync`, chain steps ...) |
| `data_class` | what the data policy requires of the content: `regions` (null: any) and `zero_retention` ([data-policy.md](data-policy.md)) |
| `veto` | '' - set it to refuse |

### What is logged

Every refusal is a row of the usage log with `error_class` `veto` and the error message
`refused (rule:<name>): <reason>` or `refused (listener): <reason>`, with the user, node, provider, model and purpose
(the purpose in the `action` column), and a notice in the debug log. No tokens are counted and no cost is estimated:
nothing was sent. The dashboard and `expai/log` show these rows like other failed requests.

## Tested

`tests/expaigate_test.php`: off switch and off siteaccess at every entry point (the recorder, the panel, edit form and
Vector guards, the chain engine, the queue, the queued request handler, the template fetch), the context a listener
receives, rule conditions, a listener that refuses and one that fails closed, the logged row, the permanent failure of
a queued request, and the resume of held jobs.

See also [configuration.md](configuration.md), [security.md](security.md), [lanes.md](lanes.md).
