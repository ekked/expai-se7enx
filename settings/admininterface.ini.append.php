<?php /* #?ini charset="utf-8"?

# The "Exponential Intelligence" tab of the content full view, for nodes in the content and the media
# navigation parts. The header template draws the tab only for users with the policy expai/write.
[WindowControlsSettings]
AdditionalTabs[]=expai
AdditionalTabs[]=expai_media

[AdditionalTab_expai]
NavigationPartName=ezcontentnavigationpart
Title=Intelligence
Description=Write and revise content with Exponential Intelligence
HeaderTemplate=expai_header.tpl
Template=expai.tpl

[AdditionalTab_expai_media]
NavigationPartName=ezmedianavigationpart
Title=Intelligence
Description=Write and revise content with Exponential Intelligence
HeaderTemplate=expai_header.tpl
Template=expai.tpl

*/ ?>
