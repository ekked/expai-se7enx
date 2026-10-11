# Content packages

A content package carries objects from one place to another: from a staging installation to the live one, from a
local copy where an agent wrote articles, from another site. On import it **always becomes drafts first**: a person
reviews them and sends them for publishing (approval workflows apply). The format is described in
[package-format.md](package-format.md), with its JSON Schema `share/package/schema/v1.json`; how an agent on a local
command line produces one is in [package-authoring-for-agents.md](package-authoring-for-agents.md).

## Trust

Packages are untrusted input. There is no signing (owner decision): the manifest checksum and the sha256 of every file
prove that a package arrived as it was written, not who wrote it. That is why an import never publishes.

- A zip may contain only `package.json` and `files/<sha256>.<ext>`: no other names, no directories but `files/`, no
  `..`, no links; sizes, file counts and the compression ratio are limited (`[PackageSettings]`).
- `package.json` is read with a size and nesting limit.
- Rich text in `ezxml` is parsed without network access, entities or a DOCTYPE; script, style, iframe and object
  elements are refused; `href` values with `javascript:`, `data:` or `vbscript:` are removed. Rich text in `html` goes
  through the kernel's input parser after the assistant's sanitizer (scripts and event handlers are removed).
- File types are detected (finfo) and must be in `AllowedMimeTypes[]`; SVG is always refused; an image attribute
  needs an image.
- An attribute of a datatype a package cannot carry is refused, never skipped silently.
- Everything is written as the importing user, with that user's `content/create` and `content/edit` permissions.

## The command line

```
./console ext:expai:package export   --subtree=<node> [--children-only] | --objects=<remote id,...> --out=<dir|file.exppkg> --login=<user>
./console ext:expai:package validate <package>
./console ext:expai:package diff     <package> [--remap=<prefix>]
./console ext:expai:package import   <package> --login=<user> [--dry-run] [--on-conflict=skip|draft|fail] [--remap=<prefix>] [--parent=<node>] [--queue [--wait]]
./console ext:expai:package checksum <package dir> [--write]
./console ext:expai:package show     <uuid>
```

`<package>` is a directory with `package.json`, a `.exppkg` / `.zip` file, or a `package.json` file. `--languages=eng-US,ger-DE`
limits export, diff and import to some translations; `--json` prints the report as JSON.

### Export

As the user of `--login` (policy `expai/package_export`): only what the user may read. A subtree is written parents
first. Ids become remote ids: parents (`object_remote_id` of an object in the package, else the parent node's remote id
and URL path), relations, links and embeds in rich text (links to URLs become `href`). Images and files are copied
once each under their sha256. Each object carries `expected` (exists, modified, current version, content hash) for
conflict detection on the same installation. Attributes of datatypes a package cannot carry are left out and listed
in the package's `warnings`.

The data policy applies: content whose section or class has a region or zero-retention rule ([data-policy.md](data-policy.md))
may end up wherever the package goes, so it is refused unless `--allow-restricted` is given (logged).

### Validate

The format, the manifest checksum (required unless `RequireChecksum=disabled`), every file (sha256, size, type),
classes, attributes and their datatypes, languages of the installation, rich text, relations and parents that can be
resolved. Errors make a package unusable; warnings (a parent that cannot be found, a relation to an object that does
not exist) are imported anyway.

### Diff and dry run

`diff` compares every attribute of every object with the published content, in the package's own form (rich text as
canonical XML, files by their sha256, dates as timestamps; rich text given as `html` is compared as text). Nothing is
written. `import --dry-run` adds the plan: per object `create`, `update`, `conflict`, `error` or `wait_parent`.

### Import

| Object | Draft |
| --- | --- |
| remote id new | **create**: one new object (version 1, a draft) with every translation of the package, below the parent of its main location; additional locations as further node assignments |
| remote id exists, same class | **update**: one draft per translation (a new translation is copied from the main language first) |
| a parent that is another object of the package and not published yet | **wait_parent**: import again after publishing the parent |
| remote id of another class, unknown class or attribute | **error** |

Conflicts (only without `--remap`):

- the package expected a new object (`expected.exists: false`) and the remote id exists;
- the object was modified after the export the package was made from (`expected.modified`);
- its content changed since (`expected.content_hash`).

`--on-conflict=skip` (default) leaves such an object alone, `draft` writes a draft anyway and reports it as a conflict
for a person to merge, `fail` imports nothing when any object has a conflict or an error.

`--remap=<prefix>` imports the package as copies: every remote id of the package's own objects becomes
`md5(<prefix>:<remote id>)` (relations and embeds between them too), and `expected` is ignored. `--parent` names the
parent (node id, node remote id or URL path) of every object whose parent is not in the package.

Every draft is recorded in `expai_draft_log` with source `package` and the package's uuid (and the job's uuid for a
queued import), so the drafts appear in the user's draft lists with their label.

A package with more than `QueueAbove` (20) objects, or any package with `--queue`, is imported as a job group of the
job queue: jobs of `ChunkSize` (5) objects each, in package order, class `bulk`. Each job checkpoints after every
object, so a job that runs again does not import an object twice. `--wait` waits up to an hour for the queue's
workers. The group's page shows the progress; it can be paused and cancelled.

## Setup > Content packages (`expai/packages`)

Policy `expai/package_import`. View parameters only: `(package)/<uuid>` one package, `(offset)/<n>` the list.

1. **Upload** a `.exppkg` / `.zip` file or a `package.json` (up to `MaxUploadBytes`; larger packages with the command
   line). It is kept in `var/<vardir>/expai/packages/<uuid>/` (never served) and validated at once.
2. **Validation** report: errors and warnings.
3. **Dry run** with the options (on conflict, parent, copies): the plan per object and the differences per language and
   attribute (live value struck through, package value underlined).
4. **Import as drafts**: in the request for a small package, as a job group for a large one (the page shows the
   group's progress and collects the results of its jobs).
5. The results link to every draft (`content/versionview`), where it is reviewed, edited and sent for publishing.

A user sees the packages they uploaded; with `expai/log` all of them.

## Settings

`[PackageSettings]` ([configuration.md](configuration.md#packagesettings-content-packages)): `MaxObjects`, `MaxFiles`, `MaxBytes`,
`MaxFileBytes`, `MaxJsonBytes`, `MaxCompressionRatio`, `MaxUploadBytes`, `QueueAbove`, `ChunkSize`, `RequireChecksum`,
`AllowedMimeTypes[]`, `StringDatatypes[]`.

## Tables

`expai_package` (one row per stored package: name, checksum, counts, storage, user, state uploaded / valid / invalid /
importing / imported / failed, the reports of validation, dry run and import as JSON). See [database.md](database.md).

## Tests

`tests/expaipackage_test.php`: a subtree with a translation, rich text with a link and an embed, an image, a file,
keywords, a relation and a nested object is exported, validated, imported into another folder as copies (drafts),
published, imported again for the nested object, published, and the diff shows no difference; conflicts of each kind;
refused packages (checksum, file hashes, `..` and foreign names in a zip, script, DOCTYPE with an external entity, an
unsupported datatype, an unknown class); script removed from html rich text; 200 objects imported through the queue
(40 jobs).
