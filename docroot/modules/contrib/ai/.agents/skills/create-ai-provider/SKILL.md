---
name: create-ai-provider
description: Scaffolds a new AI Provider plugin for the Drupal AI module. Asks which operation types the provider supports (chat, embeddings, text-to-image, etc.), then generates the plugin class with the correct interface implementations, authentication setup, and model discovery.
---

<!-- ============================================================
     HARD STOP — READ THIS BEFORE DOING ANYTHING ELSE
     ============================================================ -->

> **CRITICAL GATE — YOU MUST NOT WRITE ANY CODE, CREATE ANY FILES, OR
> GENERATE ANY SCAFFOLDING UNTIL YOU HAVE COMPLETED STEP 0 BELOW AND
> RECEIVED THE USER'S EXPLICIT ANSWERS.**
>
> This is not a suggestion. This is a blocking prerequisite.
> Skipping Step 0 produces the wrong base class, wrong architecture,
> and code that must be thrown away.
>
> **Do Step 0 first. Ask questions. Wait for answers. Then proceed.**

# Create AI Provider Plugin

This skill scaffolds a new AI Provider plugin for the Drupal AI module.
Provider plugins connect Drupal to external AI services like OpenAI,
Anthropic, Ollama, or custom AI endpoints. They handle authentication,
model discovery, and implement operation type interfaces for the
capabilities they support.

## Step 0: Ask the User — MANDATORY BLOCKING STEP

**YOU MUST COMPLETE THIS STEP BEFORE WRITING ANY CODE.**

Do not infer, assume, or guess. Do not create any files. Do not generate
scaffolding. Do not start implementing. You must ask the user the
questions below and wait for their explicit answers before proceeding to
Step 1.

### 0a. Research OpenAI Compatibility FIRST

Before asking the user anything, **research whether the target provider
offers an OpenAI-compatible API endpoint**. Many providers do — including
Ollama, LM Studio, vLLM, Azure OpenAI, Groq, Together AI, Fireworks AI,
Anyscale, and others. Check:

- Does the provider's documentation mention `/v1/chat/completions`?
- Does it mention "OpenAI-compatible" or "OpenAI API format"?
- Can you point the OpenAI SDK at it by changing the base URL?

If YES → you must recommend Pattern C (`OpenAiBasedProviderClientBase`)
in your question. Explain why it saves massive effort.

If NO or UNSURE → ask the user directly (see 0c below).

### 0b. Ask the User Which Provider Type

You MUST ask this question using the ask_questions tool (or equivalent
interactive prompt). Do NOT answer it yourself:

> **What type of AI provider are you creating?**
>
> *Based on my research, [provider X] does/does not support an
> OpenAI-compatible API. Here is my recommendation:*
>
> - **Simple / Single operation type** — Supports one operation type
>   (usually `chat`). Extends `AiProviderClientBase`.
>   *Example: `DropAiProvider` — minimal chat-only provider.*
>
> - **Full-featured / Multi-operation** — Supports multiple operation
>   types (chat, embeddings, text-to-image, etc.). Extends
>   `AiProviderClientBase` and implements multiple interfaces.
>   *Example: `OpenAiProvider`.*
>
> - **OpenAI-compatible** *(recommended when the API supports it)* —
>   Uses OpenAI's API format at a different endpoint. Extends
>   `OpenAiBasedProviderClientBase` for built-in chat, embeddings,
>   text-to-image, text-to-speech, speech-to-text, moderation with
>   near-zero code.

**STOP HERE. Wait for the user's response. Do NOT proceed to Step 1
until you have their answer.**

### 0c. If Uncertain About OpenAI Compatibility

If you could not confirm from documentation whether the API is
OpenAI-compatible, you MUST ask the user directly — do not guess:

