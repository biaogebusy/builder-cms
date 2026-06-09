<?php

namespace Drupal\ai\OperationType\ImageClassification;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for image classification models.
 */
#[OperationType(
  id: 'image_classification',
  label: new TranslatableMarkup('Image Classification'),
  description: new TranslatableMarkup('Automatically identify and categorize image content. Useful for tagging media libraries or organizing uploads.')
)]
interface ImageClassificationInterface extends OperationTypeInterface {

  /**
   * Classify an image.
   *
   * @param string|array|\Drupal\ai\OperationType\ImageClassification\ImageClassificationInput $input
   *   The image classification input.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\ImageClassification\ImageClassificationOutput
   *   The image classification output.
   */
  public function imageClassification(string|array|ImageClassificationInput $input, string $model_id, array $tags = []): ImageClassificationOutput;

}
