<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai\Service\AiLogMetadata;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Supplies current, access-checked display metadata to the administrator log view. */
final class AiLogMetadataController extends ControllerBase {

  public function __construct(private readonly AiLogMetadata $metadata) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_ai.log_metadata'));
  }

  public function read(Request $request): JsonResponse {
    try {
      $response = new JsonResponse($this->metadata->read($request->query->all()));
    }
    catch (HttpException $error) {
      $response = new JsonResponse(['code' => $error->getStatusCode() === 403
        ? 'metadata_forbidden' : 'invalid_metadata_query'], $error->getStatusCode());
    }
    $response->headers->set('Cache-Control', 'private, no-store');
    return $response;
  }

}
