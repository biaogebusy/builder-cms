<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Remove optional API and administration routes before controller construction. */
final class FinanceRouteSubscriber extends RouteSubscriberBase {

  public function __construct(private readonly array $assembly, private readonly array $finance) {}

  protected function alterRoutes(RouteCollection $collection) {
    if (!$this->finance['chat_billing']) $collection->remove('xinshi_ai_billing.quote');
    foreach ($collection as $name => $route) {
      foreach (['credits' => 'xinshi_credits.', 'rewards' => 'xinshi_rewards.', 'billing' => 'xinshi_ai_billing.'] as $feature => $prefix) {
        if (!$this->assembly[$feature] && str_starts_with($name, $prefix)) $collection->remove($name);
      }
    }
  }

}
