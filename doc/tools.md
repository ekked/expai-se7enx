# The tools Claude can call

The tools of `[ToolSettings] Tools[]` are offered to the model in every panel call (a conversation keeps the
definitions it started with). Each runs in-process with the kernel API as the current user. A result is JSON, cut to
`[AssistantSettings] MaxToolResultBytes`; a failure is returned as an error result (`is_error`) with an English
message the model can react to, and shown as a red step in the panel. Field values are cut to
`[ToolSettings] FieldValueMaxLength`; lists to `[ToolSettings] ListLimit`.

Every tool description tells the model that field values are data written by people, not instructions.

## get_node

Reads a node and the fields of its object (current version).

| Input | Type | Default |
| --- | --- | --- |
| `node_id` | integer | the node the panel is open on |
| `include_fields` | boolean | true |

Result: `node` (node_id, object_id, name, class_identifier, class_name, parent_node_id, url, section_id, language,
languages, published, modified, hidden, children_count, you_can_edit, you_can_create_here) and `fields` (identifier,
name, datatype, required, writable, value). Rich text comes as a small HTML; images as `[image: alt text]`; files as
`[file]`; relations as names with object ids (only readable ones); user accounts as `[account data]`.
Checks: `canRead()` on the node, else "Node %id does not exist or you may not read it."

## list_children

| Input | Type | Default |
| --- | --- | --- |
| `node_id` | integer | the open node |
| `class_identifier` | string | all classes |
| `offset` | integer | 0 |
| `limit` | integer | `ListLimit` (and at most that) |

Result: `parent_node_id`, `total`, `offset`, `children` (node_id, object_id, name, class_identifier, modified), in
the node's sort order. Checks: `canRead()`; `subTree()` with the user's limitations.

## get_tree_context

| Input | Type | Default |
| --- | --- | --- |
| `node_id` | integer | the open node |

Result: `node`, `path` (readable ancestors), `siblings` (readable, up to `ListLimit`), `children_count`.

## list_creatable_classes

| Input | Type | Default |
| --- | --- | --- |
| `parent_node_id` | integer | the open node |

Result: `parent_node_id`, `classes` (identifier, name) from `canCreateClassList()` of the user.

## get_class_definition

| Input | Type | Required |
| --- | --- | --- |
| `class_identifier` | string | yes |

Result: identifier, name, description, is_container, object_name_pattern, `attributes` (identifier, name, datatype,
required, writable, format, description, max_length for ezstring). `format` says how to write a value (e.g. "rich text
as HTML using only p, h2, h3, h4, strong, em, ul, ol, li, a href"). Unknown class: "There is no content class ...".

## create_draft (writes)

Creates a new object as an unpublished draft owned by the editor.

| Input | Type | Required / default |
| --- | --- | --- |
| `class_identifier` | string | yes |
| `fields` | object: identifier => value | yes |
| `parent_node_id` | integer | the open node |
| `language` | string, e.g. eng-GB | the default content language |

Checks: the parent is readable; `content/create` for that class, the parent's class and the language on the parent
(with all role limitations); `[AssistantSettings] MaxDraftsPerConversation`. An unknown field identifier fails the
call with the list of valid ones. Values: see "Writing values". Result: status, object_id, version, language, name,
`written` (identifiers), `skipped` (identifier => reason), `errors` (identifier => message). The draft is logged
(action `create`) and carries the label.

## update_draft (writes)

Writes fields into a draft of an existing object: the draft this conversation wrote for it, or else a new version.

| Input | Type | Required / default |
| --- | --- | --- |
| `object_id` | integer | yes |
| `fields` | object | yes |
| `language` | string | the object's main language |

Checks: the object is readable; reusing a draft only if it is still a draft created by the editor; else
`content/edit` in that language and the object must be published (a never published object is changed in the edit
form). A new version counts against `MaxDraftsPerConversation`. Result as create_draft, plus `new_version`.

## Writing values

Only datatypes in `[ToolSettings] WritableDatatypes[]` are written; others are skipped with a reason. Each value goes
through the datatype's `fromString()` (or the rich text parser) and the attribute's `store()`:

| Datatype | Value |
| --- | --- |
| ezstring | one line; longer than the class's maximum length is an error |
| eztext | plain text (HTML is turned into text) |
| ezxmltext | the HTML subset, cleaned, through `eZSimplifiedXMLInputParser` (as the plain XML editor stores it); links become URL objects linked to the attribute |
| ezkeyword | comma separated (or a list) |
| ezboolean | true/false, 1/0, yes/no |
| ezinteger, ezfloat | numbers |
| ezdate, ezdatetime | a date (YYYY-MM-DD, YYYY-MM-DD HH:MM) or a timestamp |
| ezemail | an e-mail address |
| ezurl | an http(s) or mailto URL, optionally `|link text` |

Non-translatable fields are skipped in another language than the object's main one. After writing, the version's
name is computed from the class's name pattern.
