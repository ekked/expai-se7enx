<?php /* #?ini charset="utf-8"?

# The cronjob part "expai": removes usage rows older than expai.ini [UsageSettings] RetentionDays.
#   php runcronjobs.php -s <admin siteaccess> expai
# (or add Scripts[]=expai_prune.php to a part your crontab already runs, e.g. infrequent)
[CronjobSettings]
ExtensionDirectories[]=expai

[CronjobPart-expai]
Scripts[]=expai_prune.php

*/ ?>
