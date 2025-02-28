<?php

namespace Drupal\bootstrap_styles\StylesGroup;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\bootstrap_styles\Style\StylePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides an StylesGroup plugin manager.
 */
class StylesGroupManager extends DefaultPluginManager {

  /**
   * The style plugin manager interface.
   *
   * @var \Drupal\bootstrap_styles\Style\StylePluginManagerInterface
   */
  protected $styleManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a StylesGroupManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\bootstrap_styles\Style\StylePluginManagerInterface $style_manager
   *   The style plugin manager interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, StylePluginManagerInterface $style_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct(
      'Plugin/BootstrapStyles/StylesGroup',
      $namespaces,
      $module_handler,
      'Drupal\bootstrap_styles\StylesGroup\StylesGroupPluginInterface',
      'Drupal\bootstrap_styles\Annotation\StylesGroup'
    );
    $this->alterInfo('bootstrap_styles_info');
    $this->setCacheBackend($cache_backend, 'bootstrap_styles_groups');
    $this->styleManager = $style_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Returns an array of styles groups.
   *
   * @return array
   *   Returns a nested array of styles keyed by styles group.
   */
  public function getStylesGroups() {
    $groups = [];
    foreach ($this->getDefinitions() as $group_id => $group_definition) {
      $groups[$group_id] = $group_definition;
      $groups[$group_id]['styles'] = $this->getGroupStyles($group_id);
    }
    uasort($groups, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
    return $groups;
  }

  /**
   * Returns an array of styles.
   *
   * @return array
   *   Returns a nested array of styles plugins.
   */
  public function getStyles() {
    $styles = [];
    foreach ($this->getDefinitions() as $group_id => $group_definition) {
      $styles += $this->getGroupStyles($group_id);
    }
    uasort($styles, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
    return $styles;
  }

  /**
   * Returns an array of group styles plugins.
   *
   * @param string $group_id
   *   The styles group plugin id.
   *
   * @return array
   *   Returns an array of styles definitions of specific group.
   */
  public function getGroupStyles($group_id) {
    $styles = [];
    foreach ($this->styleManager->getDefinitions() as $style_id => $style_definition) {
      if ($style_definition['group_id'] == $group_id) {
        $styles[$style_id] = $style_definition;
      }
    }
    uasort($styles, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
    return $styles;
  }

  /**
   * Helper function returns array of allowed groups with its plugins.
   *
   * @param string $filter
   *   The filter config name.
   *
   * @return array
   *   The allowed groups with its plugins.
   */
  public function getAllowedPlugins(string $filter = NULL) {
    $allowed_plugins = [];
    if ($filter) {
      $config = $this->configFactory->get($filter);
      if ($config->get('plugins')) {
        $allowed_plugins = [];
        // Loop through groups.
        foreach ($config->get('plugins') as $group_key => $group_plugins) {
          // Loop through group plugins.
          foreach ($group_plugins as $key => $plugin) {
            if ($plugin['enabled']) {
              $allowed_plugins[$group_key][] = $key;
            }
          }
        }
      }
    }
    return $allowed_plugins;
  }

  /**
   * Build the layout builder form styles elements.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $storage
   *   The plugins storage array.
   * @param string $filter
   *   The filter config name.
   *
   * @return array
   *   The form structure.
   */
  public function buildStylesFormElements(array &$form, FormStateInterface $form_state, array $storage, string $filter = NULL) {
    // Restrict styles.
    $allowed_plugins = $this->getAllowedPlugins($filter);

    foreach ($this->getStylesGroups() as $group_key => $style_group) {
      // Check groups restriction.
      if (!empty($allowed_plugins) && !array_key_exists($group_key, $allowed_plugins)) {
        continue;
      }

      // Styles Group.
      if (isset($style_group['styles'])) {
        $group_instance = $this->createInstance($group_key);

        $form[$group_key] = [
          '#type' => 'details',
          '#title' => $group_instance->getTitleWithIcon(),
          '#open' => FALSE,
          '#tree' => TRUE,
        ];
        $form[$group_key] += $group_instance->buildStyleFormElements($form[$group_key], $form_state, $storage);

        foreach ($style_group['styles'] as $style_key => $style) {
          // Check plugins restriction.
          if (!empty($allowed_plugins) && count($allowed_plugins[$group_key]) > 0 && !in_array($style_key, $allowed_plugins[$group_key])) {
            continue;
          }

          $style_instance = $this->styleManager->createInstance($style_key);
          $form[$group_key] += $style_instance->buildStyleFormElements($form[$group_key], $form_state, $storage);
        }
      }
    }
    return $form;
  }

  /**
   * Save styles.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $tree
   *   An array of parents.
   * @param array $storage
   *   The plugins storage array.
   * @param string $filter
   *   The filter config name.
   *
   * @return array
   *   An array of plugins with its storage values.
   */
  public function submitStylesFormElements(array &$form, FormStateInterface $form_state, array $tree = [], array $storage = [], $filter = NULL) {
    $options = [];
    $form_state->cleanValues();
    // Restrict styles.
    $allowed_plugins = $this->getAllowedPlugins($filter);
    foreach ($this->getStylesGroups() as $group_key => $style_group) {
      // Check groups restriction.
      if (!empty($allowed_plugins) && !array_key_exists($group_key, $allowed_plugins)) {
        continue;
      }
      // Styles Group.
      if ($form_state->getValue(array_merge($tree, [$group_key]))) {
        $group_elements = $form_state->getValue(array_merge($tree, [$group_key]));
        // Submit group form.
        $group_instance = $this->createInstance($group_key);
        $options += $group_instance->submitStyleFormElements($group_elements);
        // Styles Group.
        if (isset($style_group['styles'])) {
          foreach ($style_group['styles'] as $style_key => $style) {
            // Check plugins restriction.
            if (!empty($allowed_plugins) && count($allowed_plugins[$group_key]) > 0 && !in_array($style_key, $allowed_plugins[$group_key])) {
              continue;
            }
            // Submit style form.
            $style_instance = $this->styleManager->createInstance($style_key);
            $options += $style_instance->submitStyleFormElements($group_elements);
          }
        }
      }
    }

    return array_merge($storage, $options);
  }

  /**
   * Build the styles for a given build.
   *
   * @param array $build
   *   The build of element.
   * @param array $plugins_storage
   *   An array of plugins with its storage.
   * @param string $theme_wrapper
   *   The theme wrapper key.
   */
  public function buildStyles(array $build, array $plugins_storage, $theme_wrapper = NULL) {
    // Build group shared storage.
    foreach ($plugins_storage as $plugin_id => $storage) {
      if (in_array($plugin_id, array_keys($this->getStylesGroups()))) {
        $group_instance = $this->createInstance($plugin_id);
        $build = $group_instance->build($build, $plugins_storage, $theme_wrapper);
      }
    }

    // Loop through plugins storage.
    foreach ($plugins_storage as $plugin_id => $storage) {
      if (in_array($plugin_id, array_keys($this->getStyles()))) {
        $style_instance = $this->styleManager->createInstance($plugin_id);
        $build = $style_instance->build($build, $plugins_storage, $theme_wrapper);
      }
    }

    return $build;
  }

}
