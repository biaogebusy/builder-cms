<?php

declare(strict_types=1);

namespace Drupal\ai\Base;

use Drupal\Component\Plugin\PluginBase;
use Drupal\ai\DataTypeConverter\AppliesResultInterface;
use Drupal\ai\Plugin\AiDataTypeConverter\AiDataTypeConverterInterface;

/**
 * Base class for ai_data_type_converter plugins.
 */
abstract class AiDataTypeConverterPluginBase extends PluginBase implements AiDataTypeConverterInterface {

  /**
   * {@inheritdoc}
   */
  final public function applies(string $data_type, mixed $value): AppliesResultInterface {
    $result = $this->appliesToDataType($data_type);
    if ($result->applies() && $result->valid()) {
      return $this->appliesToValue($data_type, $value);
    }
    return $result;
  }

}
