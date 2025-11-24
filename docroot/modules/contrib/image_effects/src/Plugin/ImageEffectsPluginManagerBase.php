<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for ColorSelector plugins.
 */
abstract class ImageEffectsPluginManagerBase extends DefaultPluginManager {

  public function __construct(
    string $pluginNamespace,
    \Traversable $namespaces,
    ModuleHandlerInterface $module_handler,
    protected readonly ConfigFactoryInterface $configFactory,
    string $pluginAttributeClass,
  ) {
    parent::__construct(
      $pluginNamespace,
      $namespaces,
      $module_handler,
      ImageEffectsPluginBaseInterface::class,
      $pluginAttributeClass,
    );
  }

  /**
   * Get the 'image_effects' plugin type.
   *
   * @return string
   *   The plugin type.
   *
   * @throws \LogicException
   *   When a child class does not implement the method.
   */
  public function getType(): string {
    throw new \LogicException(__METHOD__ . '() not implemented');
  }

  /**
   * Returns an instance of the specified 'ColorSelector' plugin.
   *
   * @param string $plugin_id
   *   The plugin id.
   *
   * @return \Drupal\image_effects\Plugin\ImageEffectsPluginBaseInterface
   *   An instance of the specified 'image_effects' plugin.
   */
  public function getPlugin(?string $plugin_id = NULL): ImageEffectsPluginBaseInterface {
    $plugin_id = $plugin_id ?: $this->configFactory->get('image_effects.settings')->get($this->getType() . '.plugin_id');
    $plugins = $this->getAvailablePlugins();

    // Check if plugin is available.
    if (!isset($plugins[$plugin_id]) || !class_exists($plugins[$plugin_id]['class'])) {
      $plugin_id = NULL;
      // @todo Change to a logged error.
      trigger_error("image_effects " . $this->getType() . " handling plugin '$plugin_id' is no longer available.", E_USER_ERROR);
    }

    return $this->createInstance($plugin_id, ['plugin_type' => $this->getType()]);
  }

  /**
   * Gets a list of available plugins.
   *
   * @return array
   *   An array with the plugin ids as keys and the definitions as values.
   */
  public function getAvailablePlugins(): array {
    $plugins = $this->getDefinitions();
    $output = [];
    foreach ($plugins as $id => $definition) {
      // Only allow plugins that are available.
      if (call_user_func($definition['class'] . '::isAvailable')) {
        $output[$id] = $definition;
      }
    }
    return $output;
  }

  /**
   * Gets a formatted list of available plugins.
   *
   * @return array
   *   An array with the plugin ids as keys and the descriptions as values.
   */
  public function getPluginOptions(): array {
    $options = [];
    foreach ($this->getAvailablePlugins() as $plugin) {
      $options[$plugin['id']] = new FormattableMarkup('<b>@title</b> - @description', [
        '@title' => $plugin['shortTitle'],
        '@description' => $plugin['help'],
      ]);
    }
    return $options;
  }

}
