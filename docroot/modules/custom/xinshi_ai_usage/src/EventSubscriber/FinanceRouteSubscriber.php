<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Image usage stays installed even when image quotations are absent. */
final class FinanceRouteSubscriber extends RouteSubscriberBase {

  public function __construct(private readonly array $features) {}

  protected function alterRoutes(RouteCollection $collection) {
    if (!$this->features['image_billing']) $collection->remove('xinshi_ai.image_job.quote');
  }

}
