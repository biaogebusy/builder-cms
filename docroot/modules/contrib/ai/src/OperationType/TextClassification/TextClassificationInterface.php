<?php

namespace Drupal\ai\OperationType\TextClassification;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for text classification models.
 */
#[OperationType(
  id: 'text_classification',
  label: new TranslatableMarkup('Text Classification'),
)]
interface TextClassificationInterface extends OperationTypeInterface {

  /**
   * Classify text.
   *
   * @param string|\Drupal\ai\OperationType\TextClassification\TextClassificationInput $input
   *   The text classification input.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\TextClassification\TextClassificationOutput
   *   The text classification output.
   */
  public function textClassification(string|TextClassificationInput $input, string $model_id, array $tags = []): TextClassificationOutput;

}
