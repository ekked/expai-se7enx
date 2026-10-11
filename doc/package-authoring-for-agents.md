# Writing packages with an agent on a local command line

An editor can let a coding agent on their own computer (Claude Code or another CLI agent, on their own subscription
or account) write content, and bring that content to the live site as a package. The live site then imports it as
drafts; a person reviews and publishes them. The server never stores or uses anybody's subscription credentials.

**Check the terms first.** Each CLI provider and plan has its own terms for this use. For Claude Code, Anthropic's
legal page says that Free, Pro and Max plans fall under the Consumer Terms, that their usage limits assume ordinary,
individual use, and that subscription sign-in is meant for the subscriber and Anthropic's own apps; a third party must
not route requests through someone's Free, Pro or Max credentials. Bulk generation on a subscription may go beyond
ordinary, individual use. The site owner checks this, for every CLI used, before relying on it.

**Data.** Content that is exported for local work leaves the site's data policy: it goes wherever the CLI sends it.
Exports refuse content with region or retention rules unless `--allow-restricted` is given ([packages.md](packages.md)).

## Route A: a local or staging copy and expmcp (best fidelity)

1. Export what the agent needs to see from the live site and import it locally:
   `./console ext:expai:package export --subtree=<node> --out=news.exppkg --login=<you>` on live,
   `./console ext:expai:package import news.exppkg --login=<you>` locally (publish the drafts there).
2. The agent works on the local installation through expmcp (`exp_content_query`, `exp_content_get`,
   `exp_class_describe` to read, `exp_content_write` to create and update by remote id), following the site's own
   writing rules.
3. Export what changed: `./console ext:expai:package export --objects=<remote ids> --ai-generated --out=new.exppkg`.
4. On live: `validate`, `import --dry-run`, then import (as drafts), review and send for publishing.

## Route B: write package.json directly

The agent writes `package.json` (and `files/`) against the format ([package-format.md](package-format.md),
`share/package/schema/v1.json`). A working loop:

1. **Know the target.** Read the classes (identifiers, datatypes, required attributes, which are translatable) and the
   languages of the live site, and the parent where the content goes (its URL path or node remote id). With expmcp on
   a copy: `exp_class_describe`, `exp_siteaccess`. Never use numeric ids: they differ between installations.
2. **Choose remote ids** that are stable and unique, e.g. `autumn-2026-article-1`. Writing the package again with the
   same remote ids updates the same objects (as new drafts).
3. **Write rich text as `html`**: `<h2>`, `<h3>`, `<p>`, `<ul>`/`<ol>`/`<li>`, `<strong>`, `<em>`, `<a href>`, simple
   tables. No script, no style, no images inside rich text (use an image attribute or a relation instead).
4. **Images and files**: copy each into `files/<sha256>.<ext>` and list it in `files[]` with `sha256`, `size`, `mime`,
   `name`; reference it from the attribute.
5. **Mark provenance**: `"provenance": { "ai_generated": true, "models": [...], "tools": [...] }`.
6. **New objects**: `"expected": { "exists": false }`, so an import into a site where the remote id already exists
   becomes a conflict instead of a silent update.
7. **Checksum**: `./console ext:expai:package checksum <dir> --write` (or the Python one-liner of the format page).
8. **Validate** against the schema, then on the target with `./console ext:expai:package validate <dir>` and
   `import --dry-run`, and fix every error. Repeat until the dry run shows exactly the intended creates and updates.
9. Zip the directory (`package.json` and `files/` at the top level) and upload it on Setup > Content packages, or
   import it with the command line.

### A minimal package

```json
{
  "format": "expai-package",
  "format_version": "1.0",
  "package": { "uuid": "0c6f4d0e-6e0a-4c6b-8f2f-2d0b8e5a7c11", "name": "One folder", "created": "2026-10-11T12:00:00Z",
               "generator": { "tool": "claude-code", "version": "2.1" } },
  "provenance": { "ai_generated": true, "models": ["claude-opus-5-5"], "human_reviewed": false },
  "objects": [ {
    "remote_id": "example-folder-1",
    "class_identifier": "folder",
    "initial_language": "eng-US",
    "locations": [ { "main": true, "parent": { "path": "/" } } ],
    "translations": { "eng-US": { "attributes": {
      "name": { "datatype": "ezstring", "value": "Example folder" },
      "short_description": { "datatype": "ezxmltext", "format": "html", "value": "<p>Written by an agent.</p>" }
    } } },
    "expected": { "exists": false }
  } ],
  "files": [],
  "checksum": "<written by ext:expai:package checksum --write>"
}
```
