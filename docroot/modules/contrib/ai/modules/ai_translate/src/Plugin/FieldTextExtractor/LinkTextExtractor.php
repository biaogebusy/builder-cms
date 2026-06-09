<?php

namespace Drupal\ai_translate\Plugin\FieldTextExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_translate\Attribute\FieldTextExtractor;

/**
 * A field text extractor plugin for 'link' fields.
 */
#[FieldTextExtractor(
  id: "link",
  label: new TranslatableMarkup('Link'),
  field_types: [
    'link',
  ],
)]
class LinkTextExtractor extends TextFieldExtractor {

  /**
   * {@inheritdoc}
   */
  public function getColumns(): array {
    return ['title'];
  }

}
