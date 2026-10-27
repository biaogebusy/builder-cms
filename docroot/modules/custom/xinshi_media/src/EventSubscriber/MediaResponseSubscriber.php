<?php

declare(strict_types=1);

namespace Drupal\xinshi_media\EventSubscriber;

use Drupal\xinshi_media\MediaException;
use Drupal\xinshi_media\MediaPolicy;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/** Prevents account-specific media and failed authorization from entering shared caches. */
final class MediaResponseSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly MediaPolicy $policy) {}

  public function exception(ExceptionEvent $event): void {
    if (!preg_match('@/api/v3/media(/|$)@', $event->getRequest()->getPathInfo())) return;
    $error = $event->getThrowable();
    $status = $error instanceof MediaException ? $error->status :
      ($error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503);
    $code = $error instanceof MediaException ? $error->reason : match ($status) {
      400, 405, 413, 415 => 'invalid_input', 401, 403 => 'permission_denied',
      404 => 'media_unavailable', default => 'operation_unavailable',
    };
    $event->setResponse(new JsonResponse(['code' => $code], $status));
  }

  public function response(ResponseEvent $event): void {
    if ($this->policy->metadataRequest() || preg_match('@/api/v3/media(/|$)@', $event->getRequest()->getPathInfo())) {
      $event->getResponse()->headers->set('Cache-Control', 'private, no-store, max-age=0');
      $event->getResponse()->headers->set('Pragma', 'no-cache');
      $event->getResponse()->headers->set('X-Content-Type-Options', 'nosniff');
    }
  }

  public static function getSubscribedEvents(): array {
    return [KernelEvents::EXCEPTION => ['exception', 40], KernelEvents::RESPONSE => ['response', -1000]];
  }

}
