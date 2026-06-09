<?php

namespace Drupal\ai\Plugin\AiDataTypeConverter;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiDataTypeConverter;
use Drupal\ai\Base\AiDataTypeConverterPluginBase;
use Drupal\ai\DataTypeConverter\AppliesResult;
use Drupal\ai\DataTypeConverter\AppliesResultInterface;

/**
 * Plugin implementation of the ai_data_type_converter.
 */
#[AiDataTypeConverter(
  id: 'string',
  label: new TranslatableMarkup('String'),
  description: new TranslatableMarkup('Upcast a value to a string.'),
)]
class StringConverter extends AiDataTypeConverterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function appliesToDataType(string $data_type): AppliesResultInterface {
    if ($data_type === 'string') {
      return AppliesResult::applicable();
    }
    return AppliesResult::notApplicable('The data type is not an integer.');
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToValue(string $data_type, mixed $value): AppliesResultInterface {
    if ($value === NULL || is_scalar($value) || (is_object($value) && (method_exists($value, '__toString') || ($value instanceof \Stringable)))) {
      return AppliesResult::applicable();
    }
    return AppliesResult::notApplicable('The value cannot be converted to a string');
  }

  /**
   * {@inheritdoc}
   */
  public function convert(string $data_type, mixed $value): mixed {
    return (string) $value;
  }

}
