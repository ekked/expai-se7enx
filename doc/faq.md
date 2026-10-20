# FAQ

**Can it publish by itself?** No. It writes drafts. Publishing happens only when an editor presses "Send for
approval", through the normal publish operation (with approval where configured). Packages installed as drafts, chains, alt text
reports and Vector's staged changes end in drafts too.

**Whose permissions does it use?** The logged-in editor's, for every lookup and every write. There is no service
account. Queued work runs as the user who queued it.

**Which model?** The site default (`claude-sonnet-5-5` unless you change it) and the models the administrator allows.
Models of other providers come from the provider registry: Anthropic, any OpenAI-compatible service (OpenRouter,
the Hugging Face router, Mistral, a local Ollama ...) or a class of your own ([providers.md](providers.md)). What has
been run with which provider is in [provider-support.md](provider-support.md).

**Can it run without sending anything off the server?** Yes, with a local open-weight model
([local-models.md](local-models.md)) and `Region=local`; the data policy can keep sections or classes on local
models only ([data-policy.md](data-policy.md)). On a CPU host a local model takes minutes per step.

**Does the browser see an API key?** No. Keys live in `settings/override` and only the server calls the providers.

**Can it set images?** It cannot upload images, files or relations; it says so, and the editor sets them in the
edit form. With a vision model it can suggest the alt text of an image that is there ([alt-text.md](alt-text.md)).

**Does it work in the edit form with Tiptap or the Online Editor?** Yes; values go into the editor, and autosave
picks them up like typed text.

**Is anything stored when I use a field action?** Only the value in the form, until you store or publish the draft.
The version is marked in expai's log (for the label).

**How long is a conversation kept?** Until "New conversation", or a new one starts after
`[AssistantSettings] ConversationLifetime` (7 days) without a message. The history shows the last 200 exchanges.
Thread events, proposals and checkpoints are removed after `[ThreadSettings] *KeepDays` (30 days).

**Why "Intelligence" on the tab and "Exponential Intelligence" elsewhere?** The tab label is kept short; the panel, the
label and the dashboard use the full name.

**Does it read the whole site?** Only what the model asks for through the tools (a node, its children, its
surroundings, class definitions, and with the right policies search, the web or the project history), as the editor,
with limits on list length and field length, and only what the data policy lets go to that model's provider.

**Does it cost anything?** Each request to a hosted provider is billed by that provider to the account of the key; a
local model costs no fees. expai estimates the cost of every request from a price table, shows it on the dashboard
and in the log, and stops requests at the limits you set ([usage-tracking.md](usage-tracking.md)); batch lanes cost
less for bulk work ([lanes.md](lanes.md)). Prompt caching and the bounded context window keep repeated context cheap.

**Can I switch it off for one siteaccess?** Yes: `[AISettings] DisabledSiteAccesses[]`, `Enabled=false` in that
siteaccess's expai.ini.append.php, or do not activate the extension there ([switch-and-veto.md](switch-and-veto.md)).

**Where is the help?** In the help center of the administration interface (Dashboard > EI: Help, or Help in the top
bar > Extensions > expai, and the "?" link on each expai page), and in the `doc/` directory of the extension.
