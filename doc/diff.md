# The diff engine: segmenters, hunks, metrics

`classes/diff/` compares two versions or two languages of an object, attribute by attribute, at block and sentence
level. The translation sync ([translation-sync.md](translation-sync.md)) uses it to find what changed in the source
language; Vector's review of staged changes (accept, reject or edit per hunk and per field, [vector.md](vector.md))
uses the same hunks and `expAiDiff::apply()`.

## Segmenters

A segmenter cuts a stored value into **blocks** and each block into **sentences**, and puts values together again.
It implements `expAiSegmenter` and is registered per datatype in `expai.ini [DiffSettings] Segmenters[<datatype>]`
(`expAiSegmenterRegistry`); datatypes without one use `FallbackSegmenter`.

| Datatype | Class | Blocks |
| --- | --- | --- |
| `ezstring`, `ezemail`, `ezisbn` | `expAiSegmenterString` | the value is one block, split into sentences |
| `eztext` | `expAiSegmenterText` | paragraphs (separated by blank lines), split into sentences; line breaks inside a paragraph stay |
| `ezxmltext` (and HTML, `ezrichtext`) | `expAiSegmenterRichText` | paragraphs, headings, list items, table cells (and the HTML `p`, `h1`-`h6`, `li`, `td`, `th`, `dt`, `dd`, `caption`); a paragraph that only wraps a list or a table is not a block, its items and cells are; blocks inside a block-level custom tag are of type `custom` |
| `ezkeyword` | `expAiSegmenterKeyword` | a set: every keyword is a block, sorted, without duplicates |
| anything else | `expAiSegmenterGeneric` | the value (`toString()`) as one block, not split |

A block is an array:

| Key | Meaning |
| --- | --- |
| `index` | position in the value |
| `type` | `paragraph`, `heading`, `list_item`, `table_cell`, `custom`, `value`, `keyword` |
| `text` | readable text; atoms as `[[embed object 12]]` |
| `coded` | the text with inline markup as placeholders (rich text), else the text |
| `hash` | identity of type, normalised text and markup shape: equal hashes are equal blocks |
| `text_hash` | identity of the normalised text only: a different `hash` with the same `text_hash` is a change of markup only |
| `chars` | characters of the normalised text |
| `sentences` | list of `{text, hash, chars}` |
| `atoms` | embeds, images, anchors the block contains |
| `mergeable` | false for table cells: they cannot be inserted or removed alone |

### Rich text: markup as placeholders

Inline markup stays inside its block. In `coded` it becomes numbered placeholders:

```
Visit <1>our shop</1> on <2>Main Street</2>. <3/>
```

`<1>…</1>` wraps the text of an element (a link with its `url_id`, `strong`, `emphasize`, `line`, a custom inline
tag); `<3/>` is an **atom** (`embed`, `embed-inline`, `anchor`, `img`, `br`). `decode()` builds the nodes back from a
coded text with the elements of the original block, so a translation keeps every link target, class and custom
attribute. It refuses a coded text with an unknown or repeated number, wrong nesting, or a missing atom (an embed is
never dropped). `<` and `&` in text are written `&lt;` and `&amp;`.

### Sentences

`expAiSentences::split( $text, $language )` is deterministic (hunk ids must not depend on the ICU version of a
server). A boundary is `.`, `!`, `?` or `…`, optional closing quotes or brackets, white space, then a capital letter, a
digit or an opening quote. A full stop does not end a sentence after an abbreviation of the language (built in for
English and German, more in `[DiffSettings] Abbreviations[<family>]`, also written with inner spaces: `z. B.`,
`d. h.`, `e.g.`), after a single letter (initials), after a number in German (ordinals: `am 3. Oktober`), or after a
number followed by a lower-case word. Text is normalised before hashing: Unicode NFC, white space collapsed,
typographic quotes, dashes and non-breaking spaces replaced.

## Alignment and hunks

1. The common prefix and suffix of block hashes, then the longest common subsequence: **equal** blocks.
2. Blocks left over between two equal blocks are paired in order when their word similarity (Jaccard) reaches
   `[DiffSettings] ModifiedSimilarity` (default 0.5): **modify**. The others are **delete** and **insert**.
3. A deleted block whose hash is inserted elsewhere is marked **moved** (no changed characters).
4. Inside a modified block the sentences are aligned the same way.

A **hunk** is a run of changed blocks between two equal blocks. Its id (`h` + 12 hex digits) is derived from the
attribute, the hash of the equal block before it and the hashes it removes and adds, so computing the diff again
gives the same ids, and a change elsewhere in the text does not change them. Two identical hunks in one attribute
get `-2`, `-3`.

