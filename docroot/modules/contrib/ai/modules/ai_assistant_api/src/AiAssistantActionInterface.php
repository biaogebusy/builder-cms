<?php

namespace Drupal\ai_assistant_api;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines an interface for AI assistant actions.
 */
interface AiAssistantActionInterface extends PluginFormInterface, ConfigurableInterface {

  /**
   * Sets the assistant.
   *
   * @param \Drupal\ai_assistant_api\AiAssistantInterface $assistant
   *   The assistant.
   */
  public function setAssistant(AiAssistantInterface $assistant): void;

  /**
   * Sets the ai provider.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $ai_provider
   *   The ai provider.
   */
  public function setAiProvider($ai_provider): void;

  /**
   * Sets the threads id.
   *
   * @param string $thread_id
   *   The thread id.
   */
  public function setThreadId(string $thread_id): void;

  /**
   * Sets the messages.
   *
   * @param array $messages
   *   The messages.
   */
  public function setMessages(array $messages): void;

  /**
   * Returns the list of actions.
   *
   * @return array
   *   An associative array with action id, label and description.
   */
  public function listActions(): array;

  /**
   * List of contexts to give back.
   *
   * @return array
   *   These are lists of contexts that you can give back to the assistant.
   */
  public function listContexts(): array;

  /**
   * List of usage instructions to give back.
   *
   * This is used to give back a list of usage instructions to the assistant
   * so that it can be provided in the system prompt for best usages.
   *
   * @return array
   *   List of usage instructions.
   */
  public function listUsageInstructions(): array;

  /**
   * Triggers some action.
   *
   * @param string $action_id
   *   The action id.
   * @param array $parameters
   *   The parameters.
   */
  public function triggerAction(string $action_id, array $parameters = []): void;

  /**
   * Provide a few shot learning example.
   *
   * This is used to provide a few shot learning example to the AI on how to
   * trigger this action. It should give back one or more examples in an
   * array and the AI will learn from this.
   *
   * @return array
   *   An array of examples.
   */
  public function provideFewShotLearningExample(): array;

  /**
   * Triggers a rollback.
   *
   * This is used to trigger a rollback of the action, for actions that actually
   * change the state of the system.
   */
  public function triggerRollback(): void;

  /**
   * Get function call schema.
   *
   * @return array
   *   The function call schema.
   */
  public function getFunctionCallSchema(): array;

}
