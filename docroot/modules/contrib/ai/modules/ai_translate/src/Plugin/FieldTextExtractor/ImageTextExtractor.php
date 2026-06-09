<?php

namespace Drupal\ai_translate\Plugin\FieldTextExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_translate\Attribute\FieldTextExtractor;

/**
 * A field text extractor plugin for 'image' fields.
 */
#[FieldTextExtractor(
  id: "image",
  label: new TranslatableMarkup('Image'),
  field_types: [
    'image',
  ],
)]
class ImageTextExtractor extends TextFieldExtractor {

  /**
   * {@inheritdoc}
   */
  public function getColumns(): array {
    return ['alt', 'title'];
  }

}
