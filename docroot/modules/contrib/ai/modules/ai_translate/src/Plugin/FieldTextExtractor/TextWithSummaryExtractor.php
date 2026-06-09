<?php

namespace Drupal\ai_translate\Plugin\FieldTextExtractor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_translate\Attribute\FieldTextExtractor;

/**
 * A field text extractor plugin for 'text_with_summary' fields.
 */
#[FieldTextExtractor(
  id: "text_with_summary",
  label: new TranslatableMarkup('Text with summary'),
  field_types: [
    'text_with_summary',
  ],
)]
class TextWithSummaryExtractor extends TextFieldExtractor {

  /**
   * {@inheritdoc}
   */
  public function getColumns(): array {
    return ['value', 'summary'];
  }

}
