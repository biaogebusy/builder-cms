# Develop a third party module

When you want to develop a third party module using the available [calls](base_calls.md) in the AI module, here are some tips and pointers.

## First check so a provider exists for your operation type.

If you want your third party module to use chat for instance and there is no chat provider installed, you have to have some graceful fallback where you tell that to the user.

If you load the AI Provider Plugin service (`ai.provider`), you can use the method `hasProvidersForOperationType` that takes the operation type data name and a boolean if it has to be setup (working API Key, connection etc.). This means that your module could do something like this.

```php
if (!\Drupal::service('ai.provider')->hasProvidersForOperationType('chat', TRUE)) {
  return [
    '#type' => 'markup',
    '#markup' => $this->t('Sorry, no provider exists for Chat, install one first'),
  ];
}
```

## Default model for operation type

For each operation type the end user can set a default provider and model. There are also a couple of pseudo operation types, that are based on capabilities + operation type, like Chat with Image Vision (chat_with_image_vision) and Chat with Complex JSON (chat_with_complex_json).

If you load the AI Provider Plugin service (`ai.provider`), you can use the method `getDefaultProviderForOperationType` that takes the operation type data name and gives back an array if it exists, so you can write something like this:

```php
$defaults = \Drupal::service('ai.provider')->getDefaultProviderForOperationType('chat');
if (empty($defaults['provider_id']) || empty($defaults['model_id'])) {
  // We have to do something else, since there is no manual provider.
}
```

## Making simple provider/model selection available

If you want the end user to be able to choose a provider and model in a settings page, you should use the `ai_provider_configuration` form element. This form element provides a standardized way for modules to select AI providers and models, ensuring consistency across the Drupal AI ecosystem.

For a simple selector (where you only care about the provider and model, but do not want to show advanced provider-specific configuration fields like temperature or max tokens), you can set `#advanced_config => FALSE`.

For more details, see the [AI Provider Configuration Form Element](ai_provider_configuration_element.md) documentation.

### Config Form Example

To use it in your settings form, define the element and link it directly to your configuration using `#config_target` (Drupal 10.2+):

```php
use Drupal\Core\Form\ConfigTarget;

$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('AI Provider'),
  '#description' => $this->t('Choose the AI provider and model to use.'),
  '#operation_type' => 'chat',
  '#advanced_config' => FALSE, // Hide advanced configuration fields.
  '#default_provider_allowed' => TRUE,
  '#config_target' => new ConfigTarget('my_module.settings', 'chat_provider'),
];
```

Make sure your configuration schema defines the target property as type `ai.provider_config`. In `my_module/config/schema/my_module.schema.yml`:

```yaml
my_module.settings:
  type: config_object
  label: 'My Module Settings'
  mapping:
    chat_provider:
      type: ai.provider_config
      label: 'Chat Provider Configuration'
```

### Loading the Provider from Configuration

To load and instantiate the provider from the saved configuration:

```php
$config = \Drupal::config('my_module.settings');
$provider_config = $config->get('chat_provider') ?? [];

/** @var \Drupal\ai\AiProviderPluginManager $provider_manager */
$provider_manager = \Drupal::service('ai.provider');

if (empty($provider_config) || !empty($provider_config['use_default'])) {
  // Use the default provider configured for the operation type.
  $default = $provider_manager->getDefaultProviderForOperationType('chat');
  $provider_id = $default['provider_id'] ?? NULL;
  $model_id = $default['model_id'] ?? NULL;
  $configuration = [];
}
else {
  $provider_id = $provider_config['provider'] ?? NULL;
  $model_id = $provider_config['model'] ?? NULL;
  $configuration = $provider_config['config'] ?? [];
}

if (!$provider_id) {
  throw new \Exception('No AI provider configured.');
}

// Create the provider instance and set any configuration (e.g. temperature).
$provider = $provider_manager->createInstance($provider_id);
if (!empty($configuration)) {
  $provider->setConfiguration($configuration);
}

// Send your chat message.
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

$input = new ChatInput([
  new ChatMessage('user', 'Hello!'),
]);
$response = $provider->chat($input, $model_id, ['my-custom-module']);
```

## Making advanced provider/model selection available

If you want the end user to be able to configure model-specific settings (such as temperature, max tokens, etc.) via AJAX, you can enable advanced configuration on the same form element by setting `#advanced_config => TRUE`.

This is the recommended replacement for the deprecated form helper service (`AiProviderFormHelper`).

### Config Form Example

```php
use Drupal\Core\Form\ConfigTarget;

$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('AI Provider with Configuration'),
  '#operation_type' => 'chat',
  '#advanced_config' => TRUE, // Show advanced configuration fields.
  '#default_provider_allowed' => TRUE,
  '#config_target' => new ConfigTarget('my_module.settings', 'chat_provider'),
];
```

The returned value automatically handles types, constraints, and validation according to the selected provider's configuration schema. Storing and instantiating the provider works exactly the same as in the simple selection example above.

## Listening for provider changes.
Sometimes when you have your own provider picker, you might need to listen for events where this provider is disabled/uninstalled. This can be done
via the [Provider Disabled event](events.md)

### Setting Chat system messages.
There is an abstracted way to set system messages for the providers that allow for it, the method is called `setSystemPrompt` and just takes the system role you want to set. Note that different providers weight these instructions more or less, so in certain cases it might make more sense to use two user messages instead.

So it would be something like this:

```php
$provider =  \Drupal::service('ai.provider')->createInstance('openai');
// Set a system message.
$input = new ChatInput([
  new ChatMessage('user', 'Hello!'),
]);
$input->setSystemPrompt('You are an expert at bananas.');
```

## Streaming Chat

There is a way to output the chat as a stream, meaning that it outputs the words as they come in. For your third party provider to support it, you need to first turn this on (or have it as a config) via the method `$input->setStreamedOutput(TRUE);` on the ChatInput object.

When you have turned this on, it's important to note that not all providers support this, so you have to add a check and respond correctly depending on how it works. This is an example of how to do this.

```php
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\Response\AiStreamedResponse;
use Symfony\Component\HttpFoundation\Response;

$response = $ai_provider->chat($some_messages, 'model')->getNormalized();
// If it's streaming.
if (is_object($response) && $response instanceof StreamedChatMessageIteratorInterface) {
  // AiStreamedResponse sets the required headers for streaming
  // (X-Accel-Buffering, Cache-Control, Content-Type) and clears
  // output buffers automatically.
  return new AiStreamedResponse(function () use ($response) {
    // Iterate the response.
    foreach ($response as $message) {
      // Echo and flush.
      echo $message->getText();
      flush();
    }
  });
}
// Otherwise non-streaming.
else {
  // Return a normal response.
  return new Response($response->getText());
}
```

Note that you might have to have javascript that handles this as well and also a server that does not buffer whole inputs, but can send them out chunked.

