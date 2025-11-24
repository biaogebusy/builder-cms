<?php

declare(strict_types=1);

namespace Drupal\diff\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a DiffLayoutBuilder annotation object.
 *
 * Diff builders handle how fields are compared by the diff module.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class DiffLayoutBuilder extends Plugin {

  /**
   * Constructs a DiffLayoutBuilder attribute.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
  ) {}

}
