<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_knowledge\Service\KnowledgeCatalog;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Read-only frontend catalog using the authenticated host's effective account. */
final class KnowledgeCatalogController extends ControllerBase {

  public function __construct(private readonly KnowledgeCatalog $catalog) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_knowledge.catalog'));
  }

  public function listing(Request $request): JsonResponse {
    if (!$this->currentUser()->isAuthenticated() ||
        !$this->currentUser()->hasPermission('view xinshi knowledge')) {
      return $this->respond(['error' => 'access_denied'], 403);
    }
    $before = $request->query->all()['before'] ?? '0';
    if (!is_string($before) || !preg_match('/^(0|[1-9][0-9]{0,9})$/D', $before)) {
      return $this->respond(['error' => 'invalid_input'], 400);
    }
    return $this->respond($this->catalog->listing($this->currentUser(), (int) $before));
  }

  public function detail(string $uuid): JsonResponse {
    if (!$this->currentUser()->isAuthenticated() ||
        !$this->currentUser()->hasPermission('view xinshi knowledge')) {
      return $this->respond(['error' => 'access_denied'], 403);
    }
    $item = $this->catalog->detail($uuid, $this->currentUser());
    return $item ? $this->respond($item) : $this->respond(['error' => 'not_found'], 404);
  }

  /** Return only counts authorized for the effective Cookie or OAuth account. */
  public function summary(): JsonResponse {
    if (!$this->currentUser()->isAuthenticated() ||
        !$this->currentUser()->hasPermission('view xinshi knowledge')) {
      return $this->respond(['error' => 'access_denied'], 403);
    }
    return $this->respond($this->catalog->summary($this->currentUser()));
  }

  private function respond(array $data, int $status = 200): JsonResponse {
    return new JsonResponse($data, $status, ['Cache-Control' => 'private, no-store']);
  }

}
