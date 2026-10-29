<?php

namespace Drupal\xinshi_api\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Keeps HTTP caches from bypassing the current page publication check. */
final class PageJsonResponseSubscriber implements EventSubscriberInterface {

  /** Applies after Drupal's final cacheability response processing. */
  public function onResponse(ResponseEvent $event): void {
    if ($event->getRequest()->attributes->get('_route') !== 'xinshi_api.v3.landingPage') {
      return;
    }
    $response = $event->getResponse();
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Accel-Expires', '0');
  }

  /** {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -100]];
  }
}
