<?php

namespace Drupal\ai_translate\Plugin\FieldTextExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_translate\Attribute\FieldTextExtractor;

/**
 * A field text extractor plugin for 'file' fields.
 */
#[FieldTextExtractor(
  id: "file",
  label: new TranslatableMarkup('File'),
  field_types: [
    'file',
  ],
)]
class FileTextExtractor extends TextFieldExtractor {

  /**
   * {@inheritdoc}
   */
  public function getColumns(): array {
    return ['description'];
  }

}
