<?php

namespace Drupal\ai\OperationType\ObjectDetection;

use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for object detection models.
 */
#[OperationType(
  id: 'object_detection',
  label: new TranslatableMarkup('Object detection'),
)]
interface ObjectDetectionInterface extends OperationTypeInterface {

  /**
   * Detect objects in the image.
   *
   * @param string|array|\Drupal\ai\OperationType\ObjectDetection\ObjectDetectionInput $input
   *   The object detection input.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\ObjectDetection\ObjectDetectionOutput
   *   The object detection output.
   */
  public function objectDetection(string|array|ObjectDetectionInput $input, string $model_id, array $tags = []): ObjectDetectionOutput;

}
