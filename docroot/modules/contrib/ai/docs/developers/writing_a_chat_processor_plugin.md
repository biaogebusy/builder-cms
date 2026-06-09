# Writing a ChatProcessor Plugin

The AI module provides a plugin system for processing chat input and generating responses through ChatProcessor plugins. This allows developers to create custom chatbot behaviors, implement features like RAG (Retrieval-Augmented Generation), integrate custom AI services, or create specialized processing workflows.

## Overview

ChatProcessor plugins decouple any chatbot UI from any processing logic. The AI module provides the plugin infrastructure in the core module, while the AI Chatbot submodule provides a reference implementation for using these plugins in a chatbot interface.

The plugin system is built on [Drupal's plugin system](https://api.drupal.org/api/drupal/core%21core.api.php/group/plugin_api) and uses PHP 8 attributes for plugin discovery.

## Creating a ChatProcessor Plugin

### 1. Create the Plugin Class

Create a new PHP class in `src/Plugin/ChatProcessor/` that extends `ChatProcessorBase`:

```php
<?php

namespace Drupal\your_module\Plugin\ChatProcessor;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\Attribute\ChatProcessor;
use Drupal\ai\Base\ChatProcessorBase;
use Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[ChatProcessor(
  id: 'your_plugin_id',
  label: new TranslatableMarkup('Your Plugin Label'),
  description: new TranslatableMarkup('Description of what your plugin does.')
)]
class YourPlugin extends ChatProcessorBase implements ChatProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function doExecute(): ChatOutput {
    $input = $this->getInput();
    // Your processing logic here

    $responseMessage = new ChatMessage('assistant', 'Your response');
    return new ChatOutput($responseMessage, [], [], NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'your_setting' => 'default_value',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['your_setting'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your Setting'),
      '#default_value' => $this->configuration['your_setting'],
    ];
    return $form;
  }
}
```

### 2. Required Methods

All ChatProcessor plugins must implement:

- `doExecute()`: Main processing logic that returns a `ChatOutput`
- `defaultConfiguration()`: Default configuration values (from `ConfigurableInterface`)
- `buildConfigurationForm()`: Configuration form (from `PluginFormInterface`)

### 3. Available Methods

The `ChatProcessorBase` class provides these methods:

#### Input/Output Management
- `setInput(ChatInput $input)`: Set the chat input
- `getInput(): ?ChatInput`: Get the chat input
- `setOutput(ChatOutput $output)`: Set the output
- `getOutput(): ?ChatOutput`: Get the output

#### Thread Management
- `setThreadId(string $threadId)`: Set thread ID for conversation continuity
- `getThreadId(): ?string`: Get thread ID

#### Execution State
- `setFinished(bool $finished)`: Set execution finished state
- `getFinished(): bool`: Get execution finished state

#### File Handling
- `setInputFiles(array $files)`: Set non-image files for processing
- `getInputFiles(): array`: Get non-image files
- `allowedFileExtensions(): array`: Define allowed file extensions (override to customize)
- `allowsImages(): bool`: Define if images are allowed (override to customize)

### 4. File Handling

You can control what file types your plugin accepts:

```php
/**
 * {@inheritdoc}
 */
public function allowedFileExtensions(): array {
  return ['pdf', 'doc', 'docx', 'txt'];
}

/**
 * {@inheritdoc}
 */
public function allowsImages(): bool {
  return TRUE; // or FALSE to disable image uploads
}
```

## Built-in Plugins

There are two example implementations in `ai_test` module.

## Using ChatProcessor Plugins

### Programmatic Usage

```php
// Get the plugin manager
$manager = \Drupal::service('plugin.manager.ai.chat_processor');

// Get available plugins
$plugins = $manager->getDefinitions();

// Create a plugin instance
$plugin = $manager->createInstance('your_plugin_id', [
  'your_setting' => 'custom_value',
]);

// Set input
$messages = [
  new ChatMessage('user', 'Hello, how can you help?'),
];
$input = new ChatInput($messages);
$plugin->setInput($input);

// Execute
$output = $plugin->execute();

// Get response
$response = $output->getNormalized();
echo $response->getText();
```

## Examples

### Simple Echo Plugin

```php
public function doExecute(): ChatOutput {
  $input = $this->getInput();
  $messages = $input->getMessages();

  $userMessage = '';
  foreach ($messages as $message) {
    if ($message instanceof ChatMessage && $message->getRole() === 'user') {
      $userMessage = $message->getText();
      break;
    }
  }

  $responseMessage = new ChatMessage('assistant', "You said: " . $userMessage);
  return new ChatOutput($responseMessage, [], [], NULL);
}
```

### RAG Plugin with Custom Search

```php
public function doExecute(): ChatOutput {
  $input = $this->getInput();
  $userMessage = $this->extractUserMessage($input);

  // Perform custom search
  $relevantContent = $this->searchContent($userMessage);

  // Create enhanced input with context
  $enhancedInput = $this->addContextToInput($input, $relevantContent);

  // Process with AI provider
  $aiProvider = \Drupal::service('ai.provider');
  $response = $aiProvider->chat($enhancedInput, 'openai', 'gpt-4')->getNormalized();

  return new ChatOutput($response, [], [], NULL);
}
```

## Architecture

### Class Hierarchy

```
ChatProcessorInterface (interface)
  ├── Extends: PluginFormInterface
  ├── Extends: ConfigurableInterface
  └── ChatProcessorBase (abstract base class)
      ├── Extends: PluginBase
      └── Your Plugin Implementation
```

### Plugin Discovery

The `ChatProcessorPluginManager` uses PHP 8 attribute-based discovery to find plugins across all modules:

- **Namespace:** `Plugin/ChatProcessor`
- **Attribute:** `Drupal\ai\Attribute\ChatProcessor`
- **Interface:** `Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface`

## Troubleshooting

### Plugin Not Found
- Ensure your plugin class is in the correct namespace: `Drupal\your_module\Plugin\ChatProcessor`
- Check that the `#[ChatProcessor]` attribute is properly defined
- Clear the plugin cache: `drush cache:rebuild`

### Configuration Issues
- Verify your configuration form is properly implemented
- Check the configuration schema matches your form structure
- Ensure `defaultConfiguration()` returns an array with all expected keys

### Streaming Issues
- Verify your plugin returns a `ChatOutput` object
- Check that the input has streaming enabled: `$input->setStreamedOutput(TRUE)`
- Ensure your AI provider supports streaming
- Return a `StreamedChatMessageIterator` for streaming responses

### Type Errors
- Ensure you're using `ChatInput`, `ChatOutput`, and `ChatMessage` from `Drupal\ai\OperationType\Chat`
- Check that your `doExecute()` method returns a `ChatOutput` instance
- Verify dependency injection is correctly configured in `create()` and `__construct()`

## Best Practices

1. **Use Dependency Injection:** Inject services through the constructor for better testability
2. **Handle Errors Gracefully:** Catch exceptions and return meaningful error messages
3. **Implement Configuration:** Provide sensible defaults and clear form labels
4. **Document Your Plugin:** Add docblocks explaining what your plugin does
5. **Test Thoroughly:** Test with various input types, including edge cases
6. **Support Streaming:** Consider implementing streaming for better UX with long responses
7. **Validate Input:** Check that required data is present before processing

## See Also

- [Base Calls](base_calls.md) - Using AI operations
- [Chat Operations](call_chat.md) - Working with chat operations
- [Streaming Chat](call_chat_streaming.md) - Implementing streaming
- [AI Chatbot Module](https://git.drupalcode.org/project/ai/-/tree/1.x/modules/ai_chatbot) - Reference implementation


**This document was generated with AI assistance.**
