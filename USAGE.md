# Using Exponential Intelligence

Exponential Intelligence writes drafts for you, with the AI model your site has configured. It works with your own account
and permissions: it can only read what you may read and only create or change what you may create or change, and it
never publishes. You decide what happens with every draft.

## 1. The Intelligence tab (content full view)

Open any folder or other content in the admin and choose the tab **Intelligence**.

**Ask.** Write your request and press **Send** (or Ctrl+Enter). Examples:

- "Write three short articles about our spring events under this folder, friendly tone, no images."
- "Rewrite the intro of this article: shorter and clearer."
- "Translate this article into German as a new draft."
- "Which kinds of content may I create here?"

**Watch it work.** While it works, the panel shows each step in one line: "Read the class "Article"", "Listed the
children of node 74", "Created the draft "Planting Spring Bulbs"". Steps that write are bold; a failed step is red
and says why (for example a field the class does not have, or a place you may not create content in). When the
request is unclear, it asks you a short question instead of guessing.

**The history.** Every exchange (your message and everything that followed) is one row: time, your message and the
start of the answer, the number of lookups and the drafts it wrote as small links. Only the latest is open; click a
row (or press Enter on it) to open or close it. **Expand all** / **Collapse all** open or close every row, **Show
older** loads earlier exchanges. Opening and closing changes nothing; the browser remembers which rows you opened.
**New conversation** starts over (your drafts stay).

**The drafts.** Under the conversation, "Drafts written with Exponential Intelligence" lists what it wrote here for
you, each with its state (Draft, Saved as draft, Waiting for approval, Published) and links:

- **Edit** opens the draft in the normal edit form (with the Online Editor or Tiptap, autosave and everything else).
- **View version** shows the draft as it would look.
- **Discard** removes the draft (for a new object, the object with it), after a confirmation.

Select drafts with their checkboxes, then:

- **Save as drafts** (the default) keeps them as drafts. You find them in **My drafts** on the dashboard and on the
  drafts page.
- **Send for approval** publishes them the normal way. Before you press it, each draft says what will happen:
  "Waits for approval when sent" (an approval workflow applies: the approver decides), "May wait for approval when
  sent" (an approval workflow might apply), or "Published at once when sent (no approval applies)". The line above
  the buttons sums it up, and a confirmation repeats it.

## 2. In the edit form

When you edit content, the form has an **Exponential Intelligence** area at the top (open it with **Show**) and small
buttons next to the text fields.

