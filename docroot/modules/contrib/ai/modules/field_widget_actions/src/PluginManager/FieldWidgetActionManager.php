<?php

namespace Drupal\field_widget_actions\PluginManager;

use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetActionInterface;
use Drupal\field_widget_actions\FieldWidgetActionManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\field_widget_actions\FieldWidgetFormActionInterface;

/**
 * Provides a plugin manager for Field Widget Actions.
 */
class FieldWidgetActionManager extends DefaultPluginManager implements FieldWidgetActionManagerInterface {

  /**
   * Constructs a FieldWidgetActionManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/FieldWidgetAction',
      $namespaces,
      $module_handler,
      FieldWidgetActionInterface::class,
      FieldWidgetAction::class
    );
    $this->alterInfo('field_widget_action_info');
    $this->setCacheBackend($cache_backend, 'field_widget_action_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedFieldWidgetActions($widget_type, $field_type): array {
    return array_filter($this->getDefinitions(), function ($plugin) use ($field_type, $widget_type) {
      return (in_array($field_type, $plugin['field_types']) || empty($plugin['field_types'])) && (in_array($widget_type, $plugin['widget_types']) || empty($plugin['widget_types']));
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetActionFormDefinitions(): array {
    return array_filter($this->getDefinitions(), function ($plugin) {
      try {
        return $this->createInstance($plugin['id']) instanceof FieldWidgetFormActionInterface;
      }
      catch (\Exception $exception) {
        return FALSE;
      }
    });
  }

}
