# How do I update translations after the source changed?

When the text in the main language changes, the **Translations** section of the Intelligence tab shows, for each
translation, whether it is up to date or needs a small patch or a full retranslation, with the estimated cost. You
decide; the results are drafts.

## Who can do this

Users whose role has `expai/write` and `expai/translation_sync`. **Approve** and **Skip** also need `content/edit` in
that language.

## Steps

1. Open the content in **Content structure** and choose the **Intelligence** tab.
2. Open the **Translations** section and click **Check translations**.
   Each language is shown as up to date, a small patch, or a full retranslation, with the estimated cost.
3. For each language, click **Approve** (apply the proposed patch), **Translate in full**, or **Skip**.
4. Open the drafts of the translations, read them, and send them for publishing.

## What can go wrong

- **Every language shows as a full retranslation.** The source changed a lot. That is expected after a rewrite.
- **The section is missing.** Your role lacks `expai/translation_sync`, or the content has only one language.

## Related pages

- [Translation sync](../translation-sync.md)
- [User manual](README.md)
