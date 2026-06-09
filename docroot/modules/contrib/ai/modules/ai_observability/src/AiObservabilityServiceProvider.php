<?php

namespace Drupal\ai_observability;

use Drupal\ai_observability\EventSubscriber\AiOtelMetricsEventSubscriber;
use Drupal\ai_observability\EventSubscriber\AiOtelSpansEventSubscriber;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Registers event subscribers for installed AI observability modules.
 */
class AiObservabilityServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // We cannot use the module handler as the container is not yet compiled.
    // @see \Drupal\Core\DrupalKernel::compileContainer()
    $modules = $container->getParameter('container.modules');

    // Register OpenTelemetry spans event subscriber if the opentelemetry module
    // is installed.
    if (isset($modules['opentelemetry'])) {
      $container->register(AiOtelSpansEventSubscriber::class, AiOtelSpansEventSubscriber::class)
        ->setAutowired(TRUE)
        ->addTag('event_subscriber');
    }

    // Register OpenTelemetry metrics event subscriber only if the
    // opentelemetry_metrics module is installed.
    if (isset($modules['opentelemetry_metrics'])) {
      $container->register(AiOtelMetricsEventSubscriber::class, AiOtelMetricsEventSubscriber::class)
        ->setAutowired(TRUE)
        ->addTag('event_subscriber');
    }
  }

}
