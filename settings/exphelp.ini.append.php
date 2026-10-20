<?php /* #?ini charset="utf-8"?

# The help center of the administration (exphelp, Exponential 6.0.15 and later) shows this extension's documentation
# as it shows that of every active extension (doc/index.md, "Help in the administration interface"). The Help control
# of the top bar opens the page of the expai view the user is on. Page ids: ext/expai/<path below doc/>; an entry whose
# page the help does not serve is skipped, so it never leads to a "not found".
[ContextHelp]
Map[expai]=ext/expai/index.md
Map[expai/dashboard]=ext/expai/dashboard.md
Map[expai/vector]=ext/expai/vector.md
Map[expai/chain]=ext/expai/chains.md
Map[expai/alttext]=ext/expai/alt-text.md
Map[expai/summary]=ext/expai/summary.md
Map[expai/log]=ext/expai/usage-tracking.md
Map[expai/budgets]=ext/expai/budgets.md
Map[expai/models]=ext/expai/providers.md

*/ ?>