> **Does this provider use an OpenAI-compatible API?**
> Many AI services expose an OpenAI-compatible endpoint
> (`/v1/chat/completions`). If yes, we extend
> `OpenAiBasedProviderClientBase` which gives chat, embeddings,
> text-to-image, text-to-speech, speech-to-text, moderation out of
> the box.
>
> If you're not sure, we'll use `AiProviderClientBase` and implement
> everything from scratch — this is always safe.

If the user doesn't know, default to `AiProviderClientBase`.

### 0d. Rule: OpenAI-compatible → Pattern C

**When a provider is determined to be OpenAI-compatible (through your
research or user confirmation), you MUST use
`OpenAiBasedProviderClientBase` (Pattern C) unless the user explicitly
requests otherwise.** Pattern C dramatically reduces boilerplate because
chat, embeddings, moderation, text-to-image, text-to-speech, and
speech-to-text are already implemented in the base class.

### What NOT to Do

- Do NOT skip this step because you think you know the answer.
- Do NOT pick a pattern based on the user's description alone.
- Do NOT start writing any PHP, YAML, or other files before asking.
- Do NOT use `AiProviderClientBase` for a provider that has an
  OpenAI-compatible API — that wastes hundreds of lines reimplementing
  what Pattern C provides for free.

## Step 1: Gather Requirements

Ask the user for:

1. **Module machine name** — Convention: prefix with `ai_provider_` unless
   the user explicitly provides a name without it.
   Examples: `ai_provider_ollama`, `ai_provider_azure`, `ai_provider_mistral`.
   If the user says "create an Ollama provider", derive `ai_provider_ollama`.
2. **Provider ID** — plugin ID, should match the provider name portion.
   Convention: `{provider_name}` (e.g., `openai`, `anthropic`, `ollama`)
3. **Provider label** — human-readable, e.g., "My Custom AI", "Acme LLM"
4. **Which operation types?** — Select from:
   - `chat` — Text chat/completion (most common)
   - `embeddings` — Vector embeddings for semantic search
   - `text_to_image` — Image generation from text prompts
   - `text_to_speech` — Audio/speech generation from text
   - `speech_to_text` — Transcription/speech recognition
   - `moderation` — Content moderation/safety checks
   - `image_to_image` — Image transformation/editing
   - `image_to_video` — Video generation from images
   - `audio_to_audio` — Audio transformation
5. **Authentication method** — How does the API authenticate?
   - API key (most common)
   - OAuth2
   - No authentication (local services)
6. **Model discovery** — How are models discovered?
   - Predefined list (hardcoded models)
   - API endpoint (dynamic auto-discovery — **strongly preferred**)
   - User-configured (admin adds models manually)

   > **Strongly prefer API-based auto-discovery.** Most providers offer a
   > "list models" endpoint (e.g., `/v1/models`). Auto-discovery means
   > users never need to manually add models — the provider queries the
   > API and returns whatever models are available. This dramatically
   > improves UX and eliminates configuration drift when new models are
   > released. Only fall back to hardcoded or user-configured models when
   > the API genuinely does not offer model listing.

7. **Needs configuration form?** — Admin settings page for API key, etc.
8. **Needs custom model settings?** — Per-model configuration
   (temperature, max tokens, etc.)

## Step 2: Generate Module Files

Create the following files in the target module:

### 2.1 Module Info File

`{module}.info.yml`:

```yaml
name: '{Provider Label} Provider'
type: module
description: '{Provider Label} provider for AI module.'
package: AI Providers
core_version_requirement: ^10 || ^11
dependencies:
  - ai:ai
  - key:key
```

### 2.2 Provider Plugin Class

Create the plugin at `src/Plugin/AiProvider/{ClassName}.php`.

### Pattern A: Simple chat-only provider (extends AiProviderClientBase)

This minimal example implements only `chat` operation type.

