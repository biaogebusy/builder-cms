<?php

declare(strict_types=1);

namespace Drupal\xinshi_shooting_plan\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_shooting_plan\Service\ShootingSketches;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Account-bound reads and explicit image submissions for the storyboard application. */
final class ShootingSketchController extends ControllerBase {

  public function __construct(private readonly ShootingSketches $sketches) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_shooting_plan.sketches'));
  }

  /** GET reauthorizes job, asset, media and file access without resubmitting any work. */
  public function read(string $chat_uuid, string $session_uuid): JsonResponse {
    return $this->respond(fn(): array => $this->sketches->read($chat_uuid, $session_uuid));
  }

  /** POST is protected by both route CSRF/permissions and source document authorization. */
  public function submit(string $chat_uuid, string $session_uuid, Request $request): JsonResponse {
    if (strlen($request->getContent()) > 4096) {
      return new JsonResponse(['message' => 'shooting_submission_too_large'], 413,
        ['Cache-Control' => 'private, no-store']);
    }
    $body = json_decode($request->getContent(), TRUE);
    if (!is_array($body) || array_is_list($body)) {
      return new JsonResponse(['message' => 'shooting_submission_invalid'], 400,
        ['Cache-Control' => 'private, no-store']);
    }
    return $this->respond(fn(): array => $this->sketches->submit($chat_uuid, $session_uuid, $body), 202);
  }

  private function respond(callable $operation, int $status = 200): JsonResponse {
    $headers = ['Cache-Control' => 'private, no-store'];
    try {
      return new JsonResponse(['sketches' => (object) $operation()], $status, $headers);
    }
    catch (HttpExceptionInterface $error) {
      return new JsonResponse(['message' => $error->getMessage() ?: 'shooting_request_denied'],
        $error->getStatusCode(), $headers);
    }
    catch (\Throwable) {
      return new JsonResponse(['message' => 'shooting_request_failed'], 503, $headers);
    }
  }

}
