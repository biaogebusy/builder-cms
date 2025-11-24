<?php

declare(strict_types=1);

namespace Drupal\private_message\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The private message configuration form plugin attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class PrivateMessageConfigForm extends Plugin {

  /**
   * Constructs a new PrivateMessageConfigForm attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $name
   *   The name of the form plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $name,
  ) {}

}
