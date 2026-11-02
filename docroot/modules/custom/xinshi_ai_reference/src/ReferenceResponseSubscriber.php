<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Prevent caching conversation responses, including old Views and error responses. */
final class ReferenceResponseSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -1000],
      KernelEvents::EXCEPTION => ['onException', 100]];
  }

  /** Core wraps presave errors; retain a retryable conflict at the HTTP boundary. */
  public function onException(ExceptionEvent $event): void {
    $route = (string) $event->getRequest()->attributes->get('_route');
    $conversation_write = str_starts_with($route, 'jsonapi.node--conversation.') ||
      in_array($route, ['xinshi_chat.conversation.delete', 'xinshi_ai.conversation.update'], TRUE);
    for ($error = $event->getThrowable(); $error; $error = $error->getPrevious()) {
      if ($error instanceof ConversationReferenceConflict || ($conversation_write &&
        $error instanceof \PDOException && (string) $error->getCode() === '40001')) {
        $event->setResponse(new JsonResponse(['error' => 'conversation_reference_conflict'], 409,
          ['Cache-Control' => 'private, no-store']));
        return;
      }
    }
  }

  public function onResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    $route = (string) $request->attributes->get('_route');
    if (str_starts_with($route, 'jsonapi.node--ai_session.') ||
      str_starts_with($route, 'jsonapi.node--conversation.') ||
      $route === 'xinshi_ai.session.content' ||
      in_array($route, ['xinshi_ai.conversation.update', 'xinshi_chat.conversations', 'xinshi_chat.sessions'], TRUE) ||
      str_starts_with($route, 'xinshi_ai_reference.') ||
      str_starts_with($route, 'xinshi_chat.conversation.') ||
      $request->getPathInfo() === '/api/v3/ai/chat-storage' ||
      str_starts_with($request->getPathInfo(), '/api/v3/ai/conversations/') ||
      in_array($request->getPathInfo(), ['/api/v2/sessions', '/api/v2/conversations'], TRUE)) {
      $event->getResponse()->headers->set('Cache-Control', 'private, no-store');
    }
  }

}
