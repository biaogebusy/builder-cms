<?php

namespace Drupal\charts;

use Drupal\charts\Event\ChartsEvents;
use Drupal\charts\Event\TypesInfoEvent;
use Drupal\charts\Plugin\chart\Library\ChartInterface;
use Drupal\charts\Plugin\chart\Type\Type;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages discovery and instantiation of charts_type_info plugins.
 *
 * @see \Drupal\charts\Plugin\chart\Type\TypeInterface
 *
 * @see plugin_api
 */
class TypeManager extends DefaultPluginManager {

  /**
   * Default values for each plugin.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'label' => '',
    'axis' => ChartInterface::DUAL_AXIS,
    'axis_inverted' => FALSE,
    'stacking' => FALSE,
    'class' => Type::class,
  ];

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new TypeManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Even dispatcher.
   */
  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend, EventDispatcherInterface $event_dispatcher) {
    $this->moduleHandler = $module_handler;
    $this->eventDispatcher = $event_dispatcher;
    $this->setCacheBackend($cache_backend, 'charts_type', ['charts_type']);
    $this->alterInfo('charts_type_info');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $this->discovery = new YamlDiscovery('charts_types', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('label', 'label_context');
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    $definition['id'] = $plugin_id;
    foreach (['label'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new PluginException(sprintf('The charts type %s must define the %s property.', $plugin_id, $required_property));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = parent::getDefinitions();

    // Allow other modules to alter the definition list.
    $event = new TypesInfoEvent($definitions);
    $this->eventDispatcher->dispatch($event, ChartsEvents::TYPE_INFO);
    $definitions = $event->getTypes();

    return $definitions;
  }

}
