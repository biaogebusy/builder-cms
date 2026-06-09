<?php

namespace Drupal\ai\OperationType\SpeechToText;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for text to speech models.
 */
#[OperationType(
  id: 'speech_to_text',
  label: new TranslatableMarkup('Speech To Text'),
)]
interface SpeechToTextInterface extends OperationTypeInterface {

  /**
   * Generate text from speech.
   *
   * @param string|\Drupal\ai\Operation\SpeechToText\SpeechToTextInput $input
   *   The text to generate audio from or a Output.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\SpeechToText\SpeechToTextOutput
   *   The output Output.
   */
  public function speechToText(string|SpeechToTextInput $input, string $model_id, array $tags = []): SpeechToTextOutput;

}
