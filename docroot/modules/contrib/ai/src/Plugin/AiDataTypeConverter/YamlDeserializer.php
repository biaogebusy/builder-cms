<?php

namespace Drupal\ai\Plugin\AiDataTypeConverter;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiDataTypeConverter;
use Drupal\ai\Base\AiDataTypeConverterPluginBase;
use Drupal\ai\DataTypeConverter\AppliesResult;
use Drupal\ai\DataTypeConverter\AppliesResultInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the ai_data_type_converter for YAML.
 */
#[AiDataTypeConverter(
  id: 'yaml_deserializer',
  label: new TranslatableMarkup('Yaml Deserializer'),
  description: new TranslatableMarkup('Any serialized YAML string.'),
  weight: -100,
)]
class YamlDeserializer extends AiDataTypeConverterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function appliesToDataType(string $data_type): AppliesResultInterface {
    if ($data_type === 'string') {
      return AppliesResult::notApplicable('"string" data types should not be parsed as yaml');
    }
    return AppliesResult::applicable();
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToValue(string $data_type, mixed $value): AppliesResultInterface {
    if (!is_string($value)) {
      return AppliesResult::notApplicable('The value is not a valid YAML string.');
    }
    try {
      Yaml::parse($value);
      return AppliesResult::applicable();
    }
    catch (ParseException $e) {
      return AppliesResult::notApplicable('The value is not valid YAML: ' . $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function convert(string $data_type, mixed $value): mixed {
    return Yaml::parse($value);
  }

}
