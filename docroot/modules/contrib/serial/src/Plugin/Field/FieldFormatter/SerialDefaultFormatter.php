<?php

namespace Drupal\serial\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'serial_default_formatter'.
 *
 * @FieldFormatter(
 *   id = "serial_default_formatter",
 *   label = @Translation("Serial default"),
 *   field_types = {
 *     "serial"
 *   }
 * )
 */
class SerialDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      // Render output using serial_default theme.
      $source = [
        '#theme' => 'serial_default',
        '#serial_id' => $item->value,
      ];
      $elements[$delta] = [
        '#markup' => \Drupal::service('renderer')->renderPlain($source),
      ];
    }
    return $elements;
  }

}
