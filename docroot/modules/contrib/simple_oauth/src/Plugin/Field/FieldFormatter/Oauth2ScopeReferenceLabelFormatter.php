<?php

namespace Drupal\simple_oauth\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'OAuth2 scope reference label' formatter.
 *
 * @FieldFormatter(
 *   id = "oauth2_scope_reference_label",
 *   label = @Translation("Label"),
 *   description = @Translation("Display the label of the referenced OAuth2 scopes."),
 *   field_types = {
 *     "oauth2_scope_reference"
 *   }
 * )
 */
class Oauth2ScopeReferenceLabelFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      // Make sure the scope exists.
      if ($item->isEmpty()) {
        continue;
      }

      $elements[$delta] = [
        '#markup' => $item->scope_id,
      ];
    }
    return $elements;
  }

}
