# AI Assistant API
## What is the AI Assistant API module?
**This is an experimental module not recommended for production sites**
The AI Assistant API provides an API to normalize interactions between a site
and an LLM functioning as a chatbot. It creates a plugin-based system that
allows other modules to create specific actions that the LLM can trigger and
report on. In conjunction with the AI Chatbot module, this can allow site users
to talk to a chosen LLM and ask it to perform tasks on the site.

## Dependencies
1. The AI module must be installed and configured.
2. At least one Provider module must be enabled and configured.

The default configuration for this module assumes that one or more modules
providing AI Assistant Actions have also been enabled, such as the [AI search](../ai_search/index.md)
sub-module or the [AI Agents](https://www.drupal.org/project/ai_agents) module.
Without any available actions, the AI Assistant will be limited to what the
chosen LLM can do, and will have no information about the site the module is
installed on.

If no modules providing AI Assistant Actions are available, the Pre Action
Prompt section of the AI Assistant form MUST be altered from its default value,
in order to instruct the LLM about how to respond to user input. To do this, you
will need to add

`$settings['ai_assistant_advanced_mode_enabled'] = TRUE;`

to your site's settings.php file. With this added, you will be able to edit the
contents of the Advanced section.

The form details the tokens that can be included in the Pre Action Prompt to
pass other settings from the form: these are not mandatory for you to include,
but if they are excluded you must detail all the information needed by the LLM
to respond to a user's messages within the Pre Action Prompt.

Please note, **editing this section requires experience of writing LLM prompts
and may produce unexpected results**.

## How to configure the AI Assistant API module
### Standard configuration
1. Enable the module as normal.
2. Visit /admin/config/ai/ai-assistant to view existing assistants or add your own.
3. When adding an assistant, you will be able to set prompts for the assistant:
    1. **Pre Action Prompt**: this section is not editable by default (see [Dependencies](#dependencies)) and is the main prompt sent to the LLM prior to any configured actions being run.
    2. **System Prompt**: this section explains to the LLM what role it should adopt when responding to the user's messages. It is sent as part of the Pre Action Prompt.
    3. **Pre-prompt Instructions**: additional instructions for the LLM about how to respond to messages. It is sent as part of the Pre Action Prompt.
4. If there are available API Assistant Actions, you will be able to select which actions are available to the LLM. The selected Actions will be run in the order listed in the form. This section will not appear if there are no available Actions.
5. When you have completed the form as you require, click save to create the AI Assistant.

### Advanced configuration
By default, the AI Assistant API module will use the Pre Action Prompt and System Prompt provided within the /resources
directory of the module. This ensures that the module will run the latest version of these prompts. However, if you wish
to use custom values for these then you should add the following to your settings.php file:

`$settings['ai_assistant_custom_prompts'] = TRUE;`

This also allows different AI Assistants to use different Pre Action Prompts and System Prompts to each other. You may
also wish to enable the `ai_assistant_advanced_mode_enabled` setting described in the [Dependencies](#dependencies)
section so that these fields can be edited through the UI.

## AI Chatbot module
### What is the AI Chatbot module?
The AI Assistant API module provides an API to manage real-time messages to and
from an LLM, and trigger actions the LLM "decides" the user has requested.
However, it does not provide a front-end for this process. The AI Chatbot module
provides a boilerplate frontend, consisting of a block with a text-input that
pushes user messages to the AI Assistant API, and renders LLM responses as
user-readable text.

### Dependencies
The AI Chatbot module requires that the AI Assistant API module has been
installed and configured (see [How to configure the AI Assistant API module](#how-to-configure-the-ai-assistant-api-module)).

The AI Deepchat Chatbot requires the [league/commonmark](https://github.com/thephpleague/commonmark)
library. This must be downloaded and made available to the codebase: it is
recommended to use composer to achieve this.

### How to configure the AI Chatbot module
1. Enable and configure the AI Assistant API module (see [How to configure the AI Assistant API module](#how-to-configure-the-ai-assistant-api-module)).
2. Enable the AI Deepchat Chatbot module.
3. Visit /admin/structure/block
4. Choose a region of your theme template and click the "place" button.
5. Select the AI Chatbot block and press the place button.
6. Configure the block:
    1. Give it an admin name and user-facing label
    2. Make sure to not show the label.
    3. Select which AI Assistant to use.
    4. Provide an initial statement to use shown to the user.

### How to use the AI Chatbot
When an AI Chatbot block is placed on a page, it will display its label to the
user. If the user clicks it, it will open a form to allow the user to pass
messages via the AI Assistant API and see the responses.

The message history can be retained inside the block until the page is reloaded
or the user navigates away, depending on settings in the AI Assistant.

### Customize the Chatbot.
The Chatbot is based on [Deepchat](https://deepchat.dev/) by OvidijusParsiunas
and can be customized both in look and feel. The designing of the chatbot is
done via attributes, see the [documentation on deepchat.dev](https://deepchat.dev/examples/design), but we
have abstracted it away into [yaml files](https://git.drupalcode.org/project/ai/-/blob/1.0.x/modules/ai_chatbot/deepchat_styles/bard.yml?ref_type=heads).

You can in your active theme add a folder called deepchat_styles and load your
own custom themes to use.

There is also a hook called [hook_deepchat_settings](https://git.drupalcode.org/project/ai/-/blob/1.0.x/modules/ai_chatbot/ai_chatbot.api.php?ref_type=heads) where you can change the attributes on the fly.

## AI Assistant Actions
AI Assistant Actions implement Drupal's inbuilt [Plugin API](https://www.drupal.org/docs/drupal-apis/plugin-api/plugin-api-overview)
to provide ways for a configured AI Assistant to interact with the Drupal site
it has been installed on. These are provided by modules as part of their code,
and the provider modules should provide details of what actions they allow an
assistant to perform.

For more information on providing your own plugins, please see [the Develop an API Assistant Action section](../../developers/develop_api_assistant_action.md).
