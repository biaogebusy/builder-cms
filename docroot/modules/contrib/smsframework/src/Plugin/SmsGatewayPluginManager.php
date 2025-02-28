<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages SMS gateways implemented using AnnotatedClassDiscovery.
 */
class SmsGatewayPluginManager extends DefaultPluginManager implements SmsGatewayPluginManagerInterface {

  /**
   * Creates a new SmsGatewayPluginManager instance.
   *
   * @param \Traversable $namespaces
   *   The namespaces to search for the gateway plugins.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler for calling module hooks.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/SmsGateway', $namespaces, $module_handler, 'Drupal\sms\Plugin\SmsGatewayPluginInterface', 'Drupal\sms\Annotation\SmsGateway');
    $this->setCacheBackend($cache_backend, 'sms_gateways');
    $this->alterInfo('sms_gateway_info');
  }

}
