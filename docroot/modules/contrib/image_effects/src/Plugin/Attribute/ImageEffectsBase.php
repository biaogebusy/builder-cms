<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Abstract class for image_effects plugin attributes.
 */
abstract class ImageEffectsBase extends Plugin {

  /**
   * ImageEffectsBase constructor.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The title of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $shortTitle
   *   The short title of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $help
   *   An informative description of the plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $title,
    public readonly TranslatableMarkup $shortTitle,
    public readonly TranslatableMarkup $help,
  ) {}

}
