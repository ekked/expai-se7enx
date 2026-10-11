{* Chain "article", step "review" (doc/chains.md). *}
Review this draft of a {$class_name} strictly, as an experienced editor would before publishing.

The editor's brief:
{$brief}

The draft (JSON, field identifier: value; HTML in rich text fields):
{$document}

Check every item of this checklist and answer each one with ok true or false, using the item text as given:
{$checklist}

Give a score from 0 (unusable) to 100 (ready to publish as it is). 80 means: publishable after small edits. List the concrete issues that should change, each with a severity (high: wrong, misleading, missing what the brief asks for, or invented facts; medium: clearly weaker than it should be; low: polish) and the field it concerns. Do not list matters of taste as issues. Summarize your verdict in one or two sentences.
