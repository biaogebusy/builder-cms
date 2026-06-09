# Guardrails

Guardrails let you add safety and validation checks on AI inputs and outputs. They run automatically before and after AI generation, and can pass, stop, or rewrite content based on your rules.

The system is built on Drupal's plugin architecture, so you can create custom guardrail plugins, group them into sets, and configure stop thresholds through the admin UI.

## Architecture Overview

The Guardrails system has these main components:

- **Guardrail plugins** implement `AiGuardrailInterface` and contain the actual validation logic.
- **Guardrail entities** (`AiGuardrail` config entities) wrap a plugin with admin-configurable settings.
- **Guardrail sets** (`AiGuardrailSet` config entities) group guardrails into pre-generation and post-generation lists with a stop threshold.
- **`GuardrailsEventSubscriber`** listens to `PreGenerateResponseEvent` and `PostGenerateResponseEvent` and runs the configured guardrails.
- **`AiGuardrailHelper`** provides a convenience method to attach a guardrail set to an AI input.

### How Guardrails Execute

```
Input created
    │
    ▼
AiGuardrailHelper::applyGuardrailSetToChatInput()
    │  attaches a guardrail set to the input
    ▼
PreGenerateResponseEvent fires
    │
    ▼
GuardrailsEventSubscriber::applyPreGenerateGuardrails()
    │  runs each pre-generate guardrail plugin
    │  aggregates StopResult scores
    │  if score >= stop_threshold → forces output, skips AI call
    │  if RewriteInputResult → rewrites the last message
    ▼
AI provider generates response
    │
    ▼
PostGenerateResponseEvent fires
    │
    ▼
GuardrailsEventSubscriber::applyPostGenerateGuardrails()
    │  runs each post-generate guardrail plugin
    │  if score >= stop_threshold → replaces output
    │  if RewriteOutputResult → rewrites the response
    ▼
Final output returned
```

## Result Types

Every guardrail plugin returns a `GuardrailResultInterface` from its `processInput()` and `processOutput()` methods. There are four result types:

| Result | `stop()` | Effect |
|--------|----------|--------|
| `PassResult` | `false` | Input/output passes without changes. |
| `StopResult` | `true` | Signals the input/output should be blocked. Carries a `score` (default `1.0`) that is aggregated across guardrails. |
| `RewriteInputResult` | `false` | Replaces the last chat message text with the result's message (pre-generation only). |
| `RewriteOutputResult` | `false` | Replaces the AI response text with the result's message (post-generation only). |

All result types extend `AbstractResult` and take three constructor arguments:

```php
new StopResult(
  message: 'This content violates the regexp pattern.',
  guardrail: $this,          // The guardrail plugin instance.
  context: [],               // Optional context array.
  score: 1.0,                // StopResult only: the severity score.
);
```

## Score Aggregation and Stop Threshold

Each `AiGuardrailSet` has a **stop threshold** (a float). When guardrails in a set run, the subscriber aggregates the `score` values from all `StopResult` instances. If the aggregated score reaches or exceeds the stop threshold, execution stops and the AI call is either skipped (pre-generation) or the output is replaced (post-generation).

This lets you combine multiple guardrails where each one contributes a partial score. For example, three guardrails each returning a `StopResult` with score `0.4` would aggregate to `1.2` — exceeding a threshold of `1.0`.

## Writing a Custom Guardrail Plugin

Guardrail plugins live in `src/Plugin/AiGuardrail/` in your module and use the `#[AiGuardrail]` PHP attribute for discovery.

### Minimal Example

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

### With Configuration Form

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

## Special Interfaces

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

The built-in `RestrictToTopic` guardrail is a real-world example of this pattern. It uses an LLM to determine whether the user's message matches a list of allowed or disallowed topics.

### StreamableGuardrailInterface

For guardrails that need to evaluate content **during streaming** — before the full response has been received — implement `StreamableGuardrailInterface`. These guardrails hook into the stream iteration itself and can buffer suspicious portions in real-time, then decide whether to release, suppress, or rewrite them.

This is the right interface when you need to stop harmful content from reaching the user mid-stream rather than waiting for the full response.

#### How it works

1. Each incoming chunk of streamed text is checked against the pattern returned by `getStartRegex()`. If `getStartRegex()` returns an empty string, the guardrail treats the very first chunk as a match and activates immediately. Otherwise the guardrail accumulates chunks in an internal buffer and checks the combined text against the start regex on each chunk.
2. Because a start pattern can be split across two consecutive chunks (e.g. `<sta` followed by `rt>`), the guardrail system does not pass the full buffer to the consumer straight away. Instead, it only passes content up to the last sentence boundary (period or newline) and holds the remainder back for the next chunk. If no sentence boundary exists yet, nothing is passed to the consumer until one appears or the start regex matches.
3. Once `getStartRegex()` matches, the guardrail becomes **active**. From this point all incoming chunks are held in the buffer and nothing reaches the consumer.
4. While active, each new chunk is appended to the buffer and the full buffer is tested against `getStopRegex()`. When the stop regex matches, `processStreamedBuffer()` is called with everything that was buffered since activation. The return value decides what the consumer receives: pass the original content through (`PassResult`), replace it with a different message (`RewriteOutputResult`), or suppress it entirely (`StopResult`).
5. If the buffer grows beyond `maxGuardrailBufferSize` (default 8,192 characters) before the stop regex matches, `processStreamedBuffer()` is called immediately to prevent unbounded memory growth.
6. When the stream ends, any content that was buffered while the guardrail was active is passed to `processStreamedBuffer()`. Any content that was buffered while the guardrail was inactive (held back waiting for a sentence boundary that never arrived) is passed to the consumer as-is to prevent data loss.

