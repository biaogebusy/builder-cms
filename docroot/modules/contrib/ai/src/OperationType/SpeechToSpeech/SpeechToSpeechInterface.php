<?php

namespace Drupal\ai\OperationType\SpeechToSpeech;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for speech to speech models.
 */
#[OperationType(
  id: 'speech_to_speech',
  label: new TranslatableMarkup('Speech to Speech'),
  description: new TranslatableMarkup('Transform spoken audio by translating, changing the voice, or modifying tone.')
)]
interface SpeechToSpeechInterface extends OperationTypeInterface {

  /**
   * Generate speech to speech.
   *
   * @param string|array|\Drupal\ai\Operation\SpeechToSpeech\SpeechToSpeechInput $input
   *   The audio to generate audio from or a binary.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechOutput
   *   The audio output object.
   */
  public function speechToSpeech(string|array|SpeechToSpeechInput $input, string $model_id, array $tags = []): SpeechToSpeechOutput;

}
