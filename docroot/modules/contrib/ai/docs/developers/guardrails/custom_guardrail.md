# Writing a Custom Guardrail Plugin

Guardrail plugins are built on Drupal's plugin architecture. They live in `src/Plugin/AiGuardrail/` in your custom module and use the `#[AiGuardrail]` PHP attribute for discovery.

> [!TIP]
> If you are pair-programming with the AI Coding Assistant, you can ask it to use the `create-guardrail-plugin` skill to automatically scaffold a new guardrail plugin for you.

## Minimal Example

A basic guardrail plugin must extend `AiGuardrailPluginBase` and implement `processInput()` and `processOutput()`.

```php
<?php

declare(strict_types=1);

namespace Drupal\my_module\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\AiGuardrailPluginBase;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Blocks messages that exceed a maximum word count.
 */
#[AiGuardrail(
  id: 'max_word_count',
  label: new TranslatableMarkup('Max Word Count'),
  description: new TranslatableMarkup('Blocks input that exceeds a word limit.'),
)]
class MaxWordCount extends AiGuardrailPluginBase {

  /**
   * {@inheritdoc}
   */
  public function processInput(InputInterface $input): GuardrailResultInterface {
    if (!$input instanceof ChatInput) {
      return new PassResult('Not a chat input, skipping.', $this);
    }

    $messages = $input->getMessages();
    $last_message = end($messages);

    if (!$last_message instanceof ChatMessage) {
      return new PassResult('No text message found.', $this);
    }

    $word_count = str_word_count($last_message->getText());
    $max_words = 500;

    if ($word_count > $max_words) {
      return new StopResult(
        "Your message has $word_count words, which exceeds the $max_words word limit.",
        $this,
      );
    }

    return new PassResult('Word count within limits.', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function processOutput(OutputInterface $output): GuardrailResultInterface {
    return new PassResult('Output check not applicable.', $this);
  }

}
```

## Adding a Configuration Form

If your guardrail needs admin-configurable settings, implement `ConfigurableInterface` and `PluginFormInterface`:

```php
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;

#[AiGuardrail(
  id: 'max_word_count',
  label: new TranslatableMarkup('Max Word Count'),
)]
class MaxWordCount extends AiGuardrailPluginBase implements ConfigurableInterface, PluginFormInterface {

  public function getConfiguration(): array {
    return $this->configuration;
  }

  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  public function defaultConfiguration(): array {
    return [];
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['max_words'] = [
      '#type' => 'number',
      '#title' => 'Maximum word count',
      '#default_value' => $this->configuration['max_words'] ?? 500,
      '#min' => 1,
    ];
    return $form;
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->setConfiguration($form_state->getValues());
  }

  public function processInput(InputInterface $input): GuardrailResultInterface {
    // Use $this->configuration['max_words'] instead of a hardcoded value.
    $max_words = (int) ($this->configuration['max_words'] ?? 500);
    // ... same logic as above ...
  }

}
```

---

## Injecting Services

To use services in your guardrail, implement `ContainerFactoryPluginInterface` and inject them through the constructor as usual.

> [!IMPORTANT]
> Injected services must be stored in **`protected`** properties that are **not `readonly`**.

`AiGuardrailPluginBase` uses `DependencySerializationTrait`, which swaps every injected service for its service ID when the plugin is serialized and resolves it back on unserialization. This is not optional: the guardrail entity form has an `#ajax` callback on its guardrail select, so Drupal writes the form and its form state to the form cache, and the guardrail plugin instance is reachable from there. Without the trait, the container, the database connection and closures get dragged into the serialized payload, the cache write fails, and the settings subform never loads.

The trait imposes two constraints:

- **`protected`, not `private`.** The trait's `__sleep()` calls `get_object_vars()` from the scope of the class that uses the trait — `AiGuardrailPluginBase` — so it cannot see private properties declared on your subclass. A private service property is serialized whole.
- **not `readonly`.** The trait's `__wakeup()` reassigns the property to restore the service, and PHP forbids writing to an initialized readonly property outside the declaring scope.

Breaking either rule fails confusingly rather than obviously: you get an uninitialized typed property, or an `Error` thrown from inside `__wakeup()`.

```php
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MaxWordCount extends AiGuardrailPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    // Protected and not readonly, so DependencySerializationTrait can replace
    // this with its service ID for the form cache and restore it afterwards.
    protected LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.ai'),
    );
  }

}
```

---

## Special Interfaces

You can implement special interfaces to extend your guardrail's behavior:

### NonStreamableGuardrailInterface

A marker interface for guardrails that cannot process streamed responses. When a post-generation guardrail implements this interface, the event subscriber will reconstruct the full `ChatOutput` from the streamed iterator before passing it to `processOutput()`.

Use this when your guardrail needs the complete response text to make a decision (e.g., sentiment analysis on the full reply).

```php
use Drupal\ai\Guardrail\NonStreamableGuardrailInterface;

class MyGuardrail extends AiGuardrailPluginBase implements NonStreamableGuardrailInterface {
  // No additional methods required -- it is a marker interface.
}
```

