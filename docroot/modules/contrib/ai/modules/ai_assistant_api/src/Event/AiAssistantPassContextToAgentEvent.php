<?php

namespace Drupal\ai_assistant_api\Event;

use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Passes the context from AI assistant to agent.
 */
class AiAssistantPassContextToAgentEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai_assistant.pass_context_to_agent';

  /**
   * The agent to pass context to.
   *
   * @var \Drupal\ai_agents\PluginInterfaces\AiAgentInterface
   */
  protected $agent;

  /**
   * The context to pass.
   *
   * @var array
   */
  protected $context;

  /**
   * Constructs AiAssistantPassContextToAgentEvent.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\AiAgentInterface $agent
   *   The agent.
   * @param array $context
   *   The context to pass to agent.
   */
  public function __construct(AiAgentInterface $agent, array $context = []) {
    $this->agent = $agent;
    $this->context = $context;
  }

  /**
   * Get the context.
   *
   * @return array
   *   The context from assistant.
   */
  public function getContext(): array {
    return $this->context;
  }

  /**
   * Sets the context.
   *
   * @param array $context
   *   The context array.
   */
  public function setContext(array $context): void {
    $this->context = $context;
  }

  /**
   * Get the agent.
   *
   * @return \Drupal\ai_agents\PluginInterfaces\AiAgentInterface
   *   The agent from event.
   */
  public function getAgent(): AiAgentInterface {
    return $this->agent;
  }

}
