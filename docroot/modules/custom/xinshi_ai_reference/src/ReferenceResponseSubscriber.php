<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Prevent caching conversation responses, including old Views and error responses. */
final class ReferenceResponseSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -1000]];
  }

  public function onResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    $route = (string) $request->attributes->get('_route');
    if (str_starts_with($route, 'jsonapi.node--ai_session.') ||
      str_starts_with($route, 'jsonapi.node--conversation.') ||
      in_array($request->getPathInfo(), ['/api/v2/sessions', '/api/v2/conversations'], TRUE)) {
      $event->getResponse()->headers->set('Cache-Control', 'private, no-store');
    }
  }

}
