# Writing packages with an agent on a local command line

An editor can let a coding agent on their own computer (Claude Code or another CLI agent, on their own subscription
or account) write content, and bring that content to the live site as a package. The live site then imports it as
drafts; a person reviews and publishes them. The server never stores or uses anybody's subscription credentials.

The package is an ordinary Exponential kernel package in the **JSON format** (`package.json`), described with its
JSON Schema in [the JSON package format](https://github.com/se7enxweb/exponential/blob/main/doc/en/us/bc/6.0/package-json-format.md)
(`doc/en/us/bc/6.0/package-json-format.md`, schema `share/package/schema/package-v1.json` of the installation). It is the
classic package mirrored element for element in JsonML, so whatever the kernel's package handlers read, the agent can
write, and a package exported from the site is the best template.

**Check the terms first.** Each CLI provider and plan has its own terms for this use. For Claude Code, Anthropic's
legal page says that Free, Pro and Max plans fall under the Consumer Terms, that their usage limits assume ordinary,
individual use, and that subscription sign-in is meant for the subscriber and Anthropic's own apps; a third party must
not route requests through someone's Free, Pro or Max credentials. Bulk generation on a subscription may go beyond
ordinary, individual use. The site owner checks this, for every CLI used, before relying on it.

**Data.** Content that is exported for local work leaves the site's data policy: it goes wherever the CLI sends it.
Export only content that may leave the site: not content of a section or class with region or zero-retention rules
in `expai.ini` ([packages.md](packages.md#data-policy)).

## Route A: a local or staging copy and expmcp (best fidelity)

1. Export what the agent needs to see from the live site and install it locally:
   on live, Setup > Packages > New package > Content object export (Format: JSON), then Download as JSON on its
   page (or `./console ezpm create <name>`, `./console ezpm add <name> ezcontentobject <node>`,
   `./console ezpm export <name> --format=json -d <dir>`);
   `./console ezpm import <file>.json.ezpkg` and `./console ezpm install <name>` locally.
2. The agent works on the local installation through expmcp (`exp_content_query`, `exp_content_get`,
   `exp_class_describe` to read, `exp_content_write` to create and update by remote id), following the site's own
   writing rules.
3. Export what changed (`./console ezpm export <name> --format=json`) and mark it as written by AI: the metadata
   entries `expai.ai-generated`, `expai.models`, `expai.tools` in its package.json (see step 7 below).
4. On live: upload it on Setup > Packages > Import package (validated before it is imported); the install page shows
   what the install meets, by remote id; choose "Install as drafts" (and "Install in the background" for a large
   package), review the drafts and send them for publishing.

## Route B: write the JSON documents directly

A package is a directory (packed as a gzip compressed tar, `.ezpkg`) with:

```
package.json                         the definition: name, version, the install items, the simple files
ezcontentobject/contentobjects.json  the content objects (the kernel's object serialization, in JsonML)
simplefiles/<key>.<ext>              images and files the objects name by their file key
```

A working loop:

1. **Start from an export.** Export a subtree of the site with an object of each class the agent will write
   (`ezpm export <package> --format=json`, or Download as JSON on Setup > Packages). Its `contentobjects.json` shows exactly
   how each datatype serializes; copy and change it rather than writing from nothing.
2. **Know the target.** The classes (identifiers, `class_remote_id`, the attributes and their datatypes) and the
   languages of the live site, and the **node remote id** of the parent where the content goes. With expmcp on a
   copy: `exp_class_describe`, `exp_siteaccess`, `exp_content_meta`. Numeric ids (`ezremote:id`, `node-id`,
   `ezremote:class_id`) are information only; remote ids decide.
3. **Choose remote ids** that are stable and unique (`remote_id` of the object, `remote-id` of each node
   assignment), e.g. `autumn-2026-article-1` and `autumn-2026-article-1-node`. Writing the package again with the
   same remote ids gives the same objects new drafts.
4. **Place the objects.** A node assignment with `parent-node-remote-id` goes below that node: an existing node of
   the site, or a node assignment of another object in the package (parents before children). Without it, it goes
   below the node chosen on the install page (or `ezpm install -d <node>`).
5. **Rich text** (`ezxmltext`) is `["#xml", "<section ...>...</section>"]`: the editor's XML (`paragraph`,
   `header`, `strong`, `emphasize`, `link href=...`, `ul`/`ol`/`li`, `table`), with the namespaces of the section
   element. No HTML, no script.
6. **Images and files**: put the file in `simplefiles/`, add a `simple-file` (`key`, `original-path`,
   `package-path`) to `simple-files` in `package.json`, and name the key in the attribute (`image-file-key` of an
   `ezimage`, see the export).
7. **Mark provenance** in the definition: a `metadata` element with `expai.ai-generated` = `true`, `expai.models`,
   `expai.tools` (see the example).
8. **Validate**: `./console ezpm validate <dir>`: the schema, every
   document as XML, the definition and the items. Checksums are optional while writing; the kernel writes them when
   it packs: `./console ezpm convert <dir> <name>.json.ezpkg --to=json`.
9. **Check and install**: `./console ezpm import <file>` and `./console ezpm check <name> --all`, or the install page after the upload, shows `create` / `update` / `conflict` / `error`
   per object; fix every conflict and error, then install it as drafts (Setup > Packages, or `ezpm install --as-drafts`).

### A minimal package

`package.json`:

```json
{
    "format": "exponential-package",
    "format-version": "1.0",
    "kind": "definition",
    "source": "package.xml",
    "root": ["package",{"version":"3.5.2","development":"false","install_type":"install"},
        ["name","autumn_articles"],
        ["summary","Autumn articles"],
        ["packaging",["timestamp","1791763200"],["host","localhost"]],
        ["version",["number","1.0"],["release","1"]],
        ["licence","GPL-2.0-or-later"],
        ["state","stable"],
        ["install",["item",{"type":"ezcontentobject","filename":"contentobjects","sub-directory":"ezcontentobject"}]],
        ["metadata",
            ["entry",{"key":"expai.ai-generated"},"true"],
            ["entry",{"key":"expai.models"},"claude-opus-5-5"],
            ["entry",{"key":"expai.tools"},"claude-code"]
        ]
    ]
}
```

`ezcontentobject/contentobjects.json` (one folder below the existing node with the remote id `news-node`):

```json
{
    "format": "exponential-package",
    "format-version": "1.0",
    "kind": "document",
    "source": "ezcontentobject/contentobjects.xml",
    "root": ["content-object",{"xmlns:ezremote":"http://ez.no/ezobject","xmlns:ezobject":"http://ez.no/object/"},
        ["object-list",
            ["ezremote:object",{"name":"Leaves and light","remote_id":"autumn-2026-folder-1","class_remote_id":"a3d405b81be900468eb153d774f4f0d2","ezremote:class_identifier":"folder","ezremote:section_id":"1","ezremote:always_available":"0","initial_language":"eng-US"},
                ["ezobject:version-list",{"active_version":"1"},
                    ["ezobject:version",{"ezremote:version":"1","ezremote:status":"1","initial_language":"eng-US"},
                        ["ezobject:object-translation",{"language":"eng-US","object_name":"Leaves and light"},
                            ["ezobject:attribute",{"ezremote:identifier":"name","type":"ezstring"},["text","Leaves and light"]],
                            ["ezobject:attribute",{"ezremote:identifier":"short_description","type":"ezxmltext"},
                                ["#xml","<section><paragraph>The first cold <strong>morning</strong>.</paragraph></section>"]
                            ]
                        ],
                        ["ezobject:node-assignment-list",
                            ["node-assignment",{"is-main-node":"1","parent-node-remote-id":"news-node","remote-id":"autumn-2026-folder-1-node","sort-field":"path","sort-order":"1","priority":"0"}]
                        ]
                    ]
                ]
            ]
        ]
    ]
}
```

`packaging` and the element that names the Exponential version and named version are required by the upload check
(any values; copy the version element from an exported package.json, it is left out above). Attributes the package leaves out keep
their class defaults; an attribute is written by its `ezremote:identifier`. Pack the directory with
`./console ezpm convert <dir> autumn_articles-1.0-1.json.ezpkg --to=json` (which also writes the checksums).
`ezpm validate` and `ezpm check` (or the upload and the install page) say what is wrong before anything is written.
The drafts of a package with `expai.ai-generated` = `true` carry the Exponential Intelligence label ([packages.md](packages.md)).
