<?php

declare(strict_types=1);

namespace Drupal\diff;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\diff\Annotation\DiffLayoutBuilder as DiffLayoutBuilderAnnotation;
use Drupal\diff\Attribute\DiffLayoutBuilder as DiffLayoutBuilderAttribute;

/**
 * Plugin type manager for field diff builders.
 */
class DiffLayoutManager extends DefaultPluginManager {

  protected ImmutableConfig $config;
  protected ImmutableConfig $layoutPluginsConfig;

  /**
   * Constructs a DiffLayoutManager object.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct('Plugin/diff/Layout', $namespaces, $module_handler, DiffLayoutInterface::class, DiffLayoutBuilderAttribute::class, DiffLayoutBuilderAnnotation::class);

    $this->setCacheBackend($cache_backend, 'diff_layout_builder_plugins');
    $this->alterInfo('diff_layout_builder_info');
    $this->config = $config_factory->get('diff.settings');
    $this->layoutPluginsConfig = $config_factory->get('diff.layout_plugins');
  }

  /**
   * Gets the applicable layout plugins.
   *
   * Loop over the plugins that can be used to display the diff comparison
   * sorting them by the weight.
   *
   * @return array
   *   The layout plugin options.
   */
  public function getPluginOptions(): array {
    $plugins = $this->config->get('general_settings.layout_plugins');
    $plugin_options = [];
    // Get the plugins sorted and build an array keyed by the plugin id.
    if ($plugins) {
      // Sort the plugins based on their weight.
      \uasort($plugins, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
      foreach ($plugins as $key => $value) {
        if ($this->hasDefinition($key)) {
          $plugin = $this->getDefinition($key);
          if ($plugin && $value['enabled']) {
            $plugin_options[$key] = $plugin['label'];
          }
        }
      }
    }
    return $plugin_options;
  }

  /**
   * Gets the default layout plugin selected.
   *
   * Take the first option of the array returned by getPluginOptions.
   *
   * @return string
   *   The id of the default plugin.
   */
  public function getDefaultLayout(): string {
    $plugins = \array_keys($this->getPluginOptions());
    return \reset($plugins);
  }

  /**
   * {@inheritdoc}
   */
  public function findDefinitions() {
    $definitions = parent::findDefinitions();

    // Remove plugin html_diff if library is not present.
    $has_htmlDiffAdvanced = \class_exists('\HtmlDiffAdvanced');
    if (!$has_htmlDiffAdvanced) {
      unset($definitions['visual_inline']);
    }
    return $definitions;
  }

}
