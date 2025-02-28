<?php

namespace Drupal\search_api;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\search_api\Event\GatheringPluginInfoEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Extends the default plugin manager to add support for alter events.
 */
class SearchApiPluginManager extends DefaultPluginManager {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The name of the alter event to dispatch.
   *
   * @var string
   */
  protected $alterEventName;

  /**
   * Constructs a new class instance.
   *
   * @param string|bool $subdir
   *   The plugin's subdirectory, for example Plugin/views/filter.
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param string|null $plugin_interface
   *   (optional) The interface each plugin should implement.
   * @param string $plugin_definition_annotation_name
   *   (optional) The name of the annotation that contains the plugin definition.
   *   Defaults to 'Drupal\Component\Annotation\Plugin'.
   * @param string[] $additional_annotation_namespaces
   *   (optional) Additional namespaces to scan for annotation definitions.
   */
  public function __construct($subdir, \Traversable $namespaces, ModuleHandlerInterface $module_handler, EventDispatcherInterface $eventDispatcher, $plugin_interface = NULL, $plugin_definition_annotation_name = 'Drupal\Component\Annotation\Plugin', array $additional_annotation_namespaces = []) {
    parent::__construct($subdir, $namespaces, $module_handler, $plugin_interface, $plugin_definition_annotation_name, $additional_annotation_namespaces);

    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Sets the alter event class name.
   *
   * @param string $eventName
   *   Name of the event to use for the alter event.
   */
  protected function alterEvent($eventName) {
    $this->alterEventName = $eventName;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterDefinitions(&$definitions) {
    if ($this->alterHook) {
      if (!$this->alterEventName) {
        $this->moduleHandler->alter($this->alterHook, $definitions);
        return;
      }
      $description = "This hook is deprecated in search_api:8.x-1.14 and is removed from search_api:2.0.0. Please use the \"{$this->alterEventName}\" event instead. See https://www.drupal.org/node/3059866";
      $this->moduleHandler->alterDeprecated($description, $this->alterHook, $definitions);
    }

    if ($this->alterEventName) {
      $event = new GatheringPluginInfoEvent($definitions);
      $this->eventDispatcher->dispatch($event, $this->alterEventName);
    }
  }

}
