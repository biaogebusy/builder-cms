<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\xinshi_chat\Schema\Definition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Stops API use while retaining administrative access and all stored content. */
final class AvailabilitySubscriber implements EventSubscriberInterface {

  public function __construct(private readonly ConfigFactoryInterface $config) {}

  /** Deny both custom routes and aliased JSON:API resources when Chat is disabled. */
  public function onRequest(RequestEvent $event): void {
    $config = $this->config->get('xinshi_chat.settings');
    $current = $config->get('schema_version') === Definition::CURRENT;
    if ($config->get('enabled') && $current) {
      return;
    }
    $request = $event->getRequest();
    $route = (string) $request->attributes->get('_route');
    $path = $request->getPathInfo();
    if (str_starts_with($route, 'jsonapi.node--conversation.') ||
      str_starts_with($route, 'jsonapi.node--ai_session.') ||
      str_starts_with($route, 'xinshi_ai_reference.') ||
      in_array($route, ['xinshi_chat.conversations', 'xinshi_chat.sessions',
        'xinshi_ai.conversation.update', 'xinshi_ai.session.content'], TRUE) ||
      str_starts_with($path, '/api/v3/ai/conversations/') ||
      in_array($path, ['/api/v3/ai/chat-storage', '/api/v2/conversations', '/api/v2/sessions'], TRUE)) {
      $event->setResponse(new JsonResponse(['error' => $current ? 'chat_disabled' : 'chat_structure_update_required'], 503,
        ['Cache-Control' => 'private, no-store']));
    }
  }

  /** {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    // Run after routing so JSON:API aliases are identified by the route name.
    return [KernelEvents::REQUEST => ['onRequest', 30]];
  }

}
