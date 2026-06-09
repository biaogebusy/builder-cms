<?php

namespace Drupal\ai\Plugin\AiDataTypeConverter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiDataTypeConverter;
use Drupal\ai\Base\AiDataTypeConverterPluginBase;
use Drupal\ai\DataTypeConverter\AppliesResult;
use Drupal\ai\DataTypeConverter\AppliesResultInterface;

/**
 * Plugin implementation of the ai_data_type_converter.
 */
#[AiDataTypeConverter(
  id: 'json_deserializer',
  label: new TranslatableMarkup('Json Deserializer'),
  description: new TranslatableMarkup('Any serialized JSON string.'),
  weight: -100,
)]
class JsonDeserializer extends AiDataTypeConverterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function appliesToDataType(string $data_type): AppliesResultInterface {
    if ($data_type === 'string') {
      return AppliesResult::notApplicable('"string" data types should not be parsed as json');
    }
    return AppliesResult::applicable();
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToValue(string $data_type, mixed $value): AppliesResultInterface {
    if (!is_string($value)) {
      return AppliesResult::notApplicable('The value is not valid JSON');
    }
    // @todo Replace with json_validate after PHP 8.3
    Json::decode($value);
    if (json_last_error() === JSON_ERROR_NONE) {
      return AppliesResult::applicable();
    }
    return AppliesResult::notApplicable('The value is not valid JSON');
  }

  /**
   * {@inheritdoc}
   */
  public function convert(string $data_type, mixed $value): mixed {
    return Json::decode($value);
  }

}
