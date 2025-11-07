<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Plugin\DiffGenerator;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\entity_share_diff\DiffGenerator\DiffGeneratorPluginBase;

/**
 * Plugin to diff comment fields.
 *
 * @DiffGenerator(
 *   id = "comment_field_diff_parser",
 *   label = @Translation("Comment Field Diff Parser"),
 *   field_types = {
 *     "comment"
 *   },
 * )
 */
class CommentFieldDiffParser extends DiffGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items, array $remote_field_data = []) {
    $result = [];

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();

        // A more human friendly representation.
        if (isset($values['status'])) {
          switch ($values['status']) {
            case CommentItemInterface::OPEN:
              $result[$field_key] = (string) $this->t('Comments for this entity are open.');
              break;

            case CommentItemInterface::CLOSED:
              $result[$field_key] = (string) $this->t('Comments for this entity are closed.');
              break;

            case CommentItemInterface::HIDDEN:
              $result[$field_key] = (string) $this->t('Comments for this entity are hidden.');
              break;
          }
        }
      }
    }

    return $result;
  }

}
