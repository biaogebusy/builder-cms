<?php

namespace Drupal\entity_print\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\entity_print\Event\PrintEvents;
use Drupal\entity_print\PrintEngineException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Entity print plugin manager.
 */
class EntityPrintPluginManager extends DefaultPluginManager implements EntityPrintPluginManagerInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * An array of disabled print engines.
   *
   * @var array
   */
  protected $disabledPrintEngines;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a EntityPrintPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, EventDispatcherInterface $dispatcher, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct('Plugin/EntityPrint/PrintEngine', $namespaces, $module_handler, 'Drupal\entity_print\Plugin\PrintEngineInterface', 'Drupal\entity_print\Annotation\PrintEngine');
    $this->alterInfo('entity_print_print_engine');
    $this->setCacheBackend($cache_backend, 'entity_print_print_engines');
    $this->dispatcher = $dispatcher;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $configuration = array_merge($this->getPrintEngineSettings($plugin_id), $configuration);

    /** @var \Drupal\entity_print\Plugin\PrintEngineInterface $class */
    $definition = $this->getDefinition($plugin_id);
    $class = $definition['class'];

    // Throw an exception if someone tries to use a plugin that doesn't have all
    // of its dependencies met.
    if (!$class::dependenciesAvailable()) {
      throw new PrintEngineException(sprintf('Missing dependencies. %s', $class::getInstallationInstructions()));
    }

    return parent::createInstance($plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function createSelectedInstance($export_type) {
    $config = $this->configFactory->get('entity_print.settings');
    $config_engine = 'print_engines.' . $export_type . '_engine';
    $plugin_id = $config->get($config_engine);

    if (!$plugin_id) {
      throw new PrintEngineException(sprintf('Please configure a %s print engine.', $export_type));
    }

    return $this->createInstance($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function isPrintEngineEnabled($plugin_id) {
    if (!$plugin_id) {
      return FALSE;
    }

    // If the plugin definition has gone, it obviously isn't enabled.
    $plugin_definition = $this->getDefinition($plugin_id, FALSE);
    if (!$plugin_definition) {
      return FALSE;
    }

    $disabled_definitions = $this->getDisabledDefinitions($plugin_definition['export_type']);
    return !in_array($plugin_id, array_keys($disabled_definitions), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getDisabledDefinitions($filter_export_type) {
    if (!isset($this->disabledPrintEngines[$filter_export_type])) {
      $this->disabledPrintEngines[$filter_export_type] = [];

      foreach ($this->getDefinitions() as $plugin_id => $definition) {
        /** @var \Drupal\entity_print\Plugin\PrintEngineInterface $class */
        $class = $definition['class'];
        if ($definition['export_type'] === $filter_export_type && !$class::dependenciesAvailable()) {
          $this->disabledPrintEngines[$filter_export_type][$plugin_id] = $definition;
        }
      }
    }

    return $this->disabledPrintEngines[$filter_export_type];
  }

  /**
   * Gets the entity config settings for this plugin.
   *
   * @param string $plugin_id
   *   The plugin id.
   *
   * @return array
   *   An array of Print engine settings for this plugin.
   */
  protected function getPrintEngineSettings($plugin_id) {
    /** @var \Drupal\entity_print\Entity\PrintEngineStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('print_engine');
    if (!$entity = $storage->load($plugin_id)) {
      $entity = $storage->create(['id' => $plugin_id]);
    }
    $configuration = $entity->getSettings();
    $event = new GenericEvent(PrintEvents::CONFIGURATION_ALTER, [
      'configuration' => $configuration,
      'config' => $entity,
    ]);
    $this->dispatcher->dispatch($event, PrintEvents::CONFIGURATION_ALTER);
    $configuration = $event->getArgument('configuration');

    return $configuration;
  }

}
