<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiDataTypeConverter;

use Drupal\ai\DataTypeConverter\AppliesResultInterface;

/**
 * Interface for ai_data_type_converter plugins.
 */
interface AiDataTypeConverterInterface {

  /**
   * Determines if the converter applies to a data type and value.
   *
   * @param string $data_type
   *   The data type.
   * @param mixed $value
   *   The value.
   *
   * @return \Drupal\ai\DataTypeConverter\AppliesResultInterface
   *   The result of the applicability check.
   */
  public function applies(string $data_type, mixed $value): AppliesResultInterface;

  /**
   * Determines if converter applies to a data type.
   *
   * @param string $data_type
   *   The data type.
   *
   * @return \Drupal\ai\DataTypeConverter\AppliesResultInterface
   *   The result of the applicability check.
   */
  public function appliesToDataType(string $data_type): AppliesResultInterface;

  /**
   * Determines if converter applies to a value.
   *
   * Because the datatype may provide details for validating the value, we also
   * provide this param to the method.
   *
   * @param string $data_type
   *   The data type.
   * @param mixed $value
   *   The value.
   *
   * @return \Drupal\ai\DataTypeConverter\AppliesResultInterface
   *   The result of the applicability check.
   */
  public function appliesToValue(string $data_type, mixed $value): AppliesResultInterface;

  /**
   * Converts a value to match data type expected in ContextDefinition.
   *
   * This method is used to convert a value to match the data type expected in
   * the data type. For example, if the data type expects an
   * entity, this method will convert the value to an entity object.
   *
   * @param string $data_type
   *   The data type.
   * @param mixed $value
   *   The value.
   *
   * @return mixed
   *   The converted value.
   */
  public function convert(string $data_type, mixed $value): mixed;

}
