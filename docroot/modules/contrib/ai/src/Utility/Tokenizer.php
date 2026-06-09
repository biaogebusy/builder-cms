<?php

namespace Drupal\ai\Utility;

use Drupal\ai\AiProviderPluginManager;
use Yethee\Tiktoken\Encoder;
use Yethee\Tiktoken\EncoderProvider;

/**
 * The tokenizer class wrapper for Tik-Token PHP.
 */
class Tokenizer implements TokenizerInterface {

  /**
   * The encoder.
   *
   * @var \Yethee\Tiktoken\Encoder
   */
  protected Encoder $encoder;

  /**
   * The encoder provider.
   *
   * @var \Yethee\Tiktoken\EncoderProvider
   */
  protected EncoderProvider $encoderProvider;

  /**
   * Construct the tokenizer wrapper for Tik-Token PHP.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI provider plugin manager.
   */
  public function __construct(
    protected AiProviderPluginManager $aiProvider,
  ) {
    $this->encoderProvider = new EncoderProvider();
  }

  /**
   * {@inheritdoc}
   */
  public function setModel(string $model): void {
    try {
      $this->encoder = $this->encoderProvider->getForModel($model);
    }
    catch (\Exception $e) {
      // Fallback to the same encoding used by gpt3.5-turbo as a sensible
      // default when the model is not yet supported by TikToken PHP.
      // @see https://github.com/yethee/tiktoken-php/blob/master/src/EncoderProvider.php
      $this->encoder = $this->encoderProvider->get('cl100k_base');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedModels(): array {
    $model_options = $this->aiProvider->getSimpleProviderModelOptions('chat');
    foreach ($model_options as $key => $option) {
      $option_model_id = $this->aiProvider->getModelNameFromSimpleOption($option);
      if (!$option_model_id) {
        continue;
      }

      // Tik-Token throws an exception if the model is not matching an
      // available model, or matching the prefix of an available model.
      try {
        $this->encoderProvider->getForModel($option_model_id);
      }
      catch (\InvalidArgumentException $exception) {
        unset($model_options[$key]);
      }
    }
    return $model_options;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   *   Thrown when the model has not yet been set.
   */
  public function getTokens(string $chunk): array {
    if (!$this->encoder) {
      throw new \Exception('Tokenizer model not yet set.');
    }
    return $this->encoder->encode($chunk);
  }

  /**
   * Get text broken in chunks.
   *
   * This method is specific to Tik-Token as not every encoder will have a
   * chunking option. Therefore, the text chunker used should consider this.
   *
   * @param string $text
   *   The text to be chunked.
   * @param int $maxSize
   *   The maximum chunk size.
   *
   * @return array
   *   Text broken into chunks and encoded.
   */
  public function getEncodedChunks(string $text, int $maxSize): array {
    return $this->encoder->encodeInChunks($text, $maxSize);
  }

  /**
   * Decode a chunk back to the original text.
   *
   * @param int[] $encoded_chunk
   *   The encoded chunk.
   *
   * @return string
   *   The text content.
   */
  public function decodeChunk(array $encoded_chunk): string {
    return $this->encoder->decode($encoded_chunk);
  }

  /**
   * {@inheritdoc}
   */
  public function countTokens(string $chunk): int {
    return count($this->getTokens($chunk));
  }

}
