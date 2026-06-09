<?php

declare(strict_types=1);

namespace Drupal\ai\OperationType\Rerank;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for the ReRank plugin.
 */
#[OperationType(
  id: 'rerank',
  label: new TranslatableMarkup('Rerank'),
)]
interface ReRankInterface extends OperationTypeInterface {

  /**
   * Rerank a list of documents.
   *
   * @param \Drupal\ai\OperationType\Rerank\ReRankInput $input
   *   The rerank input.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\Rerank\ReRankOutput
   *   The response.
   */
  public function rerank(ReRankInput $input, string $model_id, array $tags = []): ReRankOutput;

}
