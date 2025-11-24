<?php

declare(strict_types=1);

namespace Drupal\advancedqueue\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an AdvancedQueueJobType attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AdvancedQueueJobType extends Plugin {

  /**
   * Constructs a CommercePaymentGateway attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The job type label.
   * @param int $max_retries
   *   (optional) The maximum number of retries.
   * @param int $retry_delay
   *   (optional) The time, in seconds, after which the retried job will become
   *   available to consumers. Defaults to 0, indicating no delay.
   * @param bool $allow_duplicates
   *   Whether duplicate jobs of this type are allowed.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly int $max_retries = 0,
    public readonly int $retry_delay = 10,
    public readonly bool $allow_duplicates = TRUE,
  ) {}

}
