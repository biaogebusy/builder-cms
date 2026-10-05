<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics\EventSubscriber;

use Drupal\xinshi_analytics\AnalyticsException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/** Applies the private response contract even when authentication fails before routing. */
final class AnalyticsResponseSubscriber implements EventSubscriberInterface {

  /** Matches only this module's fixed endpoints, not unrelated API errors. */
  private function applies(string $path): bool {
    return in_array($path, ['/api/v3/analytics/capabilities', '/api/v3/analytics/datasets',
      '/api/v3/analytics/count', '/api/v3/analytics/evidence', '/api/v3/analytics/evidence/verify'], TRUE);
  }

  /** Sanitizes errors without returning exception messages or backend traces. */
  public function onException(ExceptionEvent $event): void {
    if (!$this->applies($event->getRequest()->getPathInfo())) {
      return;
    }
    $error = $event->getThrowable();
    $statuses = ['invalid_query' => 400, 'dataset_unavailable' => 403,
      'dataset_version_changed' => 409, 'range_too_large' => 422, 'query_failed' => 503, 'evidence_unavailable' => 410];
    if ($error instanceof AnalyticsException) {
      $code = $error->getMessage();
      $status = $statuses[$code];
    }
    else {
      $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503;
      $code = match ($status) {
        400, 405, 413, 415 => 'invalid_query',
        401, 403, 404 => 'dataset_unavailable',
        default => 'query_failed',
      };
    }
    $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
    $event->setResponse(new JsonResponse(['code' => $code], $status, $headers));
  }

  /** Successes and all refusals must stay out of shared and browser caches. */
  public function onResponse(ResponseEvent $event): void {
    if ($this->applies($event->getRequest()->getPathInfo())) {
      $headers = $event->getResponse()->headers;
      $headers->set('Cache-Control', 'private, no-store, max-age=0');
      $headers->set('Pragma', 'no-cache');
    }
  }

  /** {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::EXCEPTION => ['onException', 40],
      KernelEvents::RESPONSE => ['onResponse', -1000]];
  }

}
