<?php

declare(strict_types=1);

namespace Drupal\ai_assistant_api;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai_assistant_api\Attribute\AiAssistantAction;

/**
 * Vector DB plugin manager.
 */
final class AiAssistantActionPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AiAssistantAction', $namespaces, $module_handler, AiAssistantActionInterface::class, AiAssistantAction::class);
    $this->alterInfo('ai_assistant_action_info');
    $this->setCacheBackend($cache_backend, 'ai_assistant_action_plugins');
  }

  /**
   * Creates a plugin instance of a Ai Assistant Actions.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   *
   * @return \Drupal\ai_assistant_api\AiAssistantActionInterface
   *   An action the AI Assistant can use.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function createInstance($plugin_id, array $configuration = []): AiAssistantActionInterface {
    /** @var \Drupal\ai_assistant_api\AiAssistantActionInterface $assistantInterface */
    $assistantInterface = parent::createInstance($plugin_id, $configuration);
    return $assistantInterface;
  }

  /**
   * Get a list of all instructions.
   *
   * @param array $configs
   *   The configurations.
   *
   * @return array
   *   An array of all instructions.
   */
  public function listAllUsageInstructions($configs = []): array {
    $instructions = [];
    foreach ($this->getDefinitions() as $definition) {
      $instance = $this->createInstance($definition['id'], $configs[$definition['id']] ?? []);
      foreach ($instance->listUsageInstructions() as $instruction) {
        $instructions[] = $instruction;
      }
    }
    return $instructions;
  }

  /**
   * Get the list of all actions.
   *
   * @param array $configs
   *   The configurations.
   *
   * @return array
   *   An array of all actions.
   */
  public function listAllActions($configs = []): array {
    $actions = [];
    foreach ($this->getDefinitions() as $definition) {
      $instance = $this->createInstance($definition['id'], $configs[$definition['id']] ?? []);
      foreach ($instance->listActions() as $actionId => $action) {
        $actions[$actionId] = $action;
      }
    }
    return $actions;
  }

  /**
   * List all contexts.
   *
   * @param \Drupal\ai_assistant_api\AiAssistantInterface $assistant
   *   The assistant.
   * @param string $thread_id
   *   The thread ID.
   * @param array $configs
   *   The configurations.
   *
   * @return array
   *   An array of all contexts.
   */
  public function listAllContexts(AiAssistantInterface $assistant, string $thread_id, $configs = []): array {
    $contexts = [];
    foreach ($this->getDefinitions() as $definition) {
      if (!in_array($definition['id'], $assistant->get('actions_enabled'))) {
        continue;
      }
      $instance = $this->createInstance($definition['id'], $configs[$definition['id']] ?? []);
      $instance->setThreadId($thread_id);
      $instance->setAssistant($assistant);
      $contexts = array_merge($contexts, $instance->listContexts());
    }
    return $contexts;
  }

}
