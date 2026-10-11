<?php /* #?ini charset="utf-8"?

# expaiexample: the worked examples of doc/extending.md, all four kinds of extension point at once.

# 1. a field action, by configuration only
[EditViewSettings]
FieldActions[]=headline

[FieldAction_headline]
Label=Headline
Handler=expAiPromptFieldAction
Datatypes[]
Datatypes[]=ezstring
Input=none
Instruction=Write a short, catchy headline (at most eight words) for this content, based on its other fields.

# 2. a tool the assistant can call
[ToolSettings]
Tools[]=count_children

[Tool_count_children]
Class=expAiExampleToolCountChildren

# 3. a panel section
[PanelSettings]
Sections[]=tips

[PanelSection_tips]
Template=expai/panel/tips.tpl

*/ ?>