```php
<?php

declare(strict_types=1);

namespace Drupal\{module}\Plugin\AiProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;

/**
 * Provides the {Provider Label} AI provider.
 */
#[AiProvider(
  id: '{provider_id}',
  label: new TranslatableMarkup('{Provider Label}'),
)]
class {ClassName} extends AiProviderClientBase implements ChatInterface {

  /**
   * The API client instance.
   *
   * @var object|null
   */
  protected $client = NULL;

  /**
   * The API key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('{module}.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    // Return hardcoded models or fetch from API.
    return [
      'model-1' => 'Model One',
      'model-2' => 'Model Two',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }

    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return ['chat'];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();

    // Normalize input to ChatInput.
    if (is_string($input)) {
      $input = new ChatInput([new ChatMessage('user', $input)]);
    }
    elseif (is_array($input)) {
      $messages = [];
      foreach ($input as $msg) {
        $messages[] = new ChatMessage($msg['role'] ?? 'user', $msg['content'] ?? '');
      }
      $input = new ChatInput($messages);
    }

    // --- Make the API request ---
    // Build the request payload from $input->getMessages().
    // Call your API client and get the response.

    try {
      // Example: $response = $this->client->chat($payload);
      $response_text = 'Example response from ' . $model_id;

      $message = new ChatMessage('assistant', $response_text);
      return new ChatOutput($message, $response_text, []);
    }
    catch (\Exception $e) {
      // Map API errors to the correct AI exception type.
      // Check for rate limit errors (HTTP 429, etc.).
      if (str_contains($e->getMessage(), 'Too Many Requests') || str_contains($e->getMessage(), 'Request too large')) {
        throw new AiRateLimitException($e->getMessage());
      }
      // Check for quota/credit exhaustion.
      if (str_contains($e->getMessage(), 'quota') || str_contains($e->getMessage(), 'insufficient_funds')) {
        throw new AiQuotaException($e->getMessage());
      }
      throw new AiResponseErrorException($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxInputTokens(string $model_id): int {
    // Return the model's max input token limit.
    return 4096;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxOutputTokens(string $model_id): int {
    // Return the model's max output token limit.
    return 4096;
  }

  /**
   * Loads the API client.
   */
  protected function loadClient(): void {
    if ($this->client) {
      return;
    }

    // Initialize your API client here.
    // $this->client = new YourApiClient($this->loadApiKey());
  }

}
```

### Pattern B: Multi-operation provider (chat + embeddings + more)

For providers supporting multiple operation types. Add interfaces and
implement their methods.

```php
<?php

declare(strict_types=1);

namespace Drupal\{module}\Plugin\AiProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;

/**
 * Provides the {Provider Label} AI provider.
 */
#[AiProvider(
  id: '{provider_id}',
  label: new TranslatableMarkup('{Provider Label}'),
)]
class {ClassName} extends AiProviderClientBase implements ChatInterface, EmbeddingsInterface {

  /**
   * The API client instance.
   *
   * @var object|null
   */
  protected $client = NULL;

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('{module}.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    $models = [];

    if (!$operation_type || $operation_type === 'chat') {
      $models['chat-model-1'] = 'Chat Model One';
    }

    if (!$operation_type || $operation_type === 'embeddings') {
      $models['embedding-model-1'] = 'Embedding Model One';
    }

    return $models;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }

    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'chat',
      'embeddings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();

    // Implement chat logic here.
    $response_text = 'Response from ' . $model_id;
    $message = new ChatMessage('assistant', $response_text);
    return new ChatOutput($message, $response_text, []);
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxInputTokens(string $model_id): int {
    return 4096;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxOutputTokens(string $model_id): int {
    return 4096;
  }

  /**
   * {@inheritdoc}
   */
  public function embeddings(string|EmbeddingsInput $input, string $model_id, array $tags = []): EmbeddingsOutput {
    $this->loadClient();

    // Implement embeddings logic here.
    // $vectors = $this->client->embeddings($input);

    $vector = array_fill(0, 1536, 0.0); // Placeholder vector.
    return new EmbeddingsOutput([$vector], $input, []);
  }

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput(string $model_id = ''): int {
    return 8191;
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    return 1536;
  }

  /**
   * Loads the API client.
   */
  protected function loadClient(): void {
    if ($this->client) {
      return;
    }
    // Initialize your API client here.
  }

}
```

