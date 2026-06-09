# Response Helper Services

The AI module provides two helper services for extracting code blocks and JSON/YAML from naturally flowing AI responses — that is, responses where the desired content is embedded in surrounding prose or explanation. Both services accept either a `ChatMessage` (regular response) or a `StreamedChatMessageIteratorInterface` (streaming response) and return the extracted content, or the original payload if extraction fails.

!!! tip "Prefer structured output for JSON extraction"
    If you need the AI to return pure JSON, the best practice is to use [structured output](call_chat.md#structured-output) so the response is already valid JSON with no surrounding text. The services documented here are for cases where the response is **unstructured** — free-form text that happens to contain JSON or code blocks that need to be extracted.

## PromptJsonDecoder

**Service ID:** `ai.prompt_json_decode`

Decodes JSON from AI chat messages. The service automatically detects JSON content in the response text, including JSON wrapped in markdown code blocks (`` ```json ... ``` ``). This is useful when the AI returns JSON embedded in natural language (e.g., an explanation followed by a JSON object). It is **not** needed when using [structured output](call_chat.md#structured-output), which already guarantees a clean JSON response.

### How it works

1. For **regular responses** (`ChatMessage`): extracts and decodes JSON directly from the message text.
2. For **streaming responses** (`StreamedChatMessageIteratorInterface`): tests the first chunks of the stream to detect whether the response contains JSON. If JSON is detected, the full stream is consumed and decoded. If not, a replayable stream is returned so downstream code can still iterate over the response.

The decoder uses a recursive regex to find JSON objects (`{...}`) and arrays (`[{...}]`) anywhere in the response. It also supports extracting JSON from `` ```json ``` `` code blocks as a fallback.

### Usage

#### With dependency injection (recommended)

```php
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;

class MyService {

  public function __construct(
    protected PromptJsonDecoderInterface $promptJsonDecoder,
  ) {}

  public function processResponse(ChatMessage $message): void {
    $result = $this->promptJsonDecoder->decode($message);

    if (is_array($result)) {
      // Successfully decoded JSON data.
      $name = $result['name'];
    }
    else {
      // Not JSON — $result is the original ChatMessage.
      $text = $result->getText();
    }
  }

}
```

#### With a streaming response

```php
/** @var \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $stream */
$result = $this->promptJsonDecoder->decode($stream);

if (is_array($result)) {
  // JSON was detected and decoded from the stream.
  $data = $result;
}
else {
  // Not JSON — $result is a replayable stream iterator.
  foreach ($result as $chunk) {
    echo $chunk->getText();
  }
}
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$payload` | `ChatMessage` \| `StreamedChatMessageIteratorInterface` | — | The AI response to decode |
| `$chunks_to_test` | `int` | `10` | Number of streaming chunks to inspect before deciding the response is not JSON |

### Return value

- `array` — if JSON was successfully decoded.
- `ChatMessage` — the original message, if the input was a `ChatMessage` and no JSON was found.
- `StreamedChatMessageIteratorInterface` — a replayable stream, if the input was a streaming response and no JSON was found.

## PromptCodeBlockExtractor

**Service ID:** `ai.prompt_code_block_extractor`

Extracts code blocks from AI responses. When an AI model returns code wrapped in markdown fences (e.g., `` ```html ... ``` ``), this service extracts just the code content. It supports multiple code block types and also uses fallback patterns for HTML/Twig content.

### Supported code block types

| Type | Matches |
|------|---------|
| `html` | `` ```html ``` ``, `<html>...</html>`, or any top-level HTML tags |
| `twig` | `` ```twig ``` ``, `<html>...</html>`, or any top-level HTML tags |
| `yaml` | `` ```yaml ``` `` |
| `json` | `` ```json ``` `` |
| `css` | `` ```css ``` `` |

For `html` and `twig` types, the extractor tries multiple patterns in order: first the markdown code fence, then a `<html>` wrapper, and finally any outermost HTML tag pair.

### Usage

#### With dependency injection (recommended)

```php
use Drupal\ai\Service\PromptCodeBlockExtractor\PromptCodeBlockExtractorInterface;

class MyService {

  public function __construct(
    protected PromptCodeBlockExtractorInterface $codeBlockExtractor,
  ) {}

  public function getHtmlFromResponse(ChatMessage $message): void {
    $result = $this->codeBlockExtractor->extract($message, 'html');

    if (is_string($result)) {
      // Successfully extracted the HTML code block.
      $html = $result;
    }
    else {
      // No code block found — $result is the original ChatMessage.
      $text = $result->getText();
    }
  }

}
```

#### Extracting different code block types

```php
// Extract YAML from a response.
$yaml = $this->codeBlockExtractor->extract($message, 'yaml');

// Extract CSS from a response.
$css = $this->codeBlockExtractor->extract($message, 'css');

// Extract from a plain string (also supported).
$html = $this->codeBlockExtractor->extract($rawText, 'html');
```

#### With a streaming response

```php
/** @var \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $stream */
$result = $this->codeBlockExtractor->extract($stream, 'twig');

if (is_string($result)) {
  // Code block extracted from the consumed stream.
  $twigTemplate = $result;
}
else {
  // No code block found — $result is a replayable stream iterator.
  foreach ($result as $chunk) {
    echo $chunk->getText();
  }
}
```

!!! note
    Calling `extract()` on a streaming response will consume the entire stream. The response is streamed only in the abstraction sense — for practical purposes it is fully buffered.

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$payload` | `string` \| `ChatMessage` \| `StreamedChatMessageIteratorInterface` | — | The AI response (or raw string) to extract from |
| `$code_block_type` | `string` | `'html'` | The type of code block to extract (`html`, `twig`, `yaml`, `json`, `css`) |

### Return value

- `string` — the extracted code block content.
- `ChatMessage` — the original message, if the input was a `ChatMessage` and no code block was found.
- `StreamedChatMessageIteratorInterface` — a replayable stream, if the input was a streaming response and no code block was found.

## Using both services together

A common pattern is to use both services when processing AI output that may contain either JSON or code:

```php
use Drupal\ai\Service\PromptCodeBlockExtractor\PromptCodeBlockExtractorInterface;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;

class AiResponseProcessor {

  public function __construct(
    protected PromptJsonDecoderInterface $jsonDecoder,
    protected PromptCodeBlockExtractorInterface $codeBlockExtractor,
  ) {}

  public function process(ChatMessage $message): void {
    // First, try to decode JSON.
    $result = $this->jsonDecoder->decode($message);
    if (is_array($result)) {
      $this->handleStructuredData($result);
      return;
    }

    // If not JSON, try to extract an HTML code block.
    $html = $this->codeBlockExtractor->extract($message, 'html');
    if (is_string($html)) {
      $this->handleHtml($html);
      return;
    }

    // Plain text response.
    $this->handleText($message->getText());
  }

}
```

## Source code

- [PromptJsonDecoderInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.3.x/src/Service/PromptJsonDecoder/PromptJsonDecoderInterface.php)
- [PromptJsonDecoder.php](https://git.drupalcode.org/project/ai/-/blob/1.3.x/src/Service/PromptJsonDecoder/PromptJsonDecoder.php)
- [PromptCodeBlockExtractorInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.3.x/src/Service/PromptCodeBlockExtractor/PromptCodeBlockExtractorInterface.php)
- [PromptCodeBlockExtractor.php](https://git.drupalcode.org/project/ai/-/blob/1.3.x/src/Service/PromptCodeBlockExtractor/PromptCodeBlockExtractor.php)
