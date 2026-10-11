# The package format (expai-package 1.0)

The JSON Schema is `share/package/schema/v1.json` (draft 2020-12); any JSON Schema validator can check a package
against it, and `./console ext:expai:package validate` checks it against the format and the installation. This page is
the reference of every member.

## Container

```
package.json
files/<sha256>.<ext>      every image and file the attributes name, named by its SHA-256
```

A directory, or a zip file (`.exppkg`, `.zip`) with exactly these entries. Nothing else is accepted.

## package.json

```json
{
  "format": "expai-package",
  "format_version": "1.0",
  "package": {
    "uuid": "8f6c2a8e-2f1b-4c55-9d7e-1b2c3d4e5f60",
    "name": "Autumn articles",
    "created": "2026-10-11T12:00:00+02:00",
    "description": "Three articles for the news section",
    "generator": { "tool": "claude-code", "version": "2.1" },
    "source_site": "staging"
  },
  "provenance": { "ai_generated": true, "models": ["claude-opus-5-5"], "tools": ["expmcp"], "human_reviewed": false },
  "objects": [
    {
      "remote_id": "autumn-2026-article-1",
      "class_identifier": "article",
      "section_identifier": "standard",
      "always_available": false,
      "initial_language": "eng-US",
      "locations": [ { "main": true, "priority": 0, "parent": { "path": "/News" } } ],
      "translations": {
        "eng-US": {
          "name": "Leaves and light",
          "attributes": {
            "title": { "datatype": "ezstring", "value": "Leaves and light" },
            "intro": { "datatype": "ezxmltext", "format": "html", "value": "<p>The first cold morning ...</p>" },
            "body":  { "datatype": "ezxmltext", "format": "html", "value": "<h2>Colors</h2><p>See <a href=\"https://example.org\">the map</a>.</p>" },
            "enable_comments": { "datatype": "ezboolean", "value": false },
            "image": { "datatype": "ezobjectrelation", "remote_id": "autumn-2026-image-1" }
          }
        },
        "ger-DE": { "attributes": { "title": { "datatype": "ezstring", "value": "Blätter und Licht" } } }
      },
      "expected": { "exists": false }
    }
  ],
  "files": [
    { "path": "files/3f0c...9a.jpg", "sha256": "3f0c...9a", "size": 182044, "mime": "image/jpeg", "name": "leaves.jpg" }
  ],
  "warnings": [],
  "checksum": "5d1b...e2"
}
```

| Member | Required | Meaning |
| --- | --- | --- |
| `format`, `format_version` | yes | `expai-package`, `1.x` |
| `package.uuid` | yes | a lower-case UUID of this package |
| `package.name`, `package.created` | yes | a name (up to 150 characters), an ISO 8601 time |
| `package.description`, `generator`, `source_site` | no | information shown on the package page |
| `provenance` | no | `ai_generated` (the drafts are written by AI: say so), `models`, `tools`, `human_reviewed`, `notes` |
| `objects[]` | yes, at least one | the objects, parents before children |
| `files[]` | yes (may be empty) | every file of `files/`: `path`, `sha256`, `size`, `mime`, `name` (the original file name) |
| `warnings[]` | no | what the exporter left out, and why |
| `checksum` | yes | the manifest checksum (below) |

### An object

| Member | Required | Meaning |
| --- | --- | --- |
| `remote_id` | yes | 1-100 characters of `A-Z a-z 0-9 _ . : -`; the identity of the object everywhere; never a numeric id |
| `class_identifier` | yes | a class of the target installation |
| `initial_language` | yes | the main language; one of `translations` |
| `locations[]` | yes, at least one | `parent` (one of `object_remote_id`: an object of the package or of the installation, its main node; `node_remote_id`; `path`: the URL path), `main` (one main location), `priority`, `hidden`; `node_remote_id` is information |
| `translations` | yes | language => `{ name, attributes }`; a language of the target installation |
| `section_identifier`, `always_available` | no | information; a new object gets its parent's section |
| `expected` | no | `exists`, `modified` (Unix time), `current_version`, `content_hash`: what the package expects of the target, for conflicts |

### Attribute values

Every value names its `datatype`, which must be the attribute's datatype in the target class.

| Datatype | Value |
| --- | --- |
| `ezstring`, `eztext`, `ezemail` | `{ "value": "text" }` |
| `ezinteger`, `ezfloat` | `{ "value": 12 }` or `null` |
| `ezboolean` | `{ "value": true }` |
| `ezdate`, `ezdatetime` | `{ "value": "2026-10-11T12:00:00+02:00" }` or `null` |
| `ezkeyword` | `{ "value": ["autumn", "nature"] }` |
| `ezurl` | `{ "url": "https://example.org", "text": "Example" }` |
| `ezxmltext` | `{ "format": "html", "value": "<p>...</p>" }`: headings, paragraphs, lists, tables, links, strong, emphasis (the subset the assistant writes); or `{ "format": "ezxml", "value": "<?xml ...?><section ...>...</section>" }`: lossless, with `href` on links and `object_remote_id` / `node_remote_id` on links and embeds |
| `ezimage` | `{ "file": "files/<sha256>.<ext>", "alt": "text", "filename": "leaves.jpg" }`, `file` null to keep only the alternative text |
| `ezbinaryfile`, `ezmedia` | `{ "file": "files/<sha256>.<ext>", "filename": "report.pdf" }` |
| `ezobjectrelation` | `{ "remote_id": "..." }` or `null` |
| `ezobjectrelationlist` | `{ "remote_ids": ["...", "..."] }` |
| `[PackageSettings] StringDatatypes[]` (`ezisbn`, `ezselection`, `ezcountry`) | `{ "value": "the datatype's toString() text" }` |

An attribute that is not translatable is written in the main language only; in other translations it is ignored.
Relations and embeds to objects that are neither in the package nor in the installation are left out on import (a
warning); links to them keep their text.

## The manifest checksum

`checksum` is the SHA-256 (lower-case hex) of the **canonical JSON** of the package without the `checksum` member:

- object members sorted by name (byte order) at every level; arrays keep their order;
- no whitespace between tokens;
- strings with UTF-8 characters and `/` written as they are, only the characters JSON requires escaped (`"`, `\`,
  control characters as `\n`, `\t`, `\u00XX`);
- numbers as they are; a float keeps its fraction (`1.0`).

`./console ext:expai:package checksum <dir> --write` computes and writes it. In Python the same is
`hashlib.sha256(json.dumps(data_without_checksum, sort_keys=True, separators=(',', ':'), ensure_ascii=False).encode()).hexdigest()`.

Each file's `sha256` is the SHA-256 of its bytes and its name; `size` its length in bytes.

## Versions

A reader of format 1.x accepts any 1.x package; members a newer minor version adds are refused by this one until it
knows them (the format is closed: `additionalProperties: false`), so a package written for 1.1 states `format_version`
1.1 and is validated by an expai that knows 1.1.
