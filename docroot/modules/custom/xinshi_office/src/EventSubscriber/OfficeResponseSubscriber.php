<?php

declare(strict_types=1);

namespace Drupal\xinshi_office\EventSubscriber;

use Drupal\xinshi_office\OfficeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/** Keeps successes, authorization failures and errors out of shared response caches. */
final class OfficeResponseSubscriber implements EventSubscriberInterface {

  /** Hides internal exceptions and document text from API error bodies. */
  public function onException(ExceptionEvent $event): void {
    if (!$this->matches($event->getRequest()->getPathInfo())) return;
    $error = $event->getThrowable();
    $status = $error instanceof OfficeException ? $error->status :
      ($error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503);
    $code = $error instanceof OfficeException ? $error->reason : match ($status) {
      400, 405, 413, 415 => 'invalid_input', 401, 403 => 'permission_denied',
      404 => 'resource_unavailable', default => 'operation_unavailable',
    };
    $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
    $event->setResponse(new JsonResponse(['code' => $code], $status, $headers));
  }

  /** Applies the same private policy to binary downloads and pre-controller refusals. */
  public function onResponse(ResponseEvent $event): void {
    if ($this->matches($event->getRequest()->getPathInfo())) {
      $event->getResponse()->headers->set('Cache-Control', 'private, no-store, max-age=0');
      $event->getResponse()->headers->set('Pragma', 'no-cache');
      $event->getResponse()->headers->set('X-Content-Type-Options', 'nosniff');
    }
  }

  /** {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::EXCEPTION => ['onException', 40], KernelEvents::RESPONSE => ['onResponse', -1000]];
  }

  private function matches(string $path): bool {
    return (bool) preg_match('@^/api/v3/ai/office-(documents|files)(/|$)@', $path);
  }

}

