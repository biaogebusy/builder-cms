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
  id: 'integer',
  label: new TranslatableMarkup('Integer'),
  description: new TranslatableMarkup('Upcast an integer if numeric.'),
)]
class IntegerConverter extends AiDataTypeConverterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function appliesToDataType(string $data_type): AppliesResultInterface {
    if ($data_type === 'integer') {
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
    return (int) $value;
  }

}
