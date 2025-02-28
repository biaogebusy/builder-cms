<?php

namespace Drupal\json_editor\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\text\Plugin\Field\FieldFormatter\TextDefaultFormatter;

/**
 * Implementation of the 'json_editor' formatter.
 *
 * @FieldFormatter(
 *   id = "json_editor",
 *   label = @Translation("Json Editor"),
 *   field_types = {
 *     "text_long",
 *     "text_with_summary"
 *   }
 * )
 */
class JsonEditorFormatter extends TextDefaultFormatter{

    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode){
        $elements = [];

        foreach ($items as $delta => $item) {
          $elements[$delta] = [
            '#theme' => 'json_editor',
            '#attached' => [
              'library' => 'json_editor/json-editor',
              'drupalSettings' => [
                'json_editor' => [
                  Json::decode($item->value)
                ]
              ]
            ]
          ];
        }
        return $elements;
    }
}