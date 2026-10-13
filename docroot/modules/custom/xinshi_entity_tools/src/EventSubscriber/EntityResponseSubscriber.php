<?php

declare(strict_types=1);

namespace Drupal\xinshi_entity_tools\EventSubscriber;

use Drupal\xinshi_entity_tools\EntityToolException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/** Prevents protected content and authentication failures from entering shared caches. */
final class EntityResponseSubscriber implements EventSubscriberInterface {

  /** Sanitizes errors, including failures before controller invocation. */
  public function onException(ExceptionEvent $event): void {
    if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/v3/entity-tools/')) {
      return;
    }
    $error = $event->getThrowable();
    $status = $error instanceof EntityToolException ? $error->status :
      ($error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503);
    $code = $error instanceof EntityToolException ? $error->reason : match ($status) {
      400, 405, 413, 415 => 'invalid_input',
      401, 403 => 'permission_denied',
      404 => 'resource_unavailable',
      default => 'operation_unavailable',
    };
    $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
    $event->setResponse(new JsonResponse(['code' => $code], $status, $headers));
  }

  /** Enforces the same cache policy on successes, refusals and unhandled errors. */
  public function onResponse(ResponseEvent $event): void {
    if (str_starts_with($event->getRequest()->getPathInfo(), '/api/v3/entity-tools/')) {
      $event->getResponse()->headers->set('Cache-Control', 'private, no-store, max-age=0');
      $event->getResponse()->headers->set('Pragma', 'no-cache');
    }
  }

  /** {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::EXCEPTION => ['onException', 40], KernelEvents::RESPONSE => ['onResponse', -1000]];
  }

}