#### Minimal example

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

#### Tuning the max buffer size

If your guardrail expects very long buffered sections, raise the limit on the iterator before starting the stream:

```php
$iterator->setMaxGuardrailBufferSize(32768); // 32 KB
```

## Applying Guardrails to AI Input

Use `AiGuardrailHelper::applyGuardrailSetToChatInput()` to attach a guardrail set to any input before making an AI call:

```php
// In a service or controller with dependency injection:
$guardrail_helper = \Drupal::service('ai.guardrail_helper');

$input = new ChatInput([
  new ChatMessage('user', 'Tell me about Drupal.'),
]);

// Attach the guardrail set by its machine name.
$input = $guardrail_helper->applyGuardrailSetToChatInput('my_guardrail_set', $input);

// Make the AI call as usual. Guardrails run automatically via events.
$response = $provider->chat($input, $model_id, ['my_module']);
```

The method clones the input and calls `addGuardrailSet()` on it. When the AI provider fires its pre/post-generation events, the `GuardrailsEventSubscriber` iterates every attached set and runs its configured guardrails.

### Attaching multiple guardrail sets

An input may carry more than one guardrail set — e.g. one attached by the caller and one by middleware. Call `applyGuardrailSetToChatInput()` repeatedly, or use the input API directly:

```php
$input->addGuardrailSet($set_a);
$input->addGuardrailSet($set_b);
// Or replace the whole list:
$input->setGuardrailSets([$set_a, $set_b]);
```

Sets are keyed by id; re-adding the same id via `addGuardrailSet()` replaces that entry in place. `setGuardrailSets()` replaces the entire list in one call and accepts either a list or a keyed map — keys are ignored and re-derived from each set's id. Each set's `stop_threshold` is evaluated independently — scores are not aggregated across sets. If any set crosses its own threshold, processing of remaining sets is short-circuited and the stop message is returned as the output.

The legacy single-set methods `setGuardrailSet()` / `getGuardrailSet()` are deprecated — use `addGuardrailSet()` / `getGuardrailSets()` instead.

### Global guardrails

Site administrators can configure one or more guardrail sets to be applied to **every** AI request, regardless of whether the caller opted in. Configure them at *Configuration → AI → AI Guardrails → Global guardrails* (`/admin/config/ai/guardrails/global`). The selected ids are stored under `ai.settings:global_guardrails`.

Under the hood, `GlobalGuardrailsEventSubscriber` listens on `PreGenerateResponseEvent` at priority `100` (before the regular `GuardrailsEventSubscriber`). It **prepends** each configured global set to the input via `setGuardrailSets()`, so global safety/PII checks always evaluate the original prompt and the raw provider output before any caller-attached guardrail can rewrite them.

Important consequences of that ordering:

- A global set that crosses its `stop_threshold` short-circuits the pipeline before any caller-attached set runs. Global stops are non-negotiable.
- If a caller and a site-wide config both reference the same guardrail set id, the global wins and the set sits at the front — the caller's ordering intent is intentionally overridden by the site-wide configuration.

If you build your own pre-request subscriber and need to attach a set from code, subscribe at any priority `> 0` and call `$event->getInput()->addGuardrailSet($set)` (append) or `$event->getInput()->setGuardrailSets($yourSets + $event->getInput()->getGuardrailSets())` (prepend, same pattern as the global subscriber).

## Built-in Guardrail Plugins

### RegexpGuardrail

Checks the last chat message against a regular expression pattern. If the pattern matches, it returns a `StopResult`. Configurable fields:

- **Regexp Pattern**: The regular expression to match against.
- **Violation Message**: The message to display when the pattern matches. Use `@pattern` as a placeholder.

### RestrictToTopic

Uses an AI provider to classify whether the user's message relates to a list of valid or invalid topics. This is a non-deterministic guardrail that implements both `NonDeterministicGuardrailInterface` and `NonStreamableGuardrailInterface`. Configurable fields:

- **Valid Topics**: List of allowed topics (one per line).
- **Invalid Topics**: List of disallowed topics (one per line).
- **AI Provider/Model**: The LLM used for topic classification.
- **Violation messages**: Custom messages for invalid topics found or valid topics missing.

## Managing Guardrails in the UI

Guardrails are managed at **Administration > Configuration > AI > Guardrails** (`/admin/config/ai/guardrails`).

- **Guardrails** tab: Create and configure individual guardrail entities, each wrapping a guardrail plugin with specific settings.
- **Guardrail Sets** tab (`/admin/config/ai/guardrails/guardrail-sets`): Create sets that group guardrails into pre-generation and post-generation lists, and set the stop threshold.

Required permissions:

- `administer guardrails` for managing individual guardrail entities.
- `administer guardrail sets` for managing guardrail sets.

## Guardrail Modes

Guardrails can run at three points in the AI generation lifecycle, defined by `AiGuardrailModeEnum`:

| Mode | Enum Value | When |
|------|-----------|------|
| Pre-generate | `pre` | Before the AI provider call. Can stop or rewrite the input. |
| Post-generate | `post` | After the AI provider returns. Can stop or rewrite the output. |
| During-generate | `during` | Mid-stream evaluation via `StreamableGuardrailInterface`. Registered on the post-generate list; runs inside the stream iterator as chunks arrive. |
