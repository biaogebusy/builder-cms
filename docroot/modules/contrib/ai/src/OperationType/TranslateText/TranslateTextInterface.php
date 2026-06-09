<?php

namespace Drupal\ai\OperationType\TranslateText;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for text translation models.
 */
#[OperationType(
  id: 'translate_text',
  label: new TranslatableMarkup('Translate Text'),
)]
interface TranslateTextInterface extends OperationTypeInterface {

  /**
   * Translate the text.
   *
   * @param \Drupal\ai\OperationType\TranslateText\TranslateTextInput $input
   *   The text to to translate.
   * @param string $model_id
   *   The model id to use.
   * @param array $options
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\TranslateText\TranslateTextOutput
   *   The translation output.
   */
  public function translateText(TranslateTextInput $input, string $model_id, array $options = []): TranslateTextOutput;

}
