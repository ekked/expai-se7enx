<?php /* #?ini charset="utf-8"?

# The entry of expai/dashboard in the left menu of the Dashboard tab (design:parts/my/menu.tpl reads
# [Leftmenu_my]); shown only to users with the policy expai/write.
[Leftmenu_my]
Links[expai]=expai/dashboard
LinkNames[expai]=Exponential Intelligence
PolicyList_expai[]=expai/write
# Vector, the workspace of threads (doc/vector.md): users with expai/write and expai/vector
Links[expai_vector]=expai/vector
LinkNames[expai_vector]=EI: Vector
PolicyList_expai_vector[]=expai/write
PolicyList_expai_vector[]=expai/vector
# AI chains (doc/chains.md): the runs and their checkpoints, users with expai/write and expai/chain
Links[expai_chain]=expai/chain
LinkNames[expai_chain]=EI: Chains
PolicyList_expai_chain[]=expai/write
PolicyList_expai_chain[]=expai/chain
# The usage log (doc/usage-tracking.md), just above the help
Links[expai_log]=expai/log
LinkNames[expai_log]=EI: Log
PolicyList_expai_log[]=expai/write
# The extension's documentation (expai/help, doc/index.md)
Links[expai_help]=expai/help
LinkNames[expai_help]=EI: Help
PolicyList_expai_help[]=expai/write

# Setup > EI: Summary (expai/summary): the printable page for a data protection officer (doc/summary.md), shown only to users
# with the policy expai/summary (the Administrator role holds every policy).
[Leftmenu_setup]
Links[expai_summary]=expai/summary
LinkNames[expai_summary]=EI: Summary
PolicyList_expai_summary[]=expai/summary
# Setup > EI: Models: providers, key presence, probe results, the local model (doc/local-models.md, expai/model_manage)
Links[expai_models]=expai/models
LinkNames[expai_models]=EI: Models
PolicyList_expai_models[]=expai/model_manage
# Setup > EI: Budgets: the monthly budget of queued requests, job groups, lanes, batches (doc/budgets.md)
Links[expai_budgets]=expai/budgets
LinkNames[expai_budgets]=EI: Budgets
PolicyList_expai_budgets[]=expai/log
# Setup > Job queue (extension expqueue), listed right after EI: Budgets
Links[expqueue]=expqueue/dashboard
LinkNames[expqueue]=Job queue
PolicyList_expqueue[]=expqueue/view


# Audit > the events of Exponential Intelligence (doc/audit-trail.md): the channel "ai" in the
# audit console (audit/read), and the usage log for users who see everyone's requests (expai/log)
[Leftmenu_audit]
Links[expai_events]=audit/console/(channel)/ai
LinkNames[expai_events]=AI events
PolicyList_expai_events[]=audit/read
Links[expai_log]=expai/log
LinkNames[expai_log]=EI: Log
PolicyList_expai_log[]=expai/write
PolicyList_expai_log[]=expai/log

# Design > Missing alt text: images without alt text under a subtree (doc/alt-text.md), users with expai/write
[Leftmenu_design]
Links[expai_alttext]=expai/alttext
LinkNames[expai_alttext]=Missing alt text
PolicyList_expai_alttext[]=expai/write

*/ ?>
