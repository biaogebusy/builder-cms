<?php

namespace Drupal\entity_share_client\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Entity Share Client Authorization attribute object.
 *
 * Plugin namespace: ClientAuthorization.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ClientAuthorization extends Plugin {

  /**
   * Constructs a ClientAuthorization attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The plugin label.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
  ) {
  }

}
