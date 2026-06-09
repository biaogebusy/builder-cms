<?php

namespace Drupal\Tests\ai\Attributes;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an attribute class for metadata around tests exposed in the UI.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AiTestUi {

  /**
   * Construct the attribute.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The human friendly title for the test, used in menus and page titles.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   An optional description for the test, used in menus.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $intro
   *   An optional intro to the test, shown in the run test UI.
   */
  public function __construct(
    public readonly TranslatableMarkup $title,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?TranslatableMarkup $intro = NULL,
  ) {}

}
