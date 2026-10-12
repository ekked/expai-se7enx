<?php /* #?ini charset="utf-8"?

[RegionalSettings]
TranslationExtensions[]=expai

# The template operators expai_money, expai_tokens, expai_number (autoloads/eztemplateautoload.php)
[TemplateSettings]
ExtensionAutoloadPath[]=expai

# The drafts of a package written with Exponential Intelligence (package metadata expai.ai-generated=true), from
# any install as drafts (Setup > Packages, ezpm install --as-drafts, a background install): recorded in the draft log
# (expAiPackageDraftListener, the kernel's event package/install/drafts)
[Event]
Listeners[]=package/install/drafts@expAiPackageDraftListener::installed

*/ ?>
