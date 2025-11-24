<?php

namespace Drupal\jsonapi_include\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jsonapi\Routing\Routes;
use Drupal\jsonapi_include\JsonapiParseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The class handler response subscriber.
 */
class ResponseSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The parse interface.
   *
   * @var \Drupal\jsonapi_include\JsonapiParseInterface
   */
  protected $jsonapiInclude;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE] = ['onResponse'];

    return $events;
  }

  /**
   * Set config factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   */
  public function setConfig(ConfigFactoryInterface $config) {
    $this->config = $config;
  }

  /**
   * Set jsonapi parse.
   *
   * @param \Drupal\jsonapi_include\JsonapiParseInterface $jsonapi_include
   *   The parse interface.
   */
  public function setJsonapiInclude(JsonapiParseInterface $jsonapi_include) {
    $this->jsonapiInclude = $jsonapi_include;
  }

  /**
   * Set route match service.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The  route match service.
   */
  public function setRouteMatch(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * This method is called the KernelEvents::RESPONSE event is dispatched.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event) {

    if (!$this->routeMatch->getRouteObject()) {
      return;
    }
    $route_defaults = $this->routeMatch->getRouteObject()->getDefaults();
    if (Routes::isJsonApiRequest($route_defaults) || !empty($route_defaults[Routes::JSON_API_ROUTE_FLAG_KEY])) {
      $response = $event->getResponse();
      if ($response instanceof CacheableResponseInterface) {
        $response->getCacheableMetadata()->addCacheContexts(['url.query_args:jsonapi_include']);
      }
      $need_parse = TRUE;
      if ($this->config->get('jsonapi_include.settings')->get('use_include_query')) {
        $need_parse = !empty($event->getRequest()->query->get('jsonapi_include'));
      }

      if ($need_parse) {
        $content = $response->getContent();
        if (strpos($content, '{"jsonapi"') === 0) {
          $this->jsonapiInclude->parse($response);
        }
      }
    }
  }

}
