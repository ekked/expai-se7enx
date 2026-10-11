# The keyboard: command palette and shortcuts

## The command palette (Ctrl/Cmd+K)

Ctrl+K (Cmd+K on macOS) opens the palette:

- **in Vector** always, also from the message field;
- **on every other admin page** (owner decision) when `[KeyBindings] GlobalPalette=enabled`, but **never while the
  focus is in a field or a rich text editor**: Ctrl+K inserts a link in the Online Editor and in Tiptap, and that
  stays so.

On other pages the palette is loaded on first use (`expaivector::palette` returns its script, texts and the
editor's key bindings), so it adds nothing to pages where it is not used. A user without access to Exponential
Intelligence keeps the browser's Ctrl+K (the answer is remembered for a day).

What it lists, as you type:

| Group | Entries |
| --- | --- |
| Commands | Open Vector; New thread here (on a content page); Switch model; Keyboard shortcuts; in Vector also New thread, Run, Stop, Plan/Write, Scope, Lane, Next/Previous thread, Plan/Changes/Cost |
| Field actions | in an edit form: every field action of every field ("Shorten: Intro"), run by Enter |
| Commands (edit form) | every preset button ("Suggest title: Title") and every Improve menu ("Improve: Body text"), registered by expai_presets.js ([presets.md](presets.md)) |
| Threads | the latest threads, or those whose title matches |
| Nodes | readable nodes whose name contains the text (2 characters or more) or with that node id: Enter opens the node |
| Models | with "Switch model": the models the editor may use; in Vector it changes the thread's model, elsewhere the editor's model |

Up and down choose, Enter runs, Esc closes (from the model list Esc goes back). The palette is a modal dialog: the
focus stays inside it (Tab cycles), the list is a listbox with `aria-activedescendant`, and focus returns to where
it was when the palette closes.

## Shortcuts in Vector

| Keys (default) | Command | Where |
| --- | --- | --- |
| Ctrl/Cmd+K | the palette | everywhere (`global`) |
| Ctrl/Cmd+Enter | run, or queue while a turn runs | Vector (`vector`) |
| Alt+Enter | queue the message (sends it when nothing runs) | the message field (`composer`) |
| Esc Esc | stop the thread (twice within 600 ms) | Vector |
| j / k | next / previous part of the Changes tab while it is shown, else next / previous exchange | Vector, not in a field (`list`) |
| a / x | accept / reject the current part of a staged change, then on to the next one that waits ([review.md](review.md)) | list |
| Shift+A / Shift+X | accept / reject the whole field of the current part | list |
| e | edit the proposed text of the current part (plain text fields) | list |
| Alt+Down / Alt+Up | next / previous thread | Vector |
| n | new thread | list |
| Alt+P | plan / write | Vector |
| Alt+M, Alt+L, Alt+S | model, lane, scope | Vector |
| 1 / 2 / 3 | Plan / Changes / Cost | list |
| ? | the shortcut sheet | list |

Keys are matched on `KeyboardEvent.code` (KeyK, Enter, Escape, ArrowDown, Digit1), so they work on every keyboard
layout; `?` is matched as the character, because each layout types it differently. `Mod` means Cmd on macOS and
Ctrl elsewhere. Combinations taken by browsers (Ctrl+Shift+M, Ctrl+Shift+P, Ctrl+Shift+K) are not used.

Every command also has a visible control (buttons, menus, tabs), and every shortcut is shown next to its control.

## The shortcut sheet and your own keys

`?` (or *Keyboard shortcuts* in the palette, or the ? button in Vector) shows every command with its keys. With
`[KeyBindings] UserOverrides=enabled` each line has *Change*: press the new keys (Esc cancels); *Reset* goes back
to the site's keys. Your keys are kept in your preference `expai_keybindings` (JSON, `expaivector::keybindings`),
not in a file.

## The site's keys

`expai.ini [KeyBindings]`:

    GlobalPalette=enabled
    UserOverrides=enabled
    Binding[palette]=Mod+KeyK
    When[palette]=global
    Binding[stop]=Escape Escape
    When[stop]=vector

`Binding[<command>]` is modifiers (`Mod`, `Ctrl`, `Alt`, `Shift`, `Meta`) joined with `+` to a `KeyboardEvent.code`
or one character; two chords separated by a space must follow within 600 ms. `When[<command>]`:

| When | The binding works |
| --- | --- |
| `global` | on every admin page, never in a field or a rich text editor (in Vector also in a field) |
| `vector` | in Vector; in a field only with Mod, Ctrl, Alt or Escape |
| `list` | in Vector, not in a field |
| `composer` | in Vector's message field |

The commands are palette, run, queue, stop, next, previous, accept, reject, accept_field, reject_field, edit_hunk, thread_next, thread_previous,
new_thread, mode, model, lane, scope, tab_plan, tab_diff, tab_cost, help.

## Commands of your own

    ExpAi.commands.register( 'my_report', {
        label: 'Open my report',
        available: function () { return true; },
        run: function () { window.location.href = ExpAi.url( 'myreport/view' ); }
    } );

Registered commands appear in the palette (from a script loaded on admin pages, after expai.js).
