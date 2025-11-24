<?php

declare(strict_types=1);

namespace Drupal\private_message\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\private_message\Annotation\PrivateMessageConfigForm as PrivateMessageConfigFormAnnotation;
use Drupal\private_message\Attribute\PrivateMessageConfigForm as PrivateMessageConfigFormAttribute;
use Drupal\private_message\Plugin\PrivateMessageConfigForm\PrivateMessageConfigFormPluginInterface;

/**
 * Plugin manager for detect PrivateMessageConfigForm plugins.
 */
class PrivateMessageConfigFormManager extends DefaultPluginManager implements PrivateMessageConfigFormManagerInterface {

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct(
      'Plugin/PrivateMessageConfigForm',
      $namespaces,
      $moduleHandler,
      PrivateMessageConfigFormPluginInterface::class,
      PrivateMessageConfigFormAttribute::class,
      PrivateMessageConfigFormAnnotation::class,
    );
    $this->alterInfo('private_message_config_form_info');
    $this->setCacheBackend($cacheBackend, 'private_message_config_form');
  }

}
