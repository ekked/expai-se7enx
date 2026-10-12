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

# The translation sync in the Intelligence tab: expaitranslation::status|check|approve|skip
# (classes/diff/expaitranslationsyncserverfunctions.php). The same permission as expai::*; each function checks
# expai/write, and expai/translation_sync for check, approve and skip.
[ezjscServer_expaitranslation]
Class=expAiTranslationSyncServerFunctions
Functions[]=expai


# Alt text for images (#370 M5 alt text): expaialttext::suggest (classes/alttext/expaialttextserverfunctions.php).
# The same permission and checks as expai::field.
[ezjscServer_expaialttext]
Class=expAiAltTextServerFunctions
Functions[]=expai

# Editor presets and the Improve menu of the edit form: expaipreset::preset
# (classes/fieldactions/expaipresetserverfunctions.php). The same permission and checks as expai::field.
[ezjscServer_expaipreset]
Class=expAiPresetServerFunctions
Functions[]=expai

# Test prompt and Load model of expai/models: expaimodel::status|test|load (classes/expaimodelserverfunctions.php).
# The function list "expai"; each function checks the policy expai/model_manage.
[ezjscServer_expaimodel]
Class=expAiModelServerFunctions
Functions[]=expai

*/ ?>
