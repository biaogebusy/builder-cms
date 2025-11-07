<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Plugin\DiffGenerator;

/**
 * Plugin to diff image fields.
 *
 * @DiffGenerator(
 *   id = "image_field_diff_parser",
 *   label = @Translation("Image Field Diff Parser"),
 *   field_types = {
 *     "image"
 *   },
 * )
 */
class ImageFieldDiffParser extends FileFieldDiffParser {

  /**
   * {@inheritdoc}
   */
  protected function getFieldMetaProperties() {
    return [
      'alt' => (string) $this->t('Alt'),
      'title' => (string) $this->t('Image title'),
    ];
  }

}
