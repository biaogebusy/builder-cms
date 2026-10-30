<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\xinshi_credits\CreditQuery;
use Drupal\xinshi_credits\CreditValue;
use Drupal\xinshi_credits\CreditWriteRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Credit reads and notification acknowledgements, with service-level authorization. */
final class CreditController implements ContainerInjectionInterface {

  public function __construct(private readonly CreditQuery $query, private readonly CreditWriteRequest $writes) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_credits.query'), $container->get('xinshi_credits.write_request'));
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

  /** Read a managed target without replacing the effective principal. */
  public function userAccount(Request $request, string $user): JsonResponse {
    CreditValue::fields($request->query->all(), []);
    return new JsonResponse($this->query->account($user));
  }

  /** Read sanitized target history with current entity access. */
  public function userTransactions(Request $request, string $user): JsonResponse {
    return new JsonResponse($this->query->transactions($request->query->all(), $user));
  }

  /** Read owned pending notification events. */
  public function notifications(Request $request): JsonResponse {
    CreditValue::fields($request->query->all(), []);
    return new JsonResponse($this->query->notifications());
  }

  /** Confirm one already-issued notification. */
  public function acknowledge(Request $request, string $event): JsonResponse {
    [$body] = $this->writes->parse($request);
    CreditValue::fields($body, []);
    return new JsonResponse($this->query->acknowledge($event));
  }

}
