# Unreleased

## Provider-neutral

expai works with any provider (Anthropic, every OpenAI-compatible service, local models); it no longer presents itself
as one provider's product.

- The line "Powered by Claude from Anthropic" is gone from the Intelligence tab, the dashboard, the log and the edit
  form, and from the tooltips and the version label ("This version was written with Exponential Intelligence."). The
  translations follow; the messages that only carried the line are removed.
- The extension's name in `ezinfo.php`, `extension.xml` and `composer.json` is "Exponential Intelligence".
- The system prompt names the model that answers and its provider ("powered by the model ... via ..."), also for the
  Anthropic models; when the model is not known it names none, instead of claiming Claude.
- Draft log entries, chain drafts, Vector drafts and the dashboard record the provider of the model actually used
  (`expAiProviderRegistry::providerOf()`), never a hard-coded `anthropic`. The data policy of a queued Intelligence
  tab turn is checked for the model's own provider; an unknown model meets no region requirement.
- The dashboard shows the Anthropic-specific notes ("Anthropic's invoice is authoritative", the spend limit in the
  Anthropic Console) only where an Anthropic provider is set up; otherwise the price note names "the provider".
- Unchanged on purpose: the Anthropic provider itself, the pricing table, the 0.0.1.0 settings (an empty
  `Providers[]` still builds the provider `anthropic` from `[AISettings]`), and the column defaults of the schema.
