<?php

namespace Drupal\ai_assistant_api\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Changes to the assistant system role.
 */
class AiAssistantSystemRoleEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai_assistant.change_assistant_message';

  /**
   * The system prompt.
   *
   * @var string
   */
  protected $systemPrompt;

  /**
   * Constructs the object.
   *
   * @param string $system_prompt
   *   The system prompt.
   */
  public function __construct(string $system_prompt) {
    $this->systemPrompt = $system_prompt;
  }

  /**
   * Gets the system prompt.
   *
   * @return string
   *   The system prompt.
   */
  public function getSystemPrompt() {
    return $this->systemPrompt;
  }

  /**
   * Set the system prompt.
   *
   * @param string $system_prompt
   *   The system prompt.
   */
  public function setSystemPrompt(string $system_prompt) {
    $this->systemPrompt = $system_prompt;
  }

}
