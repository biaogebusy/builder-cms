<?php

namespace Drupal\ai_assistant_api\Data;

/**
 * Input object for assistants input.
 */
class UserMessage {

  /**
   * The message to convert to text.
   *
   * @var string
   */
  private string $message;

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
   * @return string
   *   The message.
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * Set the message to the assistant.
   *
   * @param string $message
   *   The message.
   */
  public function setMessage(string $message) {
    $this->message = $message;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->message;
  }

}
