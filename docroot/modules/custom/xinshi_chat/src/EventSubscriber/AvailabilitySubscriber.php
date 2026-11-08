<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\xinshi_chat\Schema\Definition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Stops API use while retaining administrative access and all stored content. */
final class AvailabilitySubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $config,
    private readonly KillSwitch $cacheKillSwitch,
    private readonly RouteProviderInterface $routes,
  ) {}

  /** Deny both custom routes and aliased JSON:API resources when Chat is disabled. */
  public function onRequest(RequestEvent $event): void {
    if (!$this->isChatRequest($event->getRequest())) {
      return;
    }
    $this->cacheKillSwitch->trigger();
    $config = $this->config->get('xinshi_chat.settings');
    $current = $config->get('schema_version') === Definition::CURRENT;
    if ($config->get('enabled') && $current) {
      return;
    }
    $event->setResponse(new JsonResponse(['error' => $current ? 'chat_disabled' : 'chat_structure_update_required'], 503,
      ['Cache-Control' => 'private, no-store']));
  }

  /** HTTP headers alone do not prevent Drupal Dynamic Page Cache from storing a response. */
  public function onResponse(ResponseEvent $event): void {
    if ($this->isChatRequest($event->getRequest())) {
      $response = $event->getResponse();
      if ($response instanceof CacheableResponseInterface) {
        $response->addCacheableDependency((new CacheableMetadata())->setCacheMaxAge(0));
      }
      $response->headers->set('Cache-Control', 'private, no-store');
    }
  }

  private function isChatRequest(Request $request): bool {
    $route = (string) $request->attributes->get('_route');
    $path = $request->getPathInfo();
    // Authentication can fail before routing; use actual routes so site JSON:API aliases still match.
    if ($route === '') {
      foreach ($this->routes->getRoutesByPattern($path)->all() as $name => $candidate) {
        if (str_starts_with($name, 'jsonapi.node--conversation.') ||
          str_starts_with($name, 'jsonapi.node--ai_session.') ||
          str_starts_with($name, 'xinshi_chat.published_page.')) {
          return TRUE;
        }
      }
    }
    return str_starts_with($route, 'jsonapi.node--conversation.') ||
      str_starts_with($route, 'xinshi_chat.published_page.') ||
      str_starts_with($route, 'jsonapi.node--ai_session.') ||
      str_starts_with($route, 'xinshi_ai_reference.') ||
      str_starts_with($route, 'xinshi_chat.conversation.') ||
      in_array($route, ['xinshi_chat.conversations', 'xinshi_chat.sessions',
        'xinshi_ai.conversation.update', 'xinshi_ai.session.content'], TRUE) ||
      str_starts_with($path, '/api/v3/ai/conversations/') ||
      in_array($path, ['/api/v3/ai/chat-storage', '/api/v2/conversations', '/api/v2/sessions'], TRUE);
  }

  /** {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    // Run after routing so JSON:API aliases are identified by the route name.
    // Disable internal caching before Dynamic Page Cache, then retain headers after core finalization.
    return [KernelEvents::REQUEST => ['onRequest', 30],
      KernelEvents::RESPONSE => [['onResponse', 8], ['onResponse', -1001]]];
  }

}
