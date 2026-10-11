<?php /* #?ini charset="utf-8"?

# The entry of expai/dashboard in the left menu of the Dashboard tab (design:parts/my/menu.tpl reads
# [Leftmenu_my]); shown only to users with the policy expai/write.
[Leftmenu_my]
Links[expai]=expai/dashboard
LinkNames[expai]=Exponential Intelligence
PolicyList_expai[]=expai/write
# Vector, the workspace of threads (doc/vector.md): users with expai/write and expai/vector
Links[expai_vector]=expai/vector
LinkNames[expai_vector]=Vector
PolicyList_expai_vector[]=expai/write
PolicyList_expai_vector[]=expai/vector
Links[expai_log]=expai/log
LinkNames[expai_log]=Exponential Intelligence log
PolicyList_expai_log[]=expai/write

# Setup > AI facts: the printable "AI facts" page for a data protection officer (doc/ai-facts.md), shown only to users
# with the policy expai/facts (the Administrator role holds every policy).
[Leftmenu_setup]
Links[expai_facts]=expai/facts
LinkNames[expai_facts]=AI facts
PolicyList_expai_facts[]=expai/facts

*/ ?>
