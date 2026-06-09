<?php

namespace Drupal\ai\OperationType\ImageToVideo;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for image to video models.
 */
#[OperationType(
  id: 'image_to_video',
  label: new TranslatableMarkup('Image to Video'),
  description: new TranslatableMarkup('Animate a still image to produce a short video with movement or effects.')
)]
interface ImageToVideoInterface extends OperationTypeInterface {

  /**
   * Generate video from image.
   *
   * @param string|array|\Drupal\ai\Operation\ImageToVideo\ImageToVideoInput $input
   *   The image to generate video from or a binary.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\ImageToVideo\ImageToVideoOutput
   *   The video output object.
   */
  public function imageToVideo(string|array|ImageToVideoInput $input, string $model_id, array $tags = []): ImageToVideoOutput;

}
