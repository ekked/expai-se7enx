<?php /* #?ini charset="utf-8"?

# The browser talks to expai through ezjscore: POST <root>/ezjscore/call with
# ezjscServer_function_arguments=expai::<function> and the form token (ezformtoken).
# Functions[]=expai: the caller needs ezjscore/call (unlimited, or limited to the function list "expai");
# every function then checks the policy expai/write itself.
[ezjscServer]
FunctionList[]=expai

[ezjscServer_expai]
Class=expAiServerFunctions
Functions[]=expai

# Threads, Vector and the keyboard: expaivector::<function> (classes/vector/expaivectorserverfunctions.php). The same
# permission as expai::* (the function list "expai"); each function checks expai/write, and expai/vector where needed.
[ezjscServer_expaivector]
Class=expAiVectorServerFunctions
Functions[]=expai

*/ ?>
