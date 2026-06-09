<?php

namespace Drupal\ai_test\OperationType\Echo;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for text translation models.
 */
#[OperationType(
  id: 'echo',
  label: new TranslatableMarkup('Echo'),
)]
interface EchoInterface extends OperationTypeInterface {

  /**
   * Translate the text.
   *
   * @param \Drupal\ai_test\OperationType\Echo\EchoInput|string $input
   *   The input to echo.
   * @param string $model_id
   *   The model id to use.
   * @param array $options
   *   Extra tags to set.
   *
   * @return \Drupal\ai_test\OperationType\Echo\EchoOutput
   *   The translation output.
   */
  public function echo(string|EchoInput $input, string $model_id, array $options = []): EchoOutput;

}
