# General Settings for AI Module

## What is the AI Module?
The AI module provides a framework for integrating AI capabilities into Drupal. It allows developers to create AI-powered applications by leveraging various AI providers and operations. The module supports multiple AI providers, enabling users to choose the best fit for their needs.

The AI module itself doesn't really provide you with any functionality, you will have to install and enable, either one of the submodules - see the documentation for each of them - or a contrib module that uses the AI module to provide functionality.

## Dependencies
While the AI module can be installed on its own, the first thing you should do is install an AI Provider module. The AI module does not provide any AI Providers by itself, but it does provide a framework for integrating them. You can find various AI Provider modules in the Drupal community, such as OpenAI, Hugging Face, and others.

See the list of the providers on the left sidebar.

## How to configure default models in the AI module
When you have installed an AI Provider module, you can configure the default models for that provider if it did not do it on its own. This is done by visiting the AI module's configuration page, located under `/admin/config/ai/settings`.

You will be able to set a default model for each operation type that your provider supports. The operation types are defined by the AI Provider module and can include operations like chat, moderation, text-to-image, and more.

What this does is tell other modules that use the AI module which model to use by default when they call the AI Core module. This is useful so they can "just work" without having to configure a model for each operation. Most contrib modules will allow you to override the default model for each operation type, so you can still customize the behavior as needed.

## How to change the timeout for AI operations
Some AI operations can take a long time to complete, especially if they involve complex tasks like translations or thinking models. The AI module allows you to configure the HTTP request timeout for AI operations to ensure that your site remains responsive. Visit the AI module's configuration page at `/admin/config/ai/settings` to adjust the timeout settings according to your needs. The timeout is under the "Advanced Settings" section, and you can set it to a value that works best for your use case. The default timeout is 60 seconds, but you may want to increase it for operations that are expected to take longer.

## How to setup moderation in the AI module
Moderation is a crucial aspect of AI applications, especially when dealing with user-generated content. Many AI Provider services will kick you off their platform if you send too many requests that are breaking their content policies, so it is important to have a moderation layer in place if you will expose the AI module to end users or editors.

Moderation models or services are always ok to send any content to, as they will not generate any content themselves, but only check if the content is safe to use.

Some Drupal module providers like OpenAI and Mistral will automatically moderate the content you send to them, so you do not need to enable the moderation module if you are only using those providers or you might be able to setup moderation in the provider's configuration.

But the AI module also offers a way to add moderation to any AI Provider module that does not provide moderation by itself.

This can be set under AI moderation settings, located under `/admin/config/ai/ai-external-moderation`.

This works by setting a provider, possible tags if it should not run everywhere and then one or more models that will be used to moderate the content. The moderation will be run before any other operation is performed, so if the moderation model indicates that the content is unsafe, the operation will not be performed.
