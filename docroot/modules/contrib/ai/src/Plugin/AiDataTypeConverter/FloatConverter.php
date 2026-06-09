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
  id: 'float',
  label: new TranslatableMarkup('Float'),
  description: new TranslatableMarkup('Upcast a float if numeric'),
)]
class FloatConverter extends AiDataTypeConverterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function appliesToDataType(string $data_type): AppliesResultInterface {
    if ($data_type === 'float') {
      return AppliesResult::applicable();
    }
    return AppliesResult::notApplicable('The data type is not an integer.');
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToValue(string $data_type, mixed $value): AppliesResultInterface {
    if (is_numeric($value)) {
      return AppliesResult::applicable();
    }
    return AppliesResult::notApplicable('The value is not a string or not numeric.');
  }

  /**
   * {@inheritdoc}
   */
  public function convert(string $data_type, mixed $value): mixed {
    return (float) $value;
  }

}
