# Who may use it, and why don't I see it?

What you see of Exponential Intelligence depends on the policies of your role, and on whether your site has an AI
provider set up.

## Who can do this

Administrators give the policies in **Users** > **Roles and policies**. The main ones:

| Policy | Gives |
| --- | --- |
| `expai/write` | the Intelligence tab, the edit form controls, the dashboard page, your own log, the "Missing alt text" report |
| `expai/vector` | Vector (with `expai/write`), limited by subtree, section, kinds of scope and review |
| `expai/chain` | AI chains |
| `expai/translation_sync` | the Translations section of the Intelligence tab |
| `expai/model_use` | choosing models of providers that need it |
| `expai/log` | everyone's requests, the whole site's usage and limits |
| `expai/summary` | the printable "EI: Summary" page, for example for a data protection officer |

The whole list is in [Permissions](../permissions.md).

## Steps

1. Open any content in **Content structure** and look for the **Intelligence** tab.
2. If it is missing, open **Dashboard** and look for **Exponential Intelligence** in the left menu.
3. If both are missing, ask your administrator for `expai/write` and say what you need it for.
4. If you see the tab but it says the assistant is not ready, the site has no AI provider or model set up. Tell your
   administrator.

## What can go wrong

- **"You do not have access to Exponential Intelligence."** Your role lacks `expai/write`.
- **The assistant answers but cannot create content in a folder.** It has your rights: you may not create that class
  there either.
- **Vector is missing although the tab works.** Vector needs `expai/vector` as well.

## Related pages

- [Permissions](../permissions.md)
- [Troubleshooting: the Intelligence tab is missing](../troubleshooting.md#the-intelligence-tab-is-missing)
- [User manual](README.md)