### Pattern C: OpenAI-compatible provider (custom endpoint)

For providers using OpenAI's API format but with a different endpoint.
This pattern includes **model auto-discovery** — fetching available models
directly from the API rather than hardcoding them.

```php
<?php

declare(strict_types=1);

namespace Drupal\{module}\Plugin\AiProvider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\OpenAiBasedProviderClientBase;

/**
 * Provides the {Provider Label} AI provider.
 *
 * Uses OpenAI-compatible API at a custom endpoint.
 */
#[AiProvider(
  id: '{provider_id}',
  label: new TranslatableMarkup('{Provider Label}'),
)]
class {ClassName} extends OpenAiBasedProviderClientBase {

  /**
   * Models are not hardcoded — they are discovered from the API.
   *
   * Setting this to FALSE tells the AI module that models are added
   * dynamically via the admin UI or discovered at runtime, rather than
   * being returned by a fixed getConfiguredModels() list.
   *
   * {@inheritdoc}
   */
  protected bool $hasPredefinedModels = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function loadClient(): void {
    // Set custom endpoint if configured.
    $endpoint = $this->getConfig()->get('endpoint');
    if ($endpoint) {
      $this->setEndpoint($endpoint);
    }

    parent::loadClient();
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return ['chat', 'embeddings'];
  }

  /**
   * {@inheritdoc}
   *
   * Auto-discovers models from the API's /v1/models endpoint.
   *
   * This is the PREFERRED approach — it means users never need to
   * manually add or update models. When the provider adds new models,
   * they appear automatically.
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    try {
      $this->loadClient();
      $response = $this->client->models()->list();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('{module}')->error(
        'Failed to fetch models from API: @error',
        ['@error' => $e->getMessage()]
      );
      return [];
    }

    $models = [];
    foreach ($response->data as $model) {
      // Use the model ID as both key and label.
      // You can filter by operation type if the API provides metadata:
      //   if ($operation_type === 'embeddings' && !$model->supportsEmbeddings) continue;
      $models[$model->id] = $model->id;
    }

    return $models;
  }

}
```

### Model Auto-Discovery Guide

Model auto-discovery is the **preferred** approach for all provider
patterns. Instead of hardcoding a list of models, the provider queries
the API at runtime to discover what's available. This is important because:

- Users see new models immediately when they're released — no code update
  needed.
- Self-hosted providers (Ollama, vLLM, LM Studio, etc.) have entirely
  user-specific model lists that are impossible to predict.
- It eliminates configuration drift between what's in code and what's
  actually available.

**Implementation checklist for auto-discovery:**

1. **Set `$hasPredefinedModels = FALSE`** in the class property. This tells
   the AI module's admin UI that models are not hardcoded and enables the
   manual model management UI as fallback.

2. **Implement `getConfiguredModels()`** to call the provider's list/models
   API endpoint. Common endpoints:

   | Provider Type | Endpoint | SDK Method |
   |--------------|----------|------------|
   | OpenAI-compatible | `GET /v1/models` | `$this->client->models()->list()` |
   | Ollama | `GET /api/tags` | Custom HTTP call |
   | Custom API | Varies | Use `$this->httpClient` |

3. **Wrap in try/catch** — the API may be unreachable. Log the error and
   return an empty array so the site doesn't crash:
   ```php
   try {
     $response = $this->client->models()->list();
   }
   catch (\Exception $e) {
     $this->loggerFactory->get('{module}')->error(
       'Failed to fetch models: @error', ['@error' => $e->getMessage()]
     );
     return [];
   }
   ```

4. **Filter by `$operation_type`** when the parameter is provided — not all
   models support all operation types. If the API doesn't provide metadata
   about model capabilities, use naming conventions (e.g., models containing
   "embed" for embeddings) or return all models and let the user choose.

