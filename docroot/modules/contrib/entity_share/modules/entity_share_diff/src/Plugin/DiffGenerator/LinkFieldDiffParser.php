<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Plugin\DiffGenerator;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_share_diff\DiffGenerator\DiffGeneratorPluginBase;

/**
 * Plugin to compare the title and the uris of two link fields.
 *
 * @DiffGenerator(
 *   id = "link_field_diff_parser",
 *   label = @Translation("Link Field Diff Parser"),
 *   field_types = {
 *     "link"
 *   },
 * )
 */
class LinkFieldDiffParser extends DiffGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items, array $remote_field_data = []) {
    $result = [];

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();
        // Compare the link title.
        if (isset($values['title'])) {
          $label = (string) $this->t('Title');
          $result[$field_key][$label] = $values['title'];
        }
        // Compare the uri if exists.
        if (isset($values['uri'])) {
          $label = (string) $this->t('URL');
          $result[$field_key][$label] = $values['uri'];
        }
      }
    }

    return $result;
  }

}
