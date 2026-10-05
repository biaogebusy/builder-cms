<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\xinshi_analytics\AnalyticsException;
use Drupal\xinshi_analytics\EvidenceService;
use Drupal\xinshi_analytics\HttpInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Evidence receipts are source attestations, never authorization bearer tokens. */
final class EvidenceController implements ContainerInjectionInterface {

  /** Constructs the controller with the authenticated source service. */
  public function __construct(private readonly EvidenceService $evidence) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_analytics.evidence'));
  }

  /** Performs one count and seals it; this endpoint does not persist a report. */
  public function capture(Request $request): JsonResponse {
    return new JsonResponse($this->evidence->capture(HttpInput::query(HttpInput::read($request))));
  }

  /** Revalidates the receipt using the current account, scope, data and access rules. */
  public function verify(Request $request): JsonResponse {
    $shape = HttpInput::read($request);
    if (!($shape->result ?? NULL) instanceof \stdClass || !is_array($shape->result->rows ?? NULL)) {
      throw new AnalyticsException('invalid_query');
    }
    foreach ($shape->result->rows as $row) {
      if (!$row instanceof \stdClass || !is_array($row->dimensions ?? NULL)) {
        throw new AnalyticsException('invalid_query');
      }
    }
    HttpInput::query($shape->result->query ?? NULL);
    return new JsonResponse($this->evidence->verify(HttpInput::array($shape)));
  }

}
