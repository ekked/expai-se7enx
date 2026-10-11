# expaiexample

The worked examples of [../../extending.md](../../extending.md) as one extension: a field action "Headline", a tool
`count_children`, a panel section "Tips" with its JavaScript, and a provider `expAiExampleEchoProvider` that answers
without calling any service.

Try it: copy this directory to `extension/expaiexample`, rename `classes/*.php.example` to `*.php` (they carry the
suffix here so that the autoload generator does not pick them up from inside expai), activate it after expai (for example
`ActiveAccessExtensions[]=expaiexample` below `ActiveAccessExtensions[]=expai` in an admin siteaccess), then
regenerate the autoloads and clear the caches as extending.md says. The echo provider is used only when
`[AISettings] Provider=custom` and `ProviderClass=expAiExampleEchoProvider` are set.

This directory is documentation: it is not active as part of expai.
