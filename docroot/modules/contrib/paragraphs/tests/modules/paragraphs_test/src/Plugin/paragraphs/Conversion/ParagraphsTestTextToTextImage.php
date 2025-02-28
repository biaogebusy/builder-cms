<?php

namespace Drupal\paragraphs_test\Plugin\paragraphs\Conversion;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsConversionBase;

/**
 * Provides a Paragraphs conversion plugin.
 *
 * @ParagraphsConversion(
 *   id = "paragraphs_test_text_to_text_image",
 *   label = @Translation("Convert to Text Image (No form)"),
 *   source_type = "text",
 *   target_type = {"text_image"}
 * )
 */
class ParagraphsTestTextToTextImage extends ParagraphsConversionBase {

  /**
   * {@inheritdoc}
   */
  public function submitConversion(array $settings, ParagraphInterface $original_paragraph, array $converted_paragraphs = NULL) {
    $text = $original_paragraph->get('field_text_demo')->value;
    return [
      [
        'type' => 'text_image',
        'field_text_demo' => [
          'value' => $text,
        ],
      ],
    ];
  }

}