| Hunk key | Meaning |
| --- | --- |
| `id`, `attribute` | as above |
| `op` | `insert`, `delete` or `replace` |
| `base`, `prop` | block indexes on each side |
| `changes` | per block: `op` (`modify`, `insert`, `delete`), `moved`, `structural` (markup only), `sentences` (op, base text, proposed text, changed characters) |
| `base_text`, `prop_text` | the readable text of each side (one line per block) |
| `context_before`, `context_after` | the text of the equal blocks around it |
| `mergeable` | whether `apply()` can take it alone |
| `chars` | changed characters |

### Metrics

Per attribute (`metrics` of each attribute) and per object (`expAiDiffResult::metrics()`, characters summed over the
attributes):

| Metric | Meaning |
| --- | --- |
| `change_ratio` | changed characters / characters of the base (1 when the base is empty and the proposal is not) |
| `changed_chars` | inserted and deleted blocks count fully; a modified block counts the longer side of each changed sentence; moved blocks and markup-only changes count 0 |
| `blocks`, `blocks_changed`, `block_ratio` | blocks of the base, changed blocks (not moved), their share |
| `sentences`, `sentences_changed`, `sentence_ratio` | the same for sentences |
| `modified`, `inserted`, `deleted`, `moved`, `structural` | blocks by kind (per attribute) |
| `hunks`, `attributes_changed` | counts |
| `structural_only` | hunks, but no changed characters (links, markup) |

## The API

```php
// two versions of one language
$diff = expAiDiff::compare( $object, $object, array( 'version_a' => 5, 'version_b' => 7, 'language' => 'eng-GB' ) );

// two languages of one version
$diff = expAiDiff::compare( $object, $object, array( 'language_a' => 'eng-GB', 'language_b' => 'ger-DE' ) );

// a published value against a proposal that is not stored (Vector's staged change)
$diff = expAiDiff::compare( $object, array( 'values' => array( 'intro' => array( 'datatype' => 'ezxmltext', 'value' => $proposedXml ) ),
                                            'language' => 'eng-GB' ), array( 'attributes' => array( 'intro' ) ) );

foreach ( $diff->hunks() as $hunk ) { ... }
$diff->metrics();            // the object
$diff->toArray();            // for storage or the browser (toArray( true ) adds values and blocks)

// the base with the accepted hunks: values per attribute
$merged = expAiDiff::apply( $diff, array( 'h1a2b3c4d5e6f', 'h0f9e8d7c6b5a' ) );
$merged['values']['intro'];  $merged['applied'];  $merged['unknown'];

// ... written as a draft of the current user (expAiContentWriter, logged in expai_draft_log)
$result = expAiDiff::apply( $object, $ids, array( 'diff' => $diff, 'write' => true, 'source' => 'vector', 'thread_id' => 12 ) );
$result['version'];
```

Each side of `compare()` is an object (with the options `version_a`/`version_b`, `language_a`/`language_b` or
`language`), `array( 'object' => ..., 'version' => ..., 'language' => ... )`, or
`array( 'values' => array( identifier => array( 'datatype' => ..., 'value' => ... ) ), 'language' => ... )`. Options:
`attributes` (only these), `translatable_only`, `datatypes`, `language` (of the sentence splitter), `similarity`.
Values are in storage form: ezxmltext XML, other datatypes as `toString()` gives them.

`apply()` merges at **block** level: a modified block gets the inline content and attributes of the proposed block, an
inserted block is placed after the block before it (into the same list where both are list items; otherwise its
list or table is created with only that item), a deleted block is removed with the containers it leaves empty. An
accepted hunk that inserts or removes a table cell alone is refused (`expAiException`); accept the hunks of the whole
table instead. Hunks that are not accepted keep the base.

`compose( $value, $texts )` of a segmenter builds a value with the structure of `$value` and the coded text of some
blocks replaced; each text names the block whose placeholders it uses (`from`). The translation sync builds a
translation from the new source structure this way: unchanged blocks take the existing translation, changed blocks the
model's answer.

## A segmenter of your own

```php
class mySiteMatrixSegmenter extends expAiSegmenterBase
{
    protected function pieces( $value ) { return array_filter( explode( "\n", (string) $value ) ); }   // one row a block
    protected function join( array $pieces ) { return implode( "\n", $pieces ); }
    protected function type() { return 'value'; }
}
```

```ini
# settings/override/expai.ini.append.php
[DiffSettings]
Segmenters[ezmatrix]=mySiteMatrixSegmenter
```

Implement `expAiSegmenter` directly for structured values (as `expAiSegmenterRichText` does) when `merge()` and
`compose()` cannot work on a list of strings.

## Tests

`php bin/php/ezexec.php extension/expai/tests/expaidiff_test.php` checks the sentence splitter (English and German
abbreviations, initials, ordinals, quotes), every segmenter, rich text with headings, links, lists, tables and embeds,
the metrics, stable hunk ids, merge with accepted and rejected hunks (list items, a new list), compose with placeholders
(and the refusal of a dropped embed or wrong nesting) and the decision matrix of the translation sync. It needs no
database writes and no network.
