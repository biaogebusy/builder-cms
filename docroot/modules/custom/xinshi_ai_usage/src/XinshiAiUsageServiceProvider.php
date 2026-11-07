<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/** Usage remains available without any financial module. */
final class XinshiAiUsageServiceProvider extends ServiceProviderBase {

  /** {@inheritdoc} */
  public function register(ContainerBuilder $container) {
    $container->setParameter('xinshi_site_finance', [
      'version' => 1, 'credits' => FALSE, 'chat_billing' => FALSE, 'image_billing' => FALSE,
      'billing_recovery' => FALSE,
    ]);
  }

}
