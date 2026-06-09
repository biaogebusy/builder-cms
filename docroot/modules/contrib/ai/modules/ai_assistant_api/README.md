# AI Assistant API
## What is the AI Assistant API module?
**This is an experimental module not recommended for production sites**

The AI Assistant API provides an API to normalize interactions between a site
and an LLM functioning as a chatbot. It creates a plugin-based system that
allows other modules to create specific actions that the LLM can trigger and
report on. In conjunction with the AI Chatbot module, this can allow site users
to talk to a chosen LLM and ask it to perform tasks on the site.

## How to configure the AI Assistant API module
For more information, please see the [AI Assistant API module documentation](https://project.pages.drupalcode.org/ai/latest/modules/ai_assistant_api/).

## AI Chatbot module
### What is the AI Chatbot module?
The AI Assistant API module provides an API to manage real-time messages to and
from an LLM, and trigger actions the LLM "decides" the user has requested.
However, it does not provide a front-end for this process. The AI Chatbot module
provides a boilerplate frontend, consisting of a block with a text-input that
pushes user messages to the AI Assistant API, and renders LLM responses as
user-readable text.

### How to configure the AI Chatbot module
For more information, please see the [AI Assistant API module documentation](https://project.pages.drupalcode.org/ai/latest/modules/ai_assistant_api/).

## Developing for the AI Assistants API module.
For more information on providing your own plugins, please see [the Develop an API Assistant Action section](https://project.pages.drupalcode.org/ai/developers/develop_api_assistant_action.md).
