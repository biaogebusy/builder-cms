<?php

declare(strict_types=1);

namespace Drupal\xinshi_shooting_plan\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_shooting_plan\Service\ShootingDocumentAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Atomic working-copy updates for the independent shooting plan resource. */
final class ShootingDocumentController extends ControllerBase {

  public function __construct(private readonly ShootingDocumentAccess $documents) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_shooting_plan.documents'));
  }

  /** PATCH requires current source access and route-level Cookie CSRF protection. */
  public function update(string $plan_uuid, Request $request): JsonResponse {
    $headers = ['Cache-Control' => 'private, no-store'];
    if (strlen($request->getContent()) > 3 * 1024 * 1024) {
      return new JsonResponse(['message' => 'shooting_document_too_large'], 413, $headers);
    }
    $input = json_decode($request->getContent(), TRUE);
    if (!is_array($input) || array_diff(array_keys($input), ['expectedHash', 'content']) ||
      !is_string($input['expectedHash'] ?? NULL) || !is_string($input['content'] ?? NULL)) {
      return new JsonResponse(['message' => 'shooting_update_invalid'], 422, $headers);
    }
    try {
      $result = $this->documents->update($plan_uuid, $input['expectedHash'], $input['content']);
      return new JsonResponse($result, $result['updated'] ? 200 : 409, $headers);
    }
    catch (\Drupal\xinshi_ai\Exception\InputImageAccessException) {
      return new JsonResponse(['message' => 'shooting_reference_unavailable'], 403, $headers);
    }
    catch (HttpExceptionInterface $error) {
      return new JsonResponse(['message' => $error->getMessage() ?: 'shooting_request_denied'],
        $error->getStatusCode(), $headers);
    }
    catch (\Throwable) {
      return new JsonResponse(['message' => 'shooting_save_failed'], 503, $headers);
    }
  }

}
