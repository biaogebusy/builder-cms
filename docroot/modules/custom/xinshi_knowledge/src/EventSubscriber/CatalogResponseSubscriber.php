<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Prevent caching even when authentication or routing refuses a catalog request. */
final class CatalogResponseSubscriber implements EventSubscriberInterface {

  public function onResponse(ResponseEvent $event): void {
    $path = $event->getRequest()->getPathInfo();
    if (preg_match('@^/(?:[a-z-]+/)?api/v3/knowledge(?:/[^/]+)?$@D', $path)) {
      $event->getResponse()->headers->set('Cache-Control', 'private, no-store, max-age=0');
    }
  }

  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -1000]];
  }

}
