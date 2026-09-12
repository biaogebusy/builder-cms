<?php

namespace Drupal\tour\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a tour tip attribute object.
 *
 * Plugin Namespace: Plugin\tour\tip.
 *
 * For a working example, see \Drupal\tour\Plugin\tour\tip\TipPluginText
 *
 * @see \Drupal\tour\TipPluginBase
 * @see \Drupal\tour\TipPluginInterface
 * @see \Drupal\tour\TipPluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Tip extends Plugin {

  /**
   * Constructs a Tip attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $title
   *   The label of the tip.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $title = NULL,
  ) {}

}
