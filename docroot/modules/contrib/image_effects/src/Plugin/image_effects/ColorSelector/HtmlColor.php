<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\image_effects\ColorSelector;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\Attribute\ColorSelector;
use Drupal\image_effects\Plugin\ImageEffectsPluginBase;

/**
 * HTML color selector plugin.
 */
#[ColorSelector(
  id: "html_color",
  title: new TranslatableMarkup("HTML color selector"),
  shortTitle: new TranslatableMarkup("HTML color"),
  help: new TranslatableMarkup("Use an HTML5 color element to select colors."),
)]
class HtmlColor extends ImageEffectsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = []): array {
    return [
      '#type' => 'color',
      '#title'   => $options['#title'] ?? $this->t('Color'),
      '#description' => $options['#description'] ?? NULL,
      '#default_value' => $options['#default_value'],
      '#field_suffix' => $options['#default_value'],
      '#wrapper_attributes' => ['class' => ['image-effects-html-color-selector']],
      '#maxlength' => 7,
      '#size' => 7,
      '#attached' => ['library' => ['image_effects/image_effects.html_color_selector']],
    ];
  }

}
