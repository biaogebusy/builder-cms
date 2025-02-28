<?php

namespace Drupal\xinshi_editor\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\text\Plugin\Field\FieldFormatter\TextDefaultFormatter;

/**
 * Implementation of the 'json_editor' formatter.
 *
 * @FieldFormatter(
 *   id = "json_foramtter",
 *   label = @Translation("Json Formatter"),
 *   field_types = {
 *     "text_long",
 *     "text_with_summary"
 *   }
 * )
 */
class JsonFormatter extends TextDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#theme' => 'json_formatter',
        '#value' => $item->value,
        '#attached' => [
          'library' => 'xinshi_editor/formatter',
        ],
      ];
    }
    return $elements;
  }
}
