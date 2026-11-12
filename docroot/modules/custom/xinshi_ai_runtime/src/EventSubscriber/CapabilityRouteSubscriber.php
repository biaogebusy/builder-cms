<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_runtime\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Unavailable modules do not expose working-looking endpoints. */
final class CapabilityRouteSubscriber extends RouteSubscriberBase {

  public function __construct(private readonly array $features) {}

  protected function alterRoutes(RouteCollection $collection) {
    if (!$this->features['image_billing']) $collection->remove('xinshi_ai.image_job.quote');
    if ($this->features['metering'] === 'off') {
      foreach ($collection->all() as $name => $route) {
        if (str_starts_with($name, 'xinshi_ai_usage.')) $collection->remove($name);
      }
    }
  }

}
