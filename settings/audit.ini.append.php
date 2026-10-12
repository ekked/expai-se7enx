<?php /* #?ini charset="utf-8"?

# The audit trail of Exponential Intelligence (doc/audit-trail.md). The kernel's audit writer
# records expai's requests, drafts, chain and model actions, budget holds and MCP calls into a
# channel of its own, "ai" (var/log/audit/ai-<day>.jsonl), with a hash chain like every channel.
# A record never holds a prompt, an answer, a key or personal data beyond the user id.

[AuditEventSettings]
# The names, their severity and channel (classes/audit/expaiauditbranch.php); all are on.
# Switch one off in settings/override/audit.ini.append.php, e.g.
#   Disabled[]=system.expai.request
Branches[expai]=expAiAuditBranch

[AuditChannelSettings]
Channels[]=ai

# The channel's files: as long as the content channel.
[AuditChannel_ai]
LiveDays=90
ArchiveDays=730
MaxFileSize=64M
ArchiveFormat=gzip
Sinks[]

[AuditSourceSettings]
# The panel, links, event fields and chart of expai in the audit console (audit/dashboard,
# audit/console, audit/event, audit/charts ...): classes/audit/expaiauditsource.php
Sources[expai]=expAiAuditSource

[AuditAlertSettings]
Rules[]=expai_request_failures

# Many failed model requests in a short time, per model: a provider outage, a revoked key,
# a budget at the provider. Shown in audit/alerts; written to syslog.
[AlertRule_expai_request_failures]
Class=threshold
Event=system.expai.request.failed
Threshold=20
Window=600
GroupBy=object.name
Severity=warning
Sinks[]=syslog

*/ ?>
