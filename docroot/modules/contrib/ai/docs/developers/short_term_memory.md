# Short-Term Memory

"Short-Term Memory" in AI refers to mechanisms for an AI agent to temporarily retain, access and compress relevant information for the current task or conversation. In so called "context engineering", the goal is always to provide the AI with the most relevant context, while keeping within the token limits of the underlying model and not causing instruction or context overload.

For more information please see the [OpenAI Cookbook on Short-Term Memory](https://cookbook.openai.com/examples/agents_sdk/session_memory).

## Implementing Short-Term Memory in the AI Module

If you are implementing a custom AI solution, that is looping over many threads or that might have to take long chat histories into account, you should consider implementing or using a custom Short-Term Memory plugin. The AI module comes with an API for this, as well as two default implementations:

**Last N** - This uses simple context trimming, where only the last N messages are kept in the context. This is useful for simple use cases, or when you want to make sure that the AI always has the most recent context.
**Agent Memory Summarizer** - This plugin uses a more advanced approach, where it keeps track of tool calls and their results, and summarizes the context based on this. This is useful for more complex use cases, where the AI might have to take into account many different pieces of information, but only a few are relevant for the current task. Note that the default prompt of this plugin is generic, and you should consider customizing it for your specific use case.

### Implementing in code.
The Short-Term Memory plugins are located in `\Drupal\ai\Plugin\AiShortTermMemory` and implements the `AiShortTermMemoryInterface`. You can create your own by extending the `AiShortTermMemoryPluginBase` class, and implementing the required methods. It is a configurable plugin, so if you are going to have a complex setup where you can pick and choose between different implementations, you should also implement a plugin form and store any configuration in your plugin instance.

What you need to do, is to implement loading an instance of the plugin, and then right before you send off an AI request, you use the `process` method to process the memory, where you give in your history, tools, system prompt and some other details. At the end you can use the `getChatMessages`, `getTools` and `getSystemPrompt` methods to get the processed context to change the AI request with.
