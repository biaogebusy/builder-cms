<?php

declare(strict_types=1);

namespace Drupal\xinshi_api\EventSubscriber;

use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/** Keeps caller-specific descriptions and authentication failures out of caches. */
final class ContentFormResponseSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly LoggerInterface $logger) {}

  private function matches(string $path): bool {
    return (bool) preg_match('#^(?:/[a-zA-Z][a-zA-Z0-9-]*)?/api/v3/(?:node|comment)-form(?:/|$)#', $path);
  }

  /** Returns stable errors even when routing or authentication fails first. */
  public function onException(ExceptionEvent $event): void {
    if (!$this->matches($event->getRequest()->getPathInfo())) {
      return;
    }
    $error = $event->getThrowable();
    if (!$error instanceof HttpExceptionInterface) {
      Error::logException($this->logger, $error);
    }
    $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503;
    $code = match ($status) {
      400, 405 => 'invalid_input',
      401, 403 => 'permission_denied',
      404 => 'resource_unavailable',
      422 => 'form_not_supported',
      default => 'operation_unavailable',
    };
    $event->setResponse(new JsonResponse(['code' => $code], $status,
      $error instanceof HttpExceptionInterface ? $error->getHeaders() : []));
  }

  /** Applies to successes and failures, including language-prefixed requests. */
  public function onResponse(ResponseEvent $event): void {
    if ($this->matches($event->getRequest()->getPathInfo())) {
      $event->getResponse()->headers->set('Cache-Control', 'private, no-store, max-age=0');
    }
  }

  /** {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::EXCEPTION => ['onException', 40],
      KernelEvents::RESPONSE => ['onResponse', -1000]];
  }

}
