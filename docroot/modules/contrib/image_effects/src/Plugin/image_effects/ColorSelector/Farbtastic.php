<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\image_effects\ColorSelector;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\Attribute\ColorSelector;
use Drupal\image_effects\Plugin\ImageEffectsPluginBase;

/**
 * Farbtastic color selector plugin.
 */
#[ColorSelector(
  id: "farbtastic",
  title: new TranslatableMarkup("Farbtastic color selector"),
  shortTitle: new TranslatableMarkup("Farbtastic"),
  help: new TranslatableMarkup("Use a Farbtastic color picker to select colors."),
)]
class Farbtastic extends ImageEffectsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = []): array {
    return [
      '#type' => 'textfield',
      '#title' => $options['#title'] ?? $this->t('Color'),
      '#description' => $options['#description'] ?? NULL,
      '#default_value' => $options['#default_value'],
      '#field_suffix' => '<div class="farbtastic-colorpicker"></div>',
      '#maxlength' => 7,
      '#size' => 8,
      '#wrapper_attributes' => ['class' => ['image-effects-farbtastic-color-selector']],
      '#attributes' => ['class' => ['image-effects-color-textfield']],
      '#attached' => ['library' => ['image_effects/image_effects.farbtastic_color_selector_v2']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isAvailable(): bool {
    return \Drupal::service('module_handler')->moduleExists('color');
  }

}
