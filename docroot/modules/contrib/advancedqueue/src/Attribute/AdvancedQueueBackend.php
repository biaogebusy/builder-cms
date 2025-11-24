<?php

declare(strict_types=1);

namespace Drupal\advancedqueue\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an AdvancedQueueBackend attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AdvancedQueueBackend extends Plugin {

  /**
   * Constructs a CommercePaymentGateway attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the Advanced Queue backend.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
  ) {}

}
