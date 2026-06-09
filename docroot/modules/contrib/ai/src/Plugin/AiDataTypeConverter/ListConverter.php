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
 * Plugin implementation of the ai_data_type_converter.
 */
#[AiDataTypeConverter(
  id: 'list',
  label: new TranslatableMarkup('List'),
  description: new TranslatableMarkup('Convert a value to a list from quoted comma csv string.'),
  weight: -200,
)]
class ListConverter extends AiDataTypeConverterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function appliesToDataType(string $data_type): AppliesResultInterface {
    if ($data_type === 'list') {
      return AppliesResult::applicable();
    }
    return AppliesResult::notApplicable('The data type is not a list.');
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToValue(string $data_type, mixed $value): AppliesResultInterface {
    // If value is already an indexed array, return not applicable.
    if (is_array($value) && (empty($value) || isset($value[0]))) {
      return AppliesResult::notApplicable('The value is already an indexed array.');
    }
    if (is_string($value)) {
      // If json, and json returns an indexed array, defer to JSON deserializer.
      $decoded = json_decode($value, TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        if (empty($decoded) || isset($decoded[0])) {
          return AppliesResult::notApplicable('The value is JSON and should be handled by the JSON deserializer.');
        }
      }
      // If YAML, and YAML returns an indexed array, defer to YAML deserializer.
      try {
        $parsed = Yaml::parse($value);
        if (is_array($parsed) && (empty($parsed) || isset($parsed[0]))) {
          return AppliesResult::notApplicable('The value is YAML and should be handled by the YAML deserializer.');
        }
      }
      catch (ParseException $e) {
      }
    }
    return AppliesResult::applicable();
  }

  /**
   * {@inheritdoc}
   */
  public function convert(string $data_type, mixed $value): array {
    if ($value === NULL || $value === '') {
      $value = [];
    }
    // Convert Traversable to array early.
    elseif ($value instanceof \Traversable) {
      $value = iterator_to_array($value, FALSE);
    }
    elseif (is_string($value)) {
      // Try CSV (handles quoted commas).
      $csv = str_getcsv(trim($value));
      // If CSV produced multiple items OR the single item contained a comma.
      if (count($csv) > 1 || str_contains(trim($value), ',')) {
        // Trim and drop empty segments.
        $value = array_values(array_filter(array_map('trim', $csv), 'strlen'));
      }
    }
    // Ensure the value is a numerical array.
    if (!is_array($value) || (!empty($value) && !isset($value[0]))) {
      $value = [$value];
    }
    return $value;
  }

}
