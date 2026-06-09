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
  id: 'boolean',
  label: new TranslatableMarkup('Boolean'),
  description: new TranslatableMarkup('Upcast a boolean from "true", "false", "1" or "0".'),
)]
class BoolConverter extends AiDataTypeConverterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function appliesToDataType(string $data_type): AppliesResultInterface {
    if ($data_type === 'boolean') {
      return AppliesResult::applicable();
    }
    return AppliesResult::notApplicable('The data type is not a boolean.');
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToValue(string $data_type, mixed $value): AppliesResultInterface {
    // Check if value is a boolean or can be converted to a boolean.
    if (is_bool($value) || (is_string($value) && in_array(strtolower($value), ['true', 'false', '1', '0'], TRUE))) {
      return AppliesResult::applicable();
    }
    return AppliesResult::notApplicable('The value cannot be converted to a boolean');
  }

  /**
   * {@inheritdoc}
   */
  public function convert(string $data_type, mixed $value): mixed {
    if (is_string($value)) {
      return strtolower($value) === 'true' || $value === '1';
    }
    return (bool) $value;
  }

}
