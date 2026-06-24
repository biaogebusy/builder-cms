<?php

namespace Drupal\layout_builder_at;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;

/**
 * Layout Builder At service provider.
 */
class LayoutBuilderAtServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    if ($container->hasDefinition('layout_builder.get_block_dependency_subscriber')) {
      $definition = $container->getDefinition('layout_builder.get_block_dependency_subscriber');
      $definition->setClass('\Drupal\layout_builder_at\EventSubscriber\SetInlineBlockDependencyWithContextTranslation');
    }
  }

}
