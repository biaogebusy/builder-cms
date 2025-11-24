<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\image_effects\Plugin\Attribute\FontSelector;

/**
 * Plugin manager for FontSelector plugins.
 */
class FontSelectorPluginManager extends ImageEffectsPluginManagerBase {

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct(
      "Plugin/image_effects/FontSelector",
      $namespaces,
      $module_handler,
      $configFactory,
      FontSelector::class,
    );
    $this->alterInfo("image_effects_font_selector_plugin_info");
    $this->setCacheBackend($cache_backend, "image_effects_font_selector_plugins");
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'font_selector';
  }

}
