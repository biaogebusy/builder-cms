# Tokenizer Service

The `ai.tokenizer` service provides a wrapper around the [Tik-Token PHP](https://github.com/yethee/tiktoken-php) library for tokenizing text using the same tokenization schemes as OpenAI models. Use it when you need to count tokens, enforce token limits, calculate costs, or chunk text for embeddings.

The service is defined in `ai.services.yml` and implements `Drupal\ai\Utility\TokenizerInterface`.

## Injecting the Service

Use dependency injection to access the tokenizer in your services or plugins:

```php
use Drupal\ai\Utility\TokenizerInterface;

class MyService {

  public function __construct(
    protected TokenizerInterface $tokenizer,
  ) {}

}
```

In your `*.services.yml`:

```yaml
my_module.my_service:
  class: Drupal\my_module\MyService
  arguments: ['@ai.tokenizer']
```

## Setting a Model

Before calling any tokenization method, set the model so the tokenizer knows which encoding scheme to use:

```php
$this->tokenizer->setModel('gpt-4');
```

### Fallback Behavior

If the model is not supported by Tik-Token PHP, the tokenizer silently falls back to the `cl100k_base` encoding (the same encoding used by GPT-3.5 Turbo and GPT-4). This means tokenization will still work, but token counts may not be perfectly accurate for unsupported models.

## Methods

### `setModel(string $model): void`

Sets the tokenization model. Must be called before using any other method.

```php
$this->tokenizer->setModel('gpt-4');
```

### `countTokens(string $chunk): int`

Returns the number of tokens in a string.

```php
$this->tokenizer->setModel('gpt-4');
$count = $this->tokenizer->countTokens('Hello, world!');
// $count is an integer, e.g. 4
```

### `getTokens(string $chunk): array`

Returns the encoded token array (list of integers) for a string. Throws an exception if `setModel()` has not been called.

```php
$this->tokenizer->setModel('gpt-4');
$tokens = $this->tokenizer->getTokens('Hello, world!');
// $tokens is an array of integers, e.g. [9906, 11, 1917, 0]
```

### `getEncodedChunks(string $text, int $maxSize): array`

Splits text into chunks where each chunk contains at most `$maxSize` tokens, returned as arrays of token integers. This is useful for splitting long texts to fit within model context windows or for batch processing.

```php
$this->tokenizer->setModel('gpt-4');
$chunks = $this->tokenizer->getEncodedChunks($longText, 1000);
// $chunks is an array of token arrays, each with at most 1000 tokens.
```

### `decodeChunk(array $encoded_chunk): string`

Decodes a token array back into text. Use this to convert chunks from `getEncodedChunks()` back to readable strings.

```php
$this->tokenizer->setModel('gpt-4');
$chunks = $this->tokenizer->getEncodedChunks($longText, 1000);
foreach ($chunks as $chunk) {
  $text = $this->tokenizer->decodeChunk($chunk);
  // Process each text chunk.
}
```

### `getSupportedModels(): array`

Returns the list of chat model options (from configured AI providers) that Tik-Token PHP supports. Models that do not match a known tokenization scheme are excluded.

```php
$supported = $this->tokenizer->getSupportedModels();
// Returns an array of model option labels keyed by provider__model_id.
```

## Usage Example

A complete example showing how to count tokens and chunk text within a service:

```php
namespace Drupal\my_module\Service;

use Drupal\ai\Utility\TokenizerInterface;

class TextProcessor {

  public function __construct(
    protected TokenizerInterface $tokenizer,
  ) {}

  /**
   * Splits text into chunks that fit within a token limit.
   *
   * @param string $text
   *   The text to split.
   * @param string $model
   *   The model to tokenize for (e.g. 'gpt-4').
   * @param int $maxTokens
   *   The maximum tokens per chunk.
   *
   * @return string[]
   *   An array of text chunks.
   */
  public function chunkText(string $text, string $model, int $maxTokens): array {
    $this->tokenizer->setModel($model);

    $totalTokens = $this->tokenizer->countTokens($text);
    if ($totalTokens <= $maxTokens) {
      return [$text];
    }

    $encodedChunks = $this->tokenizer->getEncodedChunks($text, $maxTokens);
    $textChunks = [];
    foreach ($encodedChunks as $chunk) {
      $textChunks[] = $this->tokenizer->decodeChunk($chunk);
    }
    return $textChunks;
  }

}
```

## Related Services

The `ai.text_chunker` service uses the tokenizer internally to provide higher-level text chunking with overlap and context-aware splitting. If you need simple token-based operations, use `ai.tokenizer` directly. For more advanced document chunking strategies, see `ai.text_chunker`.
