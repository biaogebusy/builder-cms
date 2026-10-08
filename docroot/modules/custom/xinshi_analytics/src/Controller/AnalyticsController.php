<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\xinshi_analytics\AnalyticsException;
use Drupal\xinshi_analytics\CountService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\xinshi_analytics\HttpInput;

/** Authenticated, read-only HTTP boundary; query semantics remain in the service. */
final class AnalyticsController implements ContainerInjectionInterface {

  /** Constructs the controller with the configured count source. */
  public function __construct(private readonly CountService $counts) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_analytics.counts'));
  }

  /** Returns the capability of this effective identity, bound to its UID. */
  public function capabilities(): JsonResponse {
    return new JsonResponse($this->counts->capabilities());
  }

  /** Discovers explicit datasets without accepting identity or field overrides. */
  public function datasets(Request $request): JsonResponse {
    $params = $request->query->all();
    if (array_diff(array_keys($params), ['language', 'noCache'])
      || !is_string($params['language'] ?? NULL)) {
      throw new AnalyticsException('invalid_query');
    }
    return new JsonResponse($this->counts->discover($params['language']));
  }

  /** POST carries structured query data only; it never persists or mutates data. */
  public function count(Request $request): JsonResponse {
    return new JsonResponse($this->counts->count(HttpInput::query(HttpInput::read($request))));
  }

}
