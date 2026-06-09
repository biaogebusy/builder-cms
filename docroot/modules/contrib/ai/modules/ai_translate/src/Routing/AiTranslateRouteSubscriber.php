<?php

namespace Drupal\ai_translate\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber to alter entity translation routes.
 */
class AiTranslateRouteSubscriber extends RouteSubscriberBase {

  /**
   * Constructs the AI Translate Route Subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Only use AI Translate for the "Translate" tab if allowed.
    if (!$this->configFactory->get('ai_translate.settings')->get('use_ai_translate')) {
      return;
    }

    // Look for routes that use  ContentTranslationController and change it
    // to our subclass.
    foreach ($collection as $route) {
      if ($route->getRequirement('_access_content_translation_overview')) {
        $route->setDefault('_parent_controller', $route->getDefault('_controller'));
        $route->setDefault('_controller', '\Drupal\ai_translate\Controller\ContentTranslationControllerOverride::overview');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // AiTranslateRouteSubscriber is -100, make sure we are later.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -230];
    return $events;
  }

}