**Fill the fields.** Describe what to write ("A short article about the opening of our new office, friendly tone,
about 200 words") and press **Fill the fields**. The fields it fills are highlighted. **Undo** puts the previous
values back.

**Per field.** Next to a text field:

| Button | What it does | Asks for |
| --- | --- | --- |
| Write | writes a new value from your instruction | what to write |
| Rewrite | clearer and better written, same meaning and length | optionally, how to change it |
| Shorten | about half as long, same key facts | nothing |
| Translate | into another language of the site | the language |
| Suggest | a value based on the other fields (for keywords: five to eight) | nothing |

The new value goes into the field (into the editor for rich text) with a short note and an **Undo** link.

Nothing is saved until you store or publish the draft as usual. Your site may add more actions (doc/extending.md).

**Presets and Improve.** One-click buttons with nothing to type: **Suggest title**, **Write summary**, **Meta
description** and **Keywords** sit next to the fields your site set them up for (also next to the title, description
and keywords of the page metadata). Every text field has an **Improve** menu: Shorten, Expand, Clarify, Fix grammar,
Simplify for new readers, More formal, More casual. They fill the field the same way, with **Undo**. The line
**Defaults in use** above the fields says which model a click uses, where it runs and about what one click costs.
In the command palette (Ctrl/Cmd+K) you find them as "Suggest title: Title" or "Improve: Body text" (doc/presets.md).

## 3. The label

Every version written with Exponential Intelligence carries the label **Exponential Intelligence** (the tooltip names the
model that wrote it):

- in **My drafts** on the dashboard and on the drafts page,
- in the version view and in the edit form of that version (as a note at the top),
- in the approval item the approver opens, and in the collaboration lists.

## 4. Approval

When your site has an approval workflow, "Send for approval" puts the version in the approver's collaboration inbox
("Waiting for approval"). The approver sees the label in the item and approves or denies it. Approved: it is
published when the workflow continues. Denied: it becomes your draft again (the panel shows it as Draft) and you can
edit and send it again. More: doc/approval-workflow.md.

## 5. The dashboard page

**Dashboard > Exponential Intelligence** (left menu of the Dashboard tab):

- **At the top**: choose the period (Today, 7d, 30d, Month); with the right to see the whole site's usage, also
  **Mine** or **Site**. Your choice is remembered. The badges say whether the assistant is ready, which model you use
  and whether an approval workflow runs before publishing; **Details** shows more (and lets you choose the model).
- **Four figures**: the estimated cost, the tokens used, the requests (with their average time) and the drafts
  written, each compared with the period before when there was usage then.
- **Start writing**: the location is chosen already (the site's content root, or what your administrator set), and
  Exponential Intelligence may work there and anywhere below it, within your permissions. Write what you want and
  press **Write**: the Intelligence tab of that location opens with your request filled in; press Send.
  **change** lets you pick another place first.
- **Limits**: how much of each configured limit is used and when it resets, and the API's current rate limits.
- **Waiting for my approval**: versions written with Exponential Intelligence that wait for your decision.
- **The chart**: tokens or cost per day; **Details in the log** opens the log for the same days.
- **Recent drafts**: the last versions it wrote for you, with their state and the icons View, Edit, Prompt again and
  Intelligence tab. **All in the log** lists all of them.

The dashboard itself (Dashboard > Dashboard) also has a block "Exponential Intelligence: my drafts".
**EI: Log** in the same menu lists every request with its tokens, time and estimated cost.

## 6. Everyday examples

- *A series of news items*: open the news folder, Intelligence tab: "Write four short news items about the changes to
  our opening hours, one per location: Main Street, Station, Market and Harbor." Review them, Edit what needs
  images, then select all and Send for approval.
- *Tidy an article*: open the article, Intelligence tab: "Make the intro shorter and fix the spelling in the body."
  It writes a new draft version; the published version stays until you send the draft.
- *In the form*: while editing, Shorten on a long body; Suggest on the keywords field.

## 7. Good to know

- It cannot set images, files or relations; it says so, and you set them in the edit form.
- It does not invent facts on purpose and is told to mark missing facts as placeholders like [source needed]. Check
  facts, names, numbers and quotes before publishing.
- Content it reads is treated as data, not as instructions, but review what it wrote as you would any draft.
- Long conversations: it remembers the last exchanges word for word and earlier ones as a short summary.

## 8. Vector: bigger jobs, reviewed before they are written

**Dashboard > EI: Vector** (when your role has it) is a workspace of threads. A thread says what it may touch (a node, a
subtree, a class, languages), whether it plans first or writes, which model and lane it uses, and whether its changes
are **staged**: then every change waits in the **Changes** pane, where you accept, reject or edit it per field and
part before anything is written into a draft. Bulk work starts with a **plan** and a cost estimate that you approve
first. Ctrl/Cmd+K opens the command palette; ? lists the shortcuts. More: doc/vector.md, doc/review.md,
doc/plan-mode.md.

## 9. Alt text for images

In the edit form every image field has **Write alt text** (a model that can look at images suggests it, in the
content's language) and **Decorative (empty alt text)**. The report **Design > Missing alt text** (left menu of the Design tab) lists the images
without alt text under a location and can queue suggestions, which arrive as drafts for you to review. More:
doc/alt-text.md.

## 10. Chains and translations

- **Write with a chain** in the Intelligence tab (when your role has it): one model writes, another reviews, the
  text is revised until it passes, and you decide at the checkpoint before anything else happens. The runs are under
  **Dashboard > EI: Chains** (doc/chains.md).
- **Translations** in the Intelligence tab: after the source language changed, each translation is shown as up to
  date, a small patch or a full retranslation, with its estimated cost; you approve, translate in full or skip.
  The results are drafts of the translations (doc/translation-sync.md).

## 11. Help

**Dashboard > EI: Help**, or the **?** next to the title of each Exponential Intelligence page,
opens this documentation inside the administration interface.
