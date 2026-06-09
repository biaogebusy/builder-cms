# AI Content Suggestions
## What is the AI Content Suggestions module?
This module assists content editors to obtain feedback from a configured LLM
about the node they are editing.

## Dependencies
The AI Content Editing Tools requires the AI Core module to be installed and
configured, and a valid AI Provider module to be enabled and configured.

## How to configure the AI Content Editing Tools module
1. Visit /admin/config/ai/ai_content
2. Enable the items you wish to receive feedback on: by default all are disabled
   and the module will do nothing.
3. Select the specific model you wish to use from your Provider(s) for each
   option, or allow the LLM settings to determine the default.

## Using the module
When the module is installed and configured, when you add or edit a new node,
the edit form will have additional dropdowns to allow you to request LLM review
in your configured categories. Open the dropdown and select the field from the
node you wish to review and press the provided button. There will be a delay as
your field content is sent to the LLM and analysed, and the LLM response will
display within the drop down.

The original node content will be unchanged: to use any suggested text provided
by the LLM, you will need to copy and paste it into the required field.

## Known issues
1. Whilst the editing tools appear on the node add and edit forms, they require
   the field being reviewed to have been completed and the form submitted before
   they can do anything. To use the tools on new content, you will need to
   create a draft version, save it unpublished and then edit it.
2. The module currently only provides tools when editing nodes. The module will
   not currently work on any other fieldable Content Entity.
