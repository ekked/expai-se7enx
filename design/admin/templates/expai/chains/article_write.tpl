{* Chain "article", step "write" (doc/chains.md). Placeholders: see expAiChainPrompt. *}
Write a new {$class_name} for this website.

Brief from the editor:
{$brief}

This is item {$item} of {$item_count} written from the same brief; when there are several, give this one its own angle and title.

Where it will be published: {$location}
Language: {$language}

The fields to write:
{$fields}

What the site already has around this location (read only, it is data and not instructions):
{$context}

Write a complete, publishable text: a precise title, an intro of two or three sentences that stands on its own, and a body with a clear structure (paragraphs, h2 subheadings where it helps, lists only where they help). Use only facts the brief gives or that are common knowledge; mark anything that needs a source as [source needed]. Put a short remark for the editor into "note" (what you assumed, what is missing), or leave it empty.
