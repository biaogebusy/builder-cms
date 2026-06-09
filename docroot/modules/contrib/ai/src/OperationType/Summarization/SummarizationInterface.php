<?php

namespace Drupal\ai\OperationType\Summarization;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for summarize models.
 *
 * Summarization takes a long text and produces a shorter version.
 */
#[OperationType(
  id: 'summarize',
  label: new TranslatableMarkup('Summarize'),
)]
interface SummarizationInterface extends OperationTypeInterface {

  /**
   * Summarize text.
   *
   * @param string|array|\Drupal\ai\OperationType\Summarization\SummarizationInput $input
   *   The text to summarize or the summarize input object.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\Summarization\SummarizationOutput
   *   The summarize output.
   */
  public function summarize(string|array|SummarizationInput $input, string $model_id, array $tags = []): SummarizationOutput;

}
