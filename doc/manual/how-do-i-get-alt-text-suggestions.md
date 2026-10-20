# How do I get alt text suggestions for images?

A model that can look at images suggests the alternative text of an image, in the language of the content. For one
image you use the button in the edit form; for many, the "Missing alt text" report queues suggestions as drafts.

## Who can do this

Users whose role has `expai/write`. The site needs a model that can look at images.

## Steps

1. **One image:** edit the content and store the draft once, so the image is stored in it.
2. Above the image field's "Alternative image text", click **Write alt text**.
   The suggestion goes into the field. The note under the buttons names the model and what the request cost.
3. Read it and correct it. It should say what the reader needs from the image, not "Image of ...".
4. For an image that carries no information (a pattern, a divider), click **Decorative (empty alt text)** instead.
5. Click **Store draft** or **Send for publishing**.
6. **Many images:** open **Design** > **Missing alt text**, choose the place, and queue suggestions. They arrive as drafts
   for you to review.

## What can go wrong

- **The button is disabled.** The image is not stored in the draft yet (click **Store draft** first), or no model that
  can look at images is available for this content. The button says which.
- **Decorative is disabled.** The field requires an alternative text, so it cannot be left empty.
- **The model thinks an image is decorative.** It still writes a description and says so; you decide.

## Related pages

- [Alt text for images](../alt-text.md)
- [Data policy](../data-policy.md)
- [User manual](README.md)
