# Content packages and Exponential Intelligence

Content packages are the kernel's packages, in the classic XML or the JSON format. expai has no package pages,
commands or tables of its own: everything a package needs is in Setup > Packages and `ezpm`, and the format is
described in the kernel's [JSON package format](https://github.com/se7enxweb/exponential/blob/main/doc/bc/6.0/package-json-format.md)
(`doc/bc/6.0/package-json-format.md` of the installation).

| What | Where |
| --- | --- |
| Upload, with validation (every document, the JSON schema, the checksums of package.json) | Setup > Packages > Import package (`package/upload`); refused with the list of what failed |
| What an install meets here, by remote id (create, update, conflict, the parent of each location) | the install page (`package/install/<name>`), before anything is written |
| Install as drafts: nothing is published, an existing object gets a new draft or is left alone | the install page ("Install as drafts"), or `ezpm install <name> --as-drafts [--drafts-existing=update\|skip] -d <node>` |
| A large package | "Install in the background" on the install page: a content job (`packageinstall`) installs it as you; the package page shows its progress and the drafts |
| Validate a package in the repository | Validate on the package's page, or `ezpm validate <archive or directory>` |
| See its files | the package's page: every document as JSON or XML, images inline, other files as a card with their checksum state |
| Make a package from a subtree | Setup > Packages > New package > Content object export (Format: Classic XML or JSON), or `ezpm create`, `ezpm add <name> ezcontentobject <node>`, `ezpm export <name> --format=json` |
| Download | Download / Download as JSON on the package's page, or `ezpm export` |

## What expai adds: provenance and the draft label

**Provenance** is ordinary package metadata (`<metadata><entry key="...">` in package.xml, `["metadata", ["entry",
{"key": ...}, ...]]` in package.json), shown on the package's page like any other entry:

| Key | Value |
| --- | --- |
| `expai.ai-generated` | `true` when the content was written by AI |
| `expai.models` | the models, comma separated |
| `expai.tools` | the tools (`create_draft`, `claude-code`, ...), comma separated |
| `expai.human-reviewed` | `true` or `false` |
| `expai.notes` | free text |
| `expai.generator` | what wrote the package |

**The draft label.** Every install of a package as drafts fires the kernel's event `package/install/drafts` (the
admin's install, `ezpm install --as-drafts`, a background install). expai listens to it
(`expAiPackageDraftListener`, `settings/site.ini.append.php` `[Event] Listeners[]`): when the package's metadata says
`expai.ai-generated` = `true`, each draft it gave is recorded in `expai_draft_log` with the source `package` (and the
background job's id), so the draft carries the "Written with Exponential Intelligence" label and is listed with the
drafts of expai (EI: Log, the Intelligence tab). Drafts of any other package are not recorded. The listener runs
where expai is an active extension of the siteaccess.

## Data policy

A package goes wherever it is sent. expai's data policy ([data-policy.md](data-policy.md)) applies to the content
expai itself sends to a model; a package made in Setup > Packages or with `ezpm` is the kernel's and is not checked
by it. When a CLI agent (see [package-authoring-for-agents.md](package-authoring-for-agents.md)) works on exported
content, export only what may leave the site: content of a section or class with region or zero-retention rules in
`expai.ini` should not be exported for an agent.
