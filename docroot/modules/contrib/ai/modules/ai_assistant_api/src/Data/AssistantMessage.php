<?php

namespace Drupal\ai_assistant_api\Data;

use Drupal\ai\OperationType\Chat\ChatOutput;

/**
 * Input object for assistants output.
 */
class AssistantMessage {

  /**
   * The message to convert to text.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatOutput
   */
  private ChatOutput $message;

  /**
   * The constructor.
   *
   * @param string $message
   *   The messages to send to the Assistant.
   */
  public function __construct(string $message) {
    $this->message = $message;
  }

  /**
   * Get the messages to the assistant.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The message.
   */
  public function getMessage(): ChatOutput {
    return $this->message;
  }

  /**
   * Set the message to the assistant.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatOutput $message
   *   The message.
   */
  public function setMessage(ChatOutput $message) {
    $this->message = $message;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->message->getNormalized()->getText();
  }

}
