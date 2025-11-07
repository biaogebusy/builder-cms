<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\ImportPolicy;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;

/**
 * Provides the policy plugin manager.
 */
class ImportPolicyPluginManager extends DefaultPluginManager {

  /**
   * Provides default values for all style_plugin plugins.
   *
   * @var array
   */
  protected $defaults = [
    'label' => '',
  ];

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    CacheBackendInterface $cache_backend
  ) {
    $this->moduleHandler = $module_handler;
    $this->alterInfo('entity_share_client_policies');
    $this->setCacheBackend($cache_backend, 'entity_share_client_policies');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $this->discovery = new YamlDiscovery('entity_share_client_policies', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('label', 'label_context');
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * Prepare the available policies as an array of options.
   *
   * @return array
   *   An array prepared for the form API options.
   */
  public function getOptionsList() {
    $options = [];
    foreach ($this->getDefinitions() as $policy) {
      $options[$policy['id']] = $policy['label'];
    }
    return $options;
  }

}
