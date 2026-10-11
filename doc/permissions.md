# Permissions

## Policies of expai

| Policy | Allows |
| --- | --- |
| `expai/write` | The Intelligence tab, the edit form controls, every ezjscore function except `labels`, the dashboard page (own usage), the dashboard block, the left menu entries, `expai/log` (own requests and versions) |
| `expai/log` | everyone's requests and versions on `expai/log` (filter by user with `/(user)/<id>`), the "Site" view of the dashboard with the top users, the site limits |
| `expai/settings` | for administrators: the dashboard's Details show where the site default model is set (settings file and placement) |

None has limitations: what the assistant may do in the content tree is decided by the editor's content policies
(below). The tab, the menu entry and the dashboard block are not shown at all without `expai/write`.

## ezjscore

The browser reaches expai through `ezjscore/call`, and ezjscore.ini says `[ezjscServer_expai] Functions[]=expai`, so
the user needs the policy `ezjscore/call` without limitation or with the limitation "function list" containing
`expai`. Many installations give `ezjscore/call` to all logged-in users through the Member role.

## Everything runs as the editor

- **Reading**: every lookup goes through the kernel's checks for the current user: `canRead()` on nodes and objects,
  `subTree()` with the user's limitations for children and siblings, `canCreateClassList()` for creatable classes.
- **Creating**: `create_draft` checks `content/create` on the parent node for that class, the parent's class and the
  language (`checkAccess( 'create', class, parent class, false, language )`), with every limitation the role has
  (section, subtree, class, parent class, language, owner ...). The object is created in the parent's section, owned
  by the editor, as version 1 with status draft.
- **Changing**: `update_draft` checks `content/edit` (in that language) on the object and writes a new version
  owned by the editor, or the draft it wrote before for the editor in this conversation.
- **Edit form**: field actions and "Fill the fields" work only on a draft the editor owns and may edit; they return
  values and store nothing.
- **Keep / Send / Discard**: only for versions in expai's log written for this editor, still the editor's own draft,
  and only if the editor may edit it (`editAccess()`, or for a never published object the right to create it
  there, as the kernel's discard does). "Send" runs the publish operation as the editor; the workflow events see the
  editor as the user.
- **Labels**: shown for versions of objects the user may read, for the editor's own versions, and in approval items
  the user takes part in.

expai never uses another account, never switches the current user and has no setting to publish on its own.

## Setting up roles

Give `expai/write` to your editors' role (Setup > Roles and policies > role > New policy > module `expai`, function
`write`). Administrator has every policy already. Anonymous users and members without the policy see nothing of
expai.
