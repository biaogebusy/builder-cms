<?php

namespace Drupal\ai\OperationType\TextToSpeech;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for text to speech models.
 */
#[OperationType(
  id: 'text_to_speech',
  label: new TranslatableMarkup('Text To Speech'),
  description: new TranslatableMarkup('Convert written text into spoken audio using synthetic voices.')
)]
interface TextToSpeechInterface extends OperationTypeInterface {

  /**
   * Generate audio from text.
   *
   * Do not use this method directly. Use the invokeModelResponse instead with
   * TextToSpeechOperation as the operation type to make sure logging and
   * event triggering is working correctly.
   *
   * @param string|\Drupal\ai\Operation\TextToSpeech\TextToSpeechInput $input
   *   The text to generate audio from or a Output.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\TextToSpeech\TextToSpeechOutput
   *   The output Output.
   */
  public function textToSpeech(string|TextToSpeechInput $input, string $model_id, array $tags = []): TextToSpeechOutput;

}