5. **Consider caching** — if the list-models API is slow or called
   frequently, cache the results using `$this->cacheBackend` (already
   available from the base class) to avoid hitting the API on every
   page load:
   ```php
   // Check cache first.
   $cached = $this->cacheBackend->get('{module}.models');
   if ($cached) {
     return $cached->data;
   }
   // ... fetch from API ...
   // Cache for 1 hour.
   $this->cacheBackend->set('{module}.models', $models, time() + 3600);
   ```

**For Patterns A and B** (non-OpenAI-compatible): make an HTTP request to the
provider's model listing endpoint using `$this->httpClient` and parse the
JSON response. The principle is the same — query the API, parse results,
return `[model_id => label]`.

### 2.3 Configuration Form

Create `src/Form/{ClassName}ConfigForm.php`:

```php
<?php

declare(strict_types=1);

namespace Drupal\{module}\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure {Provider Label} provider settings.
 */
final class {ClassName}ConfigForm extends ConfigFormBase {

  /**
   * Config settings name.
   */
  const CONFIG_NAME = '{module}.settings';

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * The form helper.
   *
   * @var \Drupal\ai\Service\AiProviderFormHelper
   */
  protected AiProviderFormHelper $formHelper;

  /**
   * {@inheritdoc}
   */
  final public function __construct(AiProviderPluginManager $ai_provider_manager, AiProviderFormHelper $form_helper) {
    $this->aiProviderManager = $ai_provider_manager;
    $this->formHelper = $form_helper;
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('ai.form_helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return '{module}_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [static::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::CONFIG_NAME);

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('Select the API key for {Provider Label}.'),
      '#default_value' => $config->get('api_key'),
    ];

    // Optional: Add endpoint configuration for custom deployments.
    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Endpoint'),
      '#description' => $this->t('Leave empty for the default endpoint.'),
      '#default_value' => $config->get('endpoint') ?? '',
    ];

    // Optional: Add models table for per-model configuration.
    // $provider = $this->aiProviderManager->createInstance('{provider_id}');
    // $form['models'] = $this->formHelper->getModelsTable($form, $form_state, $provider);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Optionally set as default provider for supported operation types.
    // $this->aiProviderManager->defaultIfNone('chat', '{provider_id}', 'model-1');

    $this->config(static::CONFIG_NAME)
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('endpoint', $form_state->getValue('endpoint'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
```

### 2.4 Routing File

Create `{module}.routing.yml`:

```yaml
{module}.config:
  path: '/admin/config/ai/providers/{provider_id}'
  defaults:
    _title: '{Provider Label} Configuration'
    _form: 'Drupal\{module}\Form\{ClassName}ConfigForm'
  requirements:
    _permission: 'administer ai providers'
```

### 2.5 Menu Links File

Create `{module}.links.menu.yml`:

```yaml
{module}.config:
  title: '{Provider Label}'
  description: 'Configure {Provider Label} AI provider.'
  route_name: {module}.config
  parent: ai.admin_providers
```

### 2.6 API Defaults Definition

Create `definitions/api_defaults.yml`:

```yaml
chat:
  input:
    description: 'Input provided to the model.'
    type: 'array'
    default:
      - { role: "system", content: "You are a helpful assistant." }
      - { role: "user", content: "Hello!" }
    required: true
  authentication:
    description: 'API Key.'
    type: 'string'
    default: ''
    required: true
  configuration:
    max_tokens:
      label: 'Max Tokens'
      description: 'Maximum number of tokens in the response.'
      type: 'integer'
      default: 1024
      required: false
    temperature:
      label: 'Temperature'
      description: 'Sampling temperature (0-2). Higher = more random.'
      type: 'float'
      default: 0.7
      required: false
      constraints:
        min: 0
        max: 2
        step: 0.1
```

### 2.7 Config Schema

Create `config/schema/{module}.schema.yml`:

```yaml
{module}.settings:
  type: config_object
  label: '{Provider Label} settings'
  mapping:
    api_key:
      type: string
      label: 'API Key'
    endpoint:
      type: string
      label: 'API Endpoint'
```