### NonDeterministicGuardrailInterface

For guardrails that call AI services themselves (e.g., using an LLM to classify content). The plugin manager and event subscriber automatically inject the `AiProviderPluginManager` into plugins that implement this interface, so you can make AI calls within your guardrail logic.

Use the `NeedsAiPluginManagerTrait` for the boilerplate getter/setter:

```php
use Drupal\ai\Guardrail\NeedsAiPluginManagerTrait;
use Drupal\ai\Guardrail\NonDeterministicGuardrailInterface;

class MyAiGuardrail extends AiGuardrailPluginBase implements NonDeterministicGuardrailInterface {

  use NeedsAiPluginManagerTrait;

  public function processInput(InputInterface $input): GuardrailResultInterface {
    // Access the AI provider plugin manager.
    $provider_manager = $this->getAiPluginManager();

    // Get the default chat provider.
    $default = $provider_manager->getDefaultProviderForOperationType('chat');
    $provider = $provider_manager->createInstance($default['provider_id']);

    // Make an AI call to classify the input.
    $classification_input = new ChatInput([
      new ChatMessage('user', 'Classify this text: ' . $text),
    ]);
    $response = $provider->chat($classification_input, $default['model_id'], ['ai']);

    // Use the classification result to decide pass/stop.
  }

}
```

---

### StreamableGuardrailInterface

For guardrails that need to evaluate content **during streaming** — before the full response has been received — implement `StreamableGuardrailInterface`. These guardrails hook into the stream iteration itself and can buffer suspicious portions in real-time, then decide whether to release, suppress, or rewrite them.

This is the right interface when you need to stop harmful content from reaching the user mid-stream rather than waiting for the full response.

#### How it Works

1. Each incoming chunk of streamed text is checked against the pattern returned by `getStartRegex()`. If `getStartRegex()` returns an empty string, the guardrail treats the very first chunk as a match and activates immediately. Otherwise, the guardrail accumulates chunks in an internal buffer and checks the combined text against the start regex on each chunk.
2. Because a start pattern can be split across two consecutive chunks (e.g. `<sta` followed by `rt>`), the guardrail system does not pass the full buffer to the consumer straight away. Instead, it only passes content up to the last sentence boundary (period or newline) and holds the remainder back for the next chunk. If no sentence boundary exists yet, nothing is passed to the consumer until one appears or the start regex matches.
3. Once `getStartRegex()` matches, the guardrail becomes **active**. From this point all incoming chunks are held in the buffer and nothing reaches the consumer.
4. While active, each new chunk is appended to the buffer and the full buffer is tested against `getStopRegex()`. When the stop regex matches, `processStreamedBuffer()` is called with everything that was buffered since activation. The return value decides what the consumer receives: pass the original content through (`PassResult`), replace it with a different message (`RewriteOutputResult`), or suppress it entirely (`StopResult`).
5. If the buffer grows beyond `maxGuardrailBufferSize` (default 8,192 characters) before the stop regex matches, `processStreamedBuffer()` is called immediately to prevent unbounded memory growth.
6. When the stream ends, any content that was buffered while the guardrail was active is passed to `processStreamedBuffer()`. Any content that was buffered while the guardrail was inactive (held back waiting for a sentence boundary that never arrived) is passed to the consumer as-is to prevent data loss.

#### Minimal Example

```php
<?php

declare(strict_types=1);

namespace Drupal\my_module\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\AiGuardrailPluginBase;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\Guardrail\StreamableGuardrailInterface;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Blocks any content wrapped in [SENSITIVE]…[/SENSITIVE] during streaming.
 */
#[AiGuardrail(
  id: 'sensitive_block_stream',
  label: new TranslatableMarkup('Sensitive Block (streaming)'),
  description: new TranslatableMarkup('Suppresses content between [SENSITIVE] markers during streaming.'),
)]
class SensitiveBlockStream extends AiGuardrailPluginBase implements StreamableGuardrailInterface {

  public function getStartRegex(): string {
    return '/\[SENSITIVE\]/';
  }

  public function getStopRegex(): string {
    return '/\[\/SENSITIVE\]/';
  }

  public function processStreamedBuffer(string $buffered_content): GuardrailResultInterface {
    // Content between the markers is suppressed.
    return new StopResult('[Sensitive content was removed.]', $this);
  }

  public function processInput(InputInterface $input): GuardrailResultInterface {
    return new PassResult('', $this);
  }

  public function processOutput(OutputInterface $output): GuardrailResultInterface {
    return new PassResult('', $this);
  }

}
```

#### Registration

Streaming guardrails are registered on the **post-generate** list of a guardrail set. The `GuardrailsEventSubscriber` automatically detects `StreamableGuardrailInterface` implementations and registers them with the stream iterator before the stream starts. They do not run through the normal `processOutput()` post-generate path.

#### Tuning the Max Buffer Size

If your guardrail expects very long buffered sections, raise the limit on the iterator before starting the stream:

```php
$iterator->setMaxGuardrailBufferSize(32768); // 32 KB
```
