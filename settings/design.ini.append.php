<?php /* #?ini charset="utf-8"?

# Templates in design/admin/templates/ (tabs/expai.tpl, expai/..., content/edit/expai.tpl).
[ExtensionSettings]
DesignExtensions[]=expai

# expai.js labels the versions the assistant wrote in draft lists, version views, edit forms and approval
# items; the panel and the edit form load their own scripts with ezscript_require().
[JavaScriptSettings]
# the structure detector of the edit form (ExpAiStructure), before expai.js, which uses it
BackendJavaScriptList[]=expai_structure.js
BackendJavaScriptList[]=expai.js
# #382 "What changed" of a draft written directly, with revert per part (after expai.js, which it extends)
BackendJavaScriptList[]=expai_whatchanged.js

[StylesheetSettings]
BackendCSSFileList[]=expai.css
BackendCSSFileList[]=expai_whatchanged.css

*/ ?>
