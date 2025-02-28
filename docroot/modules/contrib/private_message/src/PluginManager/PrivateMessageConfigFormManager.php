<?php

namespace Drupal\private_message\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Traversable;

/**
 * Plugin Manager to detect PrivateMessageConfigForm plugins.
 */
class PrivateMessageConfigFormManager extends DefaultPluginManager implements PrivateMessageConfigFormManagerInterface {

  /**
   * Constructs a PrivateMessageConfigFormManager object.
   *
   * @param \Traversable $namespaces
   *   Namespaces to be searched for the plugin.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(Traversable $namespaces, CacheBackendInterface $cacheBackend, ModuleHandlerInterface $moduleHandler) {
    parent::__construct('Plugin/PrivateMessageConfigForm', $namespaces, $moduleHandler, 'Drupal\private_message\Plugin\PrivateMessageConfigForm\PrivateMessageConfigFormPluginInterface', 'Drupal\private_message\Annotation\PrivateMessageConfigForm');

    $this->alterInfo('private_message_config_form_info');
    $this->setCacheBackend($cacheBackend, 'private_message_config_form');
  }

}
