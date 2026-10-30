<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits\EventSubscriber;

use Drupal\xinshi_credits\CreditException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/** Protect credit responses, including failures raised before route matching. */
final class CreditResponseSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly LoggerInterface $logger) {}

  /** Hide internal exceptions and preserve standard authentication headers. */
  public function exception(ExceptionEvent $event): void {
    if (!preg_match('@/api/v3/credits(/|$)@', $event->getRequest()->getPathInfo())) return;
    $error = $event->getThrowable();
    if (!$error instanceof CreditException && !$error instanceof HttpExceptionInterface) {
      // Raw database messages can include financial notes or source references.
      $this->logger->error('Credit request failed with @class (code @code).',
        ['@class' => $error::class, '@code' => $error->getCode()]);
    }
    $status = $error instanceof CreditException ? $error->status :
      ($error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503);
    $code = $error instanceof CreditException ? $error->reason : match ($status) {
      400, 405, 413, 415 => 'invalid_query', 401, 403 => 'permission_denied',
      404 => 'credits_unavailable', default => 'credits_unavailable',
    };
    $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
    $event->setResponse(new JsonResponse(['code' => $code], $status, $headers));
  }

  /** Prevent personal amounts and denials from entering any shared cache. */
  public function response(ResponseEvent $event): void {
    if (!preg_match('@/(?:api/v3/credits|admin/config/people/credits)(/|$)@', $event->getRequest()->getPathInfo())) return;
    $headers = $event->getResponse()->headers;
    $headers->set('Cache-Control', 'private, no-store, max-age=0');
    $headers->set('Pragma', 'no-cache');
    $headers->set('X-Content-Type-Options', 'nosniff');
  }

  /** {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::EXCEPTION => ['exception', 40], KernelEvents::RESPONSE => ['response', -1000]];
  }

}
