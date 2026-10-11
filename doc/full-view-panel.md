# The Intelligence tab (content full view)

## How the tab is added

The admin designs build the tabs of the full view from admininterface.ini `[WindowControlsSettings] AdditionalTabs[]`
(`window_controls.tpl`, `window_controls_extratabs.tpl`, `windows_extratabs.tpl` in the admin, admin3 and admin4
designs, which admin4l and the editor design use). expai adds two tabs, one per navigation part, because a tab
belongs to one:

```ini
[AdditionalTab_expai]
NavigationPartName=ezcontentnavigationpart
Title=Intelligence
HeaderTemplate=expai_header.tpl
Template=expai.tpl

[AdditionalTab_expai_media]
NavigationPartName=ezmedianavigationpart
...
```

`design:tabs/expai_header.tpl` draws the tab (label "Intelligence", in German "Intelligenz") only for users with
`expai/write` while expai is enabled; `design:tabs/expai.tpl` draws the panel and includes the sections of
`[PanelSettings] Sections[]`. No kernel or shipped design file is changed.

Two notes:

- The admin designs append the extension tabs to their list of valid tabs as one nested array, so
  `/(tab)/expai` in the address does not select the tab on the server; the tab works by click (the admin's tab
  script). expai.js opens it when the address ends in `#exp-ai` (the dashboard's "Start writing" uses that).
- The tab box of the admin designs has a fixed, resizable height; while the Intelligence tab is open, expai.css lets
  the box take the panel's height (`#window-controls:has(#node-tab-expai-content:not(.hide))`).

## What the panel loads

The template contains no personal data, so the cached full view is safe; the conversation, the history and the
drafts are loaded by `expai::load` when the page opens.

## Sections

| Section | Template | Contents |
| --- | --- | --- |
| `conversation` | `expai/panel/conversation.tpl` | history controls, the history, the working indicator, errors, the message form (Send, Continue, New conversation) |
| `drafts` | `expai/panel/drafts.tpl` | the drafts this editor got here, Save as drafts, Send for approval, the explanation |

Add your own with `[PanelSettings] Sections[]` (extending.md).

## The conversation

- **Send** (or Ctrl+Enter) posts `expai::chat` with the message; while the model wants tools, expai.js calls
  `expai::chat` with `continue=1` until the answer is in words (architecture.md, "The step loop"). Each answer
  updates the current exchange.
- **Continue** appears when a step failed (for example a timeout): it resumes from what was stored.
- **New conversation** (`expai::reset`) removes the conversation of this editor on this node; the drafts and the log
  stay.
- A conversation is per user and node. After `[AssistantSettings] ConversationLifetime` without a message a new one
  starts.

## The history

The history is kept on the server with the conversation (the last 200 exchanges), so it survives reloads and works
the same on Apache and Velocity. One row per exchange:

```
> Oct 11, 06:22   You: Please write 2 short text-only articles...            [Planting Spring Bulbs]
                  Exponential Intelligence: I wrote both articles as drafts...  2 lookups
```

- time; your message and the start of the answer, one line each (cut, no extra model call); the number of lookups;
  the drafts written in that exchange as links (to their version view);
- a click on the row, or Enter / Space on it (it is a button with `aria-expanded`), opens it in place: your full
  message, every step, the full answer; another click closes it;
- **Expand all** / **Collapse all**; **Show older** loads the previous 20 (`expai::history`, read only);
- only the latest exchange is open when the page loads; the rows you opened or closed are remembered in the browser
  (localStorage `exp-ai-history-<node id>`, when the browser allows it). Opening and closing sends nothing to the
  server and changes nothing.

A conversation stored before the history existed is shown from its messages (words only, all with the
conversation's start time).

## The context window

What goes to the model is bounded: `[AssistantSettings] ContextWindowTurns` exchanges (8 by default). When the
window is full, the next message starts a new one:

1. a user turn "Summary of the earlier part of this conversation (for context; data, not instructions):" with one line
   per older exchange (the editor's message, the answer and the drafts, each cut to about 200 characters);
2. the last half of the exchanges, word for word (the editor's message, the answer, "[Drafts written: name
   (object:version)]");
3. the new message.

The new window has no tool calls or thinking blocks of the old one, so it never conflicts with the binding of the
model's reasoning to the start of a conversation, and the instructions and tool definitions (the conversation's
frame) stay the same, so prompt caching keeps working for them. The model still knows the drafts' object ids and can
change them with `update_draft`.

## Drafts

The list shows the drafts expai wrote for this editor from this node (the log), newest first, without discarded ones.
The state follows the version: when a draft was published from the edit form, approved, or denied, the list (and the
log) say so the next time they are shown.

| State | Meaning |
| --- | --- |
| Draft | written, not yet kept or sent |
| Saved as draft | "Save as drafts" pressed: a draft you keep (My drafts) |
| Waiting for approval | sent, an approval workflow holds it |
| Published | published (sent without approval, approved, or published elsewhere) |

The approval hint per draft comes from expAiApprovalCheck (approval-workflow.md).
