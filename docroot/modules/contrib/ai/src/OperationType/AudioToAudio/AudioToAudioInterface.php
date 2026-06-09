<?php

namespace Drupal\ai\OperationType\AudioToAudio;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for audio to audio models.
 */
#[OperationType(
  id: 'audio_to_audio',
  label: new TranslatableMarkup('Audio to Audio'),
  description: new TranslatableMarkup('Transform audio by changing the voice, improving quality, or applying other modifications.')
)]
interface AudioToAudioInterface extends OperationTypeInterface {

  /**
   * Generate audio from audio.
   *
   * @param string|array|\Drupal\ai\Operation\AudioToAudio\AudioToAudioInput $input
   *   The audio to generate audio from or a binary.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\AudioToAudio\AudioToAudioOutput
   *   The audio output object.
   */
  public function audioToAudio(string|array|AudioToAudioInput $input, string $model_id, array $tags = []): AudioToAudioOutput;

}