## Key Methods Reference

### AiProviderInterface (all providers must implement)

| Method | Purpose |
|--------|---------|
| `getConfiguredModels()` | Return array of model IDs => labels. Optionally filter by operation type. |
| `isUsable()` | Return `TRUE` if provider is configured and ready. Check for API key, etc. |
| `getSupportedOperationTypes()` | Return array of supported operation type strings. |
| `getSupportedCapabilities()` | Return array of supported capability enums. |
| `getAvailableConfiguration()` | Return config options for operation type + model. |
| `getDefaultConfigurationValues()` | Return default config values for operation type + model. |
| `setAuthentication()` | Set API credentials (called before operations). |
| `setConfiguration()` | Set runtime configuration. |
| `getConfiguration()` | Get current runtime configuration. |
| `loadModelsForm()` | Build custom per-model configuration form. |
| `validateModelsForm()` | Validate per-model configuration form. |
| `hasPredefinedModels()` | Return `TRUE` if models are hardcoded, `FALSE` if discovered dynamically. **Set `$hasPredefinedModels = FALSE` for auto-discovery providers** — this tells the admin UI to allow manual model management as a fallback while `getConfiguredModels()` handles auto-discovery. |
| `getSetupData()` | Return setup info for automated configuration (key paths, default models). |

### AiProviderClientBase (provided by base class)

| Method | Purpose |
|--------|---------|
| `getConfig()` | Get provider's ImmutableConfig object. |
| `getApiDefinition()` | Load `definitions/api_defaults.yml`. |
| `getModelSettings()` | Override to customize configuration per model. |
| `loadApiKey()` | Load API key from Key module. Throws `AiSetupFailureException` if missing. |
| `normalizeConfiguration()` | Merge default + runtime configuration. |
| `setTag()` / `getTags()` | Manage tags for event identification. |
| `setDebugData()` | Add extra debug info for logging/events. |
| `loadModelConfig()` | Load saved model configuration. |
| `getModelsConfig()` | Get all models configuration from `ai.settings`. |

### Operation Type Interfaces

| Interface | Method | Purpose |
|-----------|--------|---------|
| `ChatInterface` | `chat()` | Generate chat response. |
| `ChatInterface` | `getMaxInputTokens()` | Max input tokens for model. |
| `ChatInterface` | `getMaxOutputTokens()` | Max output tokens for model. |
| `EmbeddingsInterface` | `embeddings()` | Generate vector embeddings. |
| `EmbeddingsInterface` | `maxEmbeddingsInput()` | Max input length for embeddings. |
| `EmbeddingsInterface` | `embeddingsVectorSize()` | Embedding vector dimensions. |
| `TextToImageInterface` | `textToImage()` | Generate image from text. |
| `TextToSpeechInterface` | `textToSpeech()` | Generate speech from text. |
| `SpeechToTextInterface` | `speechToText()` | Transcribe speech to text. |
| `ModerationInterface` | `moderation()` | Check content for safety. |

## Operation Types

| Operation Type | Interface | Input Class | Output Class |
|---------------|-----------|-------------|--------------|
| `chat` | `ChatInterface` | `ChatInput` | `ChatOutput` |
| `embeddings` | `EmbeddingsInterface` | `EmbeddingsInput` | `EmbeddingsOutput` |
| `text_to_image` | `TextToImageInterface` | `TextToImageInput` | `TextToImageOutput` |
| `text_to_speech` | `TextToSpeechInterface` | `TextToSpeechInput` | `TextToSpeechOutput` |
| `speech_to_text` | `SpeechToTextInterface` | `SpeechToTextInput` | `SpeechToTextOutput` |
| `moderation` | `ModerationInterface` | `ModerationInput` | `ModerationOutput` |
| `image_to_image` | `ImageToImageInterface` | `ImageToImageInput` | `ImageToImageOutput` |
| `image_to_video` | `ImageToVideoInterface` | `ImageToVideoInput` | `ImageToVideoOutput` |

