<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai\Attribute\AiGuardrail;

/**
 * AiGuardrail plugin manager.
 */
final class AiGuardrailPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AiGuardrail', $namespaces, $module_handler, AiGuardrailInterface::class, AiGuardrail::class);
    $this->alterInfo('ai_guardrail_info');
    $this->setCacheBackend($cache_backend, 'ai_guardrail_plugins');
  }

  /**
   * Gets the available guardrail definitions.
   *
   * @return array
   *   An array of guardrail definitions, keyed by plugin ID.
   */
  public function getOptions(): array {
    $options = [];
    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      try {
        $plugin = $this->createInstance($plugin_id);
        if ($plugin->isAvailable()) {
          $options[$plugin_id] = $definition['label'];
        }
      }
      catch (\Exception $e) {
        continue;
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []): AiGuardrailInterface {
    /** @var \Drupal\ai\Guardrail\AiGuardrailInterface $plugin */
    $plugin = parent::createInstance($plugin_id, $configuration);

    if ($plugin instanceof NonDeterministicGuardrailInterface) {
      // phpcs:disable
      // @phpstan-ignore-next-line
      $plugin->setAiPluginManager(\Drupal::service('ai.provider'));
      // phpcs:enable
    }

    return $plugin;
  }

}
