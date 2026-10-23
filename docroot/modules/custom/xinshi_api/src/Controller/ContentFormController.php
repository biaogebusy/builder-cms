<?php

declare(strict_types=1);

namespace Drupal\xinshi_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_api\ContentFormService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Read-only content form descriptions for the authenticated caller. */
final class ContentFormController extends ControllerBase {

  public function __construct(private readonly ContentFormService $forms) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container) {
    return new static($container->get('xinshi_api.content_forms'));
  }

  /** Lists types with current create access. */
  public function types(): JsonResponse {
    return new JsonResponse(['data' => $this->forms->types()]);
  }

  /** Describes node create or edit access in the requested language. */
  public function node(string $bundle, Request $request, ?string $uuid = NULL): JsonResponse {
    return new JsonResponse($this->forms->node($bundle, $uuid, $request->query->get('lang')));
  }

  /** Describes the permitted comment body format. */
  public function comment(string $bundle, ?string $uuid = NULL): JsonResponse {
    return new JsonResponse($this->forms->comment($bundle, $uuid));
  }

}
