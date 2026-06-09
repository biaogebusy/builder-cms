<?php

namespace Drupal\ai\OperationType\Moderation;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for moderation models.
 */
#[OperationType(
  id: 'moderation',
  label: new TranslatableMarkup('Moderation'),
  description: new TranslatableMarkup('Detect and filter harmful or inappropriate content in text or images.')
)]
interface ModerationInterface extends OperationTypeInterface {

  /**
   * Generate moderation.
   *
   * @param string|\Drupal\ai\OperationType\Moderation\ModerationInput $input
   *   The prompt or the moderation input.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\Moderation\ModerationOutput
   *   The moderation output. True if its flagged.
   */
  public function moderation(string|ModerationInput $input, ?string $model_id = NULL, array $tags = []): ModerationOutput;

}
