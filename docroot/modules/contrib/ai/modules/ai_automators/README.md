# AI Automators
## What is the AI Automators module?
This module offers the possibility for field content to be generated, amended or
evaluated by AI or other tools. The module provides a variety of plugins for
different field types, which can be triggered individually or in sequence
depending on your specific needs.

## Dependencies
1. The AI Core module needs to be installed and configured with a working AI
   Provider.
2. The [Token module](https://www.drupal.org/project/token) is required to
   assist with using tokens in prompt-based AI Automators.
3. The Field UI module needs to be enabled to add or alter AI Automators. Once
   you have your desired AI Automators in place, it can be uninstalled again so
   it will not be automatically enabled when this module is enabled.

### Additional modules
Some of the other sub-modules provided by the AI Core module interact with the
AI Automators, and you may wish to use them depending on how complex your use
case is.

1. The AI CKEditor Integration module can be optionally enabled to allow content
   creators choose to run selected AI Automators against WYSIWYG fields. See the
   [Advanced section](#advanced-usage) for more details.
2. The AI ECA module integrates AI Core with the [ECA](https://www.drupal.org/project/eca) module
   and can be used to trigger AI Automators within ECA workflows.

## How to use the AI Automators module
The AI Automators module is extremely flexible, and will interact with the
modules installed on your site to allow use in a number of different ways. We
recommend that you use the [AI Automators module guide](https://project.pages.drupalcode.org/ai/latest/modules/ai_automators/index) to assist with
using the module.

## Develop for it
Check the [developers guide](https://project.pages.drupalcode.org/ai/developers/developer_information) for information on how to develop using the
AI module.

### Override field processing decisions
To override whether an automator field should run after `checkIfEmpty()`
normalization, subscribe to
`Drupal\ai_automators\Event\ShouldProcessFieldEvent::EVENT_NAME`
(`ai_automator.should_process_field`), and call `setShouldProcess(TRUE|FALSE)`.

Rules extending `RuleBase` can override `postCheckIfEmpty()` and return an
empty array to force the value to be treated as empty before the event is
dispatched.

Rules not extending `RuleBase` can implement
`Drupal\ai_automators\PluginInterfaces\AiAutomatorPostCheckIfEmptyInterface`
to provide the same behavior.
