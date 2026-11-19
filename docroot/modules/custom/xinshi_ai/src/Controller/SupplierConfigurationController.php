<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai\Service\SupplierConfiguration;
use Drupal\xinshi_ai_runtime\Service\ServiceRequestAuthentication;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/** Publishes secrets only to an authenticated Node service; no user session grants access. */
final class SupplierConfigurationController extends ControllerBase {

  private const HEADERS = ['Cache-Control' => 'private, no-store', 'Pragma' => 'no-cache'];

  public function __construct(
    private readonly ServiceRequestAuthentication $authentication,
    private readonly SupplierConfiguration $suppliers,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_ai_runtime.service_authentication'),
      $container->get('xinshi_ai.supplier_configuration'));
  }

  public function read(Request $request): JsonResponse {
    if ($request->getMethod() !== 'GET' || $request->query->count() || $request->getContent() !== '') {
      return new JsonResponse(['code' => 'invalid_request'], 400, self::HEADERS);
    }
    try {
      $this->authentication->authenticate($request);
    }
    catch (UnauthorizedHttpException) {
      return new JsonResponse(['code' => 'service_unauthenticated'], 401, self::HEADERS);
    }
    catch (\Throwable) {
      return new JsonResponse(['code' => 'supplier_configuration_unavailable'], 503, self::HEADERS);
    }
    try {
      return new JsonResponse($this->suppliers->snapshot(), 200, self::HEADERS);
    }
    catch (\Throwable) {
      return new JsonResponse(['code' => 'supplier_configuration_unavailable'], 503, self::HEADERS);
    }
  }

}
