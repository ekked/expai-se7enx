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

# Setup > AI facts: the printable "AI facts" page for a data protection officer (doc/ai-facts.md), shown only to users
# with the policy expai/facts (the Administrator role holds every policy).
[Leftmenu_setup]
Links[expai_facts]=expai/facts
LinkNames[expai_facts]=AI facts
PolicyList_expai_facts[]=expai/facts
# Setup > AI models: providers, key presence, probe results, the local model (doc/local-models.md, expai/model_manage)
Links[expai_models]=expai/models
LinkNames[expai_models]=AI models
PolicyList_expai_models[]=expai/model_manage
# Setup > Budgets and lanes: the monthly budget of queued requests, job groups, lanes, batches (doc/budgets.md)
Links[expai_budgets]=expai/budgets
LinkNames[expai_budgets]=Budgets and lanes
PolicyList_expai_budgets[]=expai/log
# Setup > Content packages: upload, validate, dry run, import as drafts (doc/packages.md)
Links[expai_packages]=expai/packages
LinkNames[expai_packages]=Content packages
PolicyList_expai_packages[]=expai/package_import


# Design > Missing alt text: images without alt text under a subtree (doc/alt-text.md), users with expai/write
[Leftmenu_design]
Links[expai_alttext]=expai/alttext
LinkNames[expai_alttext]=Missing alt text
PolicyList_expai_alttext[]=expai/write

*/ ?>
