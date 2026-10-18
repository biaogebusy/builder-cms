<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/** Gives the module's list endpoint precedence over an old site-created View. */
final class RouteSubscriber extends RouteSubscriberBase {

  private array $owned = [];

  /** Preserve the module definitions before Views adopts an existing matching path. */
  public function capture(RouteBuildEvent $event): void {
    $this->owned = [];
    foreach (['xinshi_chat.conversations', 'xinshi_chat.sessions'] as $name) {
      if ($route = $event->getRouteCollection()->get($name)) {
        $this->owned[$name] = clone $route;
      }
    }
  }

  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($collection as $name => $route) {
      if (in_array(rtrim($route->getPath(), '/'), ['/api/v2/conversations', '/api/v2/sessions'], TRUE) &&
        !in_array($name, ['xinshi_chat.conversations', 'xinshi_chat.sessions'], TRUE)) {
        $collection->remove($name);
      }
    }
    foreach ($this->owned as $name => $route) {
      $collection->add($name, $route);
    }
  }

  public static function getSubscribedEvents(): array {
    // Views alters matching routes at -175, including routes owned by other modules.
    return [RoutingEvents::ALTER => [['capture', 1000], ['onAlterRoutes', -200]]];
  }

}
