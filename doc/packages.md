# Content packages

A content package carries objects from one place to another: from a staging installation to the live one, from a
local copy where an agent wrote articles, from another site. Since 2026-10 expai has no package format of its own:
a content package **is an Exponential kernel package** (`.ezpkg`, Setup > Packages, `ezpm`), in the classic XML
format or in the kernel's **JSON format** (`package.json`). The format, its JSON Schema, validation, the report of
what an install meets and the install as drafts are the kernel's; see
[the JSON package format](https://github.com/se7enxweb/exponential/blob/main/doc/bc/6.0/package-json-format.md)
(`doc/bc/6.0/package-json-format.md` of the installation, schema `share/package/schema/package-v1.json`). How an agent
on a local command line writes one: [package-authoring-for-agents.md](package-authoring-for-agents.md).

What expai adds on top:

| | |
|---|---|
| **Drafts only** | An import through expai never publishes: it is the kernel's install as drafts. A person reviews each draft and sends it for publishing (approval workflows apply). |
| **Provenance** | Whether the content was written by AI, the models and tools, whether a person reviewed it: package metadata (`expai.ai-generated`, `expai.models`, `expai.tools`, `expai.human-reviewed`, `expai.notes`, `expai.generator`), shown on expai/packages and on the package's page on Setup > Packages. |
| **The label** | Every draft is recorded in `expai_draft_log` with source `package` and the package, so it carries the Exponential Intelligence label in the draft lists. |
| **The job queue** | A package with more than `QueueAbove` (20) content objects, or any with `--queue`, is installed in an `expai.package.import` job. |
| **Data policy** | `export` refuses content whose section or class has a region or zero-retention rule ([data-policy.md](data-policy.md)) unless `--allow-restricted` (logged). |

## Trust

Packages are untrusted input. There is no signing: the SHA-256 of every file in `package.json` proves that a JSON
package arrived as it was written, not who wrote it. That is why an import through expai never publishes.

- The archive passes the kernel's upload checks (`eZPackageUploadInspector`): a gzip compressed tar of at most
  `package.ini [UploadSettings]` size and entries, only plain files and directories with safe relative paths.
- The kernel validates it before it is kept: JSON documents against the schema, every document as XML (no DOCTYPE, no
  entities, no network), the definition, the documents the items name, the checksums.
- Only content objects are written, as drafts. Classes, settings, design files, templates, extensions and install
  scripts of the package are not installed by expai (install the package on Setup > Packages for those, which needs
  `package/install`).
- Everything is written as the importing user (`user_id` of the install), with that user's policies for the parent.

## The command line

```
./console ext:expai:package export   --subtree=<node> --out=<dir> --login=<user> -s <admin siteaccess> [--name=..] [--summary=..] [--languages=eng-US,ger-DE]
                                     [--format=json|xml] [--ai-generated] [--models=a,b] [--tools=a,b] [--notes=..] [--allow-restricted]
./console ext:expai:package validate <archive or directory>
./console ext:expai:package check    <archive> --login=<user>
./console ext:expai:package import   <archive> --login=<user> [--dry-run] [--existing=update|skip] [--parent=<node>] [--queue [--wait]]
./console ext:expai:package show     <uuid>
```

`--json` prints the report as JSON. The kernel's own commands do the same without the expai parts:
`ezpm export <package> --format=json`, `ezpm validate`, `ezpm check`, `ezpm install <package> --as-drafts`.

- **export** (policy `expai/package_export`): the subtree as a kernel package (JSON by default), content only (no
  classes, no templates), with its provenance as metadata, written as `<name>-1.0-1.json.ezpkg`. The kernel's handler
  follows the siteaccess's `ShowHiddenNodes`: with `-s <admin siteaccess>` hidden content is included, with a public
  siteaccess it is not. The command prints how many objects the package holds.
- **validate**: the kernel's checks; nothing is kept or imported.
- **check** (policy `expai/package_import`): keeps the package (validated, imported into the package repository
  `local`) and prints what an install meets here, per object by remote id: `create`, `update` (exists: a new draft),
  `conflict` (the remote id is an object of another class, or a node remote id belongs to another object), `error`
  (the class is missing), and where each location goes.
- **import** (policy `expai/package_import`, `--login` required): keeps the package and installs it as drafts.
  `--existing=update` (default) gives an existing object a new draft, `skip` leaves it alone. `--parent` names the
  parent of the package's top objects (node id, node remote id or URL path; default the content root).
  `--dry-run` reports only.
- **show**: a kept package and its last reports.

The commands `diff` and `checksum` of the former expai-only format are gone: the kernel writes the checksums of a JSON
package itself, and `check` reports by remote id.

### Drafts and parents

A new object becomes an object with one draft; an existing one gets a new draft version; nothing published changes.
A draft whose parent is another new object of the package waits for it (a draft parent has no node yet): publish the
parent, then import the package again with `--existing=update` (the parent is found by its node remote id, and the
child's new draft gets its place). The import report lists the waiting drafts.

## Setup > Content packages (`expai/packages`)

Policy `expai/package_import`. View parameters only: `(package)/<uuid>` one package, `(offset)/<n>` the list.

1. **Upload** an `.ezpkg` (classic or JSON, up to `MaxUploadBytes`; larger packages with the command line). It is
   validated at once and, when valid, imported into the kernel's package repository (`local`), where Setup > Packages
   shows it too. A package of the same name that expai imported before is replaced; a package of the same name that
   came another way is not touched (rename yours).
2. **Validation**: the kernel's report, errors and warnings.
3. **Dry run**: what an install meets here, per object by remote id, with the options (existing objects, parent).
4. **Import as drafts**: in the request for a small package, as a queue job for a large one (the page shows the job's
   progress and collects its result).
5. The results link to every draft (`content/versionview`), where it is reviewed, edited and sent for publishing.

A user sees the packages they uploaded; with `expai/log` all of them. The page links to the package on Setup >
Packages and to its JSON download.

## Settings

`[PackageSettings]` ([configuration.md](configuration.md#packagesettings-content-packages)): `MaxUploadBytes`,
`QueueAbove`. The format's own settings are the kernel's: `package.ini [PackageFormatSettings]` and `[UploadSettings]`.

## Tables

`expai_package` (one row per kept package: name, the SHA-256 of the archive, the format (`format_version`: `xml` or
`json`), counts, the kernel package's name (`storage`), user, state uploaded / valid / invalid / importing / imported /
failed, the reports of validation, dry run and import as JSON). See [database.md](database.md).

## Tests

`tests/expaipackage_test.php`: a test subtree with a translation, rich text with a link and an embed, an image, a
file, keywords, a relation and a nested object is exported with provenance (JSON), validated, kept (the conflict
report says update for every object), imported as drafts of the existing objects (nothing published changes, every
draft in the draft log with the package), removed and imported again as new drafts (the children wait for their
parent), published parent first; refused archives (a file whose checksum does not match, a path outside the package);
the data policy refusal; a queued import through the job queue.
