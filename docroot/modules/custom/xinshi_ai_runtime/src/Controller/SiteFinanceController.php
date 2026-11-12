<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_runtime\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Publish public module capabilities without opening accounts or billing services. */
final class SiteFinanceController implements ContainerInjectionInterface {

  public function __construct(private readonly array $features) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->getParameter('xinshi_site_finance'));
  }

  public function get(): JsonResponse {
    return new JsonResponse($this->features, 200, ['Cache-Control' => 'private, no-store']);
  }

}
