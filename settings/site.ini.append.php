<?php /* #?ini charset="utf-8"?

[RegionalSettings]
TranslationExtensions[]=expai

# The template operators expai_money, expai_tokens, expai_number, expai_help_url (autoloads/eztemplateautoload.php)
[TemplateSettings]
ExtensionAutoloadPath[]=expai

# The drafts of a package written with Exponential Intelligence (package metadata expai.ai-generated=true), from
# any install as drafts (Setup > Packages, ezpm install --as-drafts, a background install): recorded in the draft log
# (expAiPackageDraftListener, the kernel's event package/install/drafts)
[Event]
Listeners[]=package/install/drafts@expAiPackageDraftListener::installed
# The menu entry EI: Help leads to the help center (menu.ini.append.php); on a kernel without it (Exponential 6.0.14)
# it leads to expai/help, which redirects to GitHub (expAiHelpLinks::menuFallback(), changes nothing where it is)
Listeners[]=request/input@expAiHelpLinks::menuFallback

*/ ?>