## Model Capabilities

Providers can declare capabilities per model using `AiModelCapability` enum:

| Capability | Description |
|------------|-------------|
| `ChatWithImageVision` | Model can analyze images in chat. |
| `ChatWithAudio` | Model can process audio in chat. |
| `ChatWithVideo` | Model can process video in chat. |
| `ChatSystemRole` | Model supports system role messages. |
| `ChatJsonOutput` | Model can output structured JSON. |
| `ChatStructuredResponse` | Model supports JSON schema validation. |
| `ChatTools` | Model supports function calling/tools. |
| `ChatCombinedToolsAndStructuredResponse` | Model supports both tools and structured output. |

## Attribute Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | `string` | Yes | Plugin ID. Should match module name or be descriptive. |
| `label` | `TranslatableMarkup` | Yes | Human-readable provider name. |
| `deriver` | `class-string` | No | Deriver class for dynamic plugin definitions. |

## Step 3: Generate a Kernel Test

Create the test at `tests/src/Kernel/Plugin/AiProvider/{ClassName}Test.php`.

**Pattern:**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\{module}\Kernel\Plugin\AiProvider;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the {ClassName} AI provider plugin.
 *
 * @group {module}
 * @covers \Drupal\{module}\Plugin\AiProvider\{ClassName}
 */
