<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for chat models.
 */
#[OperationType(
  id: 'chat',
  label: new TranslatableMarkup('Chat'),
  description: new TranslatableMarkup('Have back-and-forth conversations with AI using plain language.')
)]
interface ChatInterface extends OperationTypeInterface {

  /**
   * Generate chats.
   *
   * @param array|string|\Drupal\ai\Operation\Chat\ChatInput $input
   *   The chat array, string or ChatInput.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The output Output.
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput;

  /**
   * Gets the maximum input tokens for a model.
   *
   * @param string $model_id
   *   The model id.
   *
   * @return int
   *   The maximum input tokens.
   */
  public function getMaxInputTokens(string $model_id): int;

  /**
   * Gets the maximum output tokens for a model.
   *
   * @param string $model_id
   *   The model id.
   *
   * @return int
   *   The maximum output tokens.
   */
  public function getMaxOutputTokens(string $model_id): int;

}
