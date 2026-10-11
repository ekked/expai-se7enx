# FAQ

**Can it publish by itself?** No. It writes drafts. Publishing happens only when an editor presses "Send for
approval", through the normal publish operation (with approval where configured).

**Whose permissions does it use?** The logged-in editor's, for every lookup and every write. There is no service
account.

**Which model?** `claude-sonnet-5-5` by default; any Anthropic model id in `[AISettings] Model`. Other backends through
a provider class (extending.md).

**Does the browser see the API key?** No. Only the server calls the API.

**Can it set images?** No, nor files or relations. It says so; the editor sets them in the edit form.

**Does it work in the edit form with Tiptap or the Online Editor?** Yes; values go into the editor, and autosave
picks them up like typed text.

**Is anything stored when I use a field action?** Only the value in the form, until you store or publish the draft.
The version is marked in expai's log (for the label).

**How long is a conversation kept?** Until "New conversation", or a new one starts after
`[AssistantSettings] ConversationLifetime` (7 days) without a message. The history shows the last 200 exchanges.

**Why "Intelligence" on the tab and "Exponential Intelligence" elsewhere?** The tab label is kept short; the panel, the
label and the dashboard use the full name.

**Does it read the whole site?** Only what the model asks for through the tools (a node, its children, its
surroundings, class definitions), as the editor, with limits on list length and field length.

**Does it cost anything?** Each model call is billed by Anthropic to the account of the key. Prompt caching
(`PromptCaching=enabled`) and the bounded context window (`ContextWindowTurns`) keep repeated context cheap.

**Can I switch it off for one siteaccess?** Don't activate it there, or set `[AISettings] Enabled=false` in that
siteaccess's expai.ini.append.php.