class {ClassName}Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'key',
    '{module}',
    'system',
  ];

  /**
   * Tests plugin discovery.
   */
  public function testPluginDiscovery(): void {
    $manager = $this->container->get('ai.provider');
    $definitions = $manager->getDefinitions();
    $this->assertArrayHasKey('{provider_id}', $definitions);
    $this->assertEquals('{Provider Label}', (string) $definitions['{provider_id}']['label']);
  }

  /**
   * Tests plugin instantiation.
   */
  public function testPluginInstantiation(): void {
    $manager = $this->container->get('ai.provider');
    $instance = $manager->createInstance('{provider_id}');
    $this->assertEquals('{provider_id}', $instance->getPluginId());
  }

  /**
   * Tests supported operation types.
   */
  public function testSupportedOperationTypes(): void {
    $manager = $this->container->get('ai.provider');
    $instance = $manager->createInstance('{provider_id}');
    $types = $instance->getSupportedOperationTypes();
    $this->assertIsArray($types);
    $this->assertContains('chat', $types);
  }

  /**
   * Tests isUsable returns false without API key.
   */
  public function testIsUsableWithoutApiKey(): void {
    $manager = $this->container->get('ai.provider');
    $instance = $manager->createInstance('{provider_id}');
    $this->assertFalse($instance->isUsable());
  }

}
```

## Common Mistakes — DO NOT REPEAT THESE

| Mistake | Why It's Wrong | Correct Action |
|---------|---------------|----------------|
| Skipping Step 0 and jumping to code generation | Produces wrong base class and architecture that must be rewritten | Always ask the user before writing any code |
| Using `AiProviderClientBase` for an OpenAI-compatible provider (e.g. Ollama) | Wastes hundreds of lines reimplementing chat, embeddings, etc. that `OpenAiBasedProviderClientBase` provides for free | Research API compatibility first, recommend Pattern C |
| Assuming the provider type instead of asking | User may want a different approach than what you assume | Ask explicitly and wait for their answer |
| Writing raw HTTP calls when an SDK-based base class exists | Error-prone, misses streaming, tool calling, structured output, etc. | Use `OpenAiBasedProviderClientBase` which wraps the OpenAI PHP SDK |
| Not researching the target API before asking questions | You can't make a good recommendation without knowing the API | Check docs for OpenAI compatibility BEFORE asking the user |
| Hardcoding model lists when the API has a list-models endpoint | Models go stale, self-hosted providers have unpredictable models | Use auto-discovery (`$client->models()->list()` or equivalent), set `$hasPredefinedModels = FALSE` |

## Critical Rules

1. **ALWAYS complete Step 0 first** — Ask the user which provider type they
   need. Research OpenAI compatibility. Wait for their answer. This is
   non-negotiable.

2. **Always extend `AiProviderClientBase`** — or `OpenAiBasedProviderClientBase`
   for OpenAI-compatible APIs. Never implement `AiProviderInterface` directly.
   **If the target provider is determined to be OpenAI-compatible** (through
   research or user confirmation), **you MUST use
   `OpenAiBasedProviderClientBase`** (Pattern C) unless the user explicitly
   requests the native API approach.

3. **Implement all interfaces for supported operation types** — if you support
   `chat`, implement `ChatInterface`. If you support `embeddings`, implement
   `EmbeddingsInterface`. Each interface has required methods.

3. **`getSupportedOperationTypes()` must match implemented interfaces** — only
   return operation types you've actually implemented.

4. **`isUsable()` must check for API key** — return `FALSE` if the provider
   isn't configured. This prevents errors when users haven't set up the provider.

5. **`getConfiguredModels()` must return valid models** — return an array of
   `model_id => label`. Filter by operation type if the parameter is provided.

6. **Prefer model auto-discovery over hardcoded lists** — if the provider's
   API has a "list models" endpoint (e.g., `/v1/models`, `/api/tags`), use
   it in `getConfiguredModels()` to fetch models dynamically. This ensures
   users automatically see new models as they become available without
   code changes. Set `protected bool $hasPredefinedModels = FALSE;` when
   models are discovered dynamically rather than hardcoded. Wrap the API
   call in a try/catch and log errors gracefully — return an empty array
   if the API is unreachable so the site doesn't break.

6. **Use the Key module for API keys** — the AI module integrates with the Key
   module for secure credential storage. Use `loadApiKey()` to retrieve keys.

7. **Throw appropriate AI exceptions on API errors** — wrap API errors in
   the correct exception type for consistent error handling:
   - `AiResponseErrorException` — for unexpected or malformed API responses.
   - `AiRateLimitException` — when the API returns rate limit errors
     (e.g. HTTP 429, "Too Many Requests", "Request too large").
   - `AiQuotaException` — when the API returns quota/credit exhaustion
     errors (e.g. "You exceeded your current quota").
   - `AiRequestErrorException` — for general request failures.
   See `OpenAiBasedProviderClientBase::handleApiException()` for a
   reference implementation of exception mapping.

8. **Create the configuration form** — users need a way to configure the API
   key and other settings via the admin UI.

9. **Add routing and menu links** — the configuration form needs to be
   accessible from the AI providers admin page.

10. **Run `drush cr` after creating a provider** — Drupal caches plugin
    discovery. Clear cache to see your new provider.

11. **Run Kernel tests after generation** — If PHPUnit is available (check
    with `which phpunit` or `vendor/bin/phpunit --version`), run the
    generated kernel test to verify plugin discovery and basic instantiation:
    ```
    vendor/bin/phpunit web/modules/custom/{module}/tests/src/Kernel/
    ```
    If tests fail, fix the issues before considering the task complete.

## Summary of Generated Files

| File | Purpose |
|------|---------|
| `{module}.info.yml` | Module definition |
| `{module}.routing.yml` | Routes for configuration form |
| `{module}.links.menu.yml` | Admin menu link |
| `src/Plugin/AiProvider/{ClassName}.php` | Provider plugin class |
| `src/Form/{ClassName}ConfigForm.php` | Configuration form |
| `definitions/api_defaults.yml` | API configuration defaults |
| `config/schema/{module}.schema.yml` | Config schema |
| `tests/src/Kernel/Plugin/AiProvider/{ClassName}Test.php` | Kernel test |`

**IMPORTANT:** When you have decided which base class to use, **read the
corresponding source file** (`AiProviderClientBase.php` or
`OpenAiBasedProviderClientBase.php`) to understand
the full API surface, default implementations, and patterns you should follow.
