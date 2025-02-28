<?php

namespace Drupal\jsonapi_extras\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Associates config cache tag and rebuilds container + routes when necessary.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * The Drupal kernel.
   *
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $drupalKernel;

  /**
   * The route building service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * The route builder.
   *
   * @var Drupal\Core\Routing\RouteBuilder
   */
  protected $service;

  /**
   * Constructs a ConfigSubscriber object.
   *
   * @param \Drupal\Core\DrupalKernelInterface $drupal_kernel
   *   The Drupal kernel.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route building service.
   */
  public function __construct(DrupalKernelInterface $drupal_kernel, RouteBuilderInterface $route_builder) {
    $this->drupalKernel = $drupal_kernel;
    $this->routeBuilder = $route_builder;
  }

  /**
   * Rebuilds container and routes  when 'path_prefix' configuration is changed.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The Event to process.
   */
  public function onSave(ConfigCrudEvent $event) {
    $container = \Drupal::getContainer();
    // It is problematic to rebuild the container during the installation.
    $should_process = $container->getParameter('kernel.environment') !== 'install'
      && (!$container->hasParameter('jsonapi_extras.base_path_override_disabled') || !$container->getParameter('jsonapi_extras.base_path_override_disabled'))
      && $event->getConfig()->getName() === 'jsonapi_extras.settings';
    if ($should_process) {
      // @see \Drupal\jsonapi_extras\JsonapiExtrasServiceProvider::alter()
      if ($event->isChanged('path_prefix')) {
        $this->drupalKernel->rebuildContainer();
        // Because \Drupal\jsonapi\Routing\Routes::routes() uses a container
        // parameter, we need to ensure that it uses the freshly rebuilt
        // container. Due to that, it's impossible to use an injected route
        // builder service, at least until core updates it to support
        // \Drupal\Core\DrupalKernelInterface::CONTAINER_INITIALIZE_SUBREQUEST_FINISHED.
        $this->service = $container->get('router.builder');
        $container->get('router.builder')->rebuild();
      }
    }
  }

  /**
   * Associates JSON:API Extras' config cache tag with all JSON:API responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event) {
    if ($event->getRequest()->getRequestFormat() !== 'api_json') {
      return;
    }

    $response = $event->getResponse();
    if (!$response instanceof CacheableResponseInterface) {
      return;
    }

    $response->getCacheableMetadata()
      ->addCacheTags([
        'config:jsonapi_extras.settings',
        'config:jsonapi_resource_config_list',
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    // Run before
    // \Drupal\jsonapi\EventSubscriber\ResourceResponseSubscriber::onResponse()
    // (priority 128), so we can add JSON:API's config cache tag.
    $events[KernelEvents::RESPONSE][] = ['onResponse', 150];
    return $events;
  }

}
