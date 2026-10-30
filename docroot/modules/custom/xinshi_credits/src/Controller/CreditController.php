<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\xinshi_credits\CreditQuery;
use Drupal\xinshi_credits\CreditValue;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Read-only own-account endpoints; authorization remains in the query service. */
final class CreditController implements ContainerInjectionInterface {

  public function __construct(private readonly CreditQuery $query) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_credits.query'));
  }

  /** Read the authenticated principal's balance without creating an account. */
  public function account(Request $request): JsonResponse {
    CreditValue::fields($request->query->all(), []);
    return new JsonResponse($this->query->account());
  }

  /** Read a bounded window of immutable transactions. */
  public function transactions(Request $request): JsonResponse {
    return new JsonResponse($this->query->transactions($request->query->all()));
  }

}
