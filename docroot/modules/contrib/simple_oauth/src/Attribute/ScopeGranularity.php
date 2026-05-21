<?php

declare(strict_types=1);

namespace Drupal\simple_oauth\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ScopeGranularity attribute for plugin discovery.
 *
 * @see \Drupal\simple_oauth\Plugin\ScopeGranularity\ScopeGranularityInterface
 * @see \Drupal\simple_oauth\Plugin\ScopeGranularity\ScopeGranularityManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ScopeGranularity extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?string $deriver = NULL,
  ) {}

}
