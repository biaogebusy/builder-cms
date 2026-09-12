<?php

declare(strict_types=1);

namespace Drupal\crop;

use Drupal\Core\DefaultContent\PreEntityImportEvent;
use Drupal\Core\DefaultContent\PreExportEvent;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\crop\EventSubscriber\DefaultContentSubscriber;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Dynamically registers container services.
 *
 * @internal
 *   This is an internal part of Crop API and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class CropServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    if (class_exists(PreEntityImportEvent::class) && class_exists(PreExportEvent::class)) {
      $container->register(DefaultContentSubscriber::class)
        ->setClass(DefaultContentSubscriber::class)
        ->setAutoconfigured(TRUE)
        ->setAutowired(TRUE)
        ->addMethodCall('setLogger', [
          new Reference('logger.channel.default_content'),
        ]);
    }
  }

}
