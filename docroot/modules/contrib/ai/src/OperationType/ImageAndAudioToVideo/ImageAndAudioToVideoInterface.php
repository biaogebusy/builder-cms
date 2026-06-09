<?php

namespace Drupal\ai\OperationType\ImageAndAudioToVideo;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for image and audio to video models.
 */
#[OperationType(
  id: 'image_and_audio_to_video',
  label: new TranslatableMarkup('Image and Audio to Video'),
  description: new TranslatableMarkup('Combine an image and audio track to create a video, such as animating a photo with voiceover.')
)]
interface ImageAndAudioToVideoInterface extends OperationTypeInterface {

  /**
   * Generate video from image and audio.
   *
   * @param string|array|\Drupal\ai\Operation\ImageAndAudioToVideo\ImageAndAudioToVideoInput $input
   *   The image to generate video from or a binary.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\ImageAndAudioToVideo\ImageAndAudioToVideoOutput
   *   The video output object.
   */
  public function imageAndAudioToVideo(string|array|ImageAndAudioToVideoInput $input, string $model_id, array $tags = []): ImageAndAudioToVideoOutput;

}
