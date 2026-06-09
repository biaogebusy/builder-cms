# Text Chunker Service

## What is it?

The `ai.text_chunker` service splits text into smaller chunks based on token count, with configurable overlap between consecutive chunks. It is designed for NLP workflows where documents need to be broken into manageable pieces — for example, splitting content into chunks for embedding and vector storage in retrieval-augmented generation (RAG) pipelines.

The service works with the [`ai.tokenizer`](#relationship-with-the-tokenizer-service) service to ensure chunk boundaries respect token limits rather than simple character counts, producing more accurate splits for language model consumption.

## Why use overlap?

When text is split into chunks, important context can be lost at chunk boundaries. A sentence that spans two chunks may lose its meaning in both. Overlap solves this by repeating a portion of the preceding chunk at the start of the next one.

This is especially important for:

- **RAG / semantic search** — Overlapping chunks increase the chance that a relevant passage appears fully within at least one chunk, improving retrieval accuracy.
- **Embedding quality** — Embeddings capture meaning better when chunks contain complete thoughts rather than truncated fragments.

For example, with `maxSize = 500` and `minOverlap = 50`, each chunk will contain up to 500 tokens, and consecutive chunks will share approximately 50 tokens of context at their boundaries.

## Methods

### `chunkText(string $text, int $maxSize, int $minOverlap): array`

Splits the input text into an array of string chunks.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$text` | `string` | The text to split into chunks. |
| `$maxSize` | `int` | The maximum number of tokens per chunk. |
| `$minOverlap` | `int` | The minimum number of tokens to overlap between consecutive chunks. Must be less than `$maxSize`. |

**Returns:** `string[]` — An array of text chunks.

**Exceptions:**

- Throws `\Exception` if `$minOverlap` is greater than or equal to `$maxSize`.

### `setModel(string $model): void`

Sets the model used for tokenization. Different models use different tokenization schemes (e.g., GPT-4 uses cl100k_base), so setting the correct model ensures accurate token counts.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$model` | `string` | The model identifier (e.g., `gpt-4`, `claude-3-opus`). |

## Usage example

Inject the `ai.text_chunker` service and use it to split a document into chunks suitable for embedding:

```php
<?php

namespace Drupal\my_module\Service;

use Drupal\ai\Utility\TextChunkerInterface;

class DocumentProcessor {

  public function __construct(
    protected TextChunkerInterface $textChunker,
  ) {}

  /**
   * Split a document into chunks for embedding.
   *
   * @param string $text
   *   The document text.
   *
   * @return string[]
   *   The text chunks.
   */
  public function chunkDocument(string $text): array {
    // Set the model so the tokenizer uses the correct encoding.
    $this->textChunker->setModel('gpt-4');

    // Split into chunks of up to 500 tokens with 50-token overlap.
    return $this->textChunker->chunkText($text, 500, 50);
  }

}
```

Register the service in your module's `my_module.services.yml`:

```yaml
services:
  my_module.document_processor:
    class: Drupal\my_module\Service\DocumentProcessor
    arguments: ['@ai.text_chunker']
```

## Relationship with the Tokenizer service

The Text Chunker depends on the `ai.tokenizer` service for all token-related operations. The tokenizer handles encoding text into tokens, counting tokens, and decoding token arrays back into text. When you call `setModel()` on the Text Chunker, it delegates to the tokenizer so that token boundaries are calculated using the correct model-specific encoding.

You do not need to interact with the tokenizer directly when using the Text Chunker — it is injected automatically.
