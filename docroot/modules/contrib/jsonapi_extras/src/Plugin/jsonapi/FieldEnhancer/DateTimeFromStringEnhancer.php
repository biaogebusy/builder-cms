<?php

namespace Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\jsonapi_extras\Plugin\DateTimeEnhancerBase;
use Shaper\Util\Context;

/**
 * Perform additional manipulations to datetime fields.
 *
 * @ResourceFieldEnhancer(
 *   id = "date_time_from_string",
 *   label = @Translation("Date Time (Date Time field)"),
 *   description = @Translation("Formats a date based the configured date format for date fields."),
 *   dependencies = {"datetime"}
 * )
 */
class DateTimeFromStringEnhancer extends DateTimeEnhancerBase {

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    $configuration = $this->getConfiguration();

    $reformat = function ($input) use ($configuration) {
      $storage_timezone = new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE);
      $date = new \DateTime($input, $storage_timezone);

      $output_timezone = new \DateTimezone(date_default_timezone_get());
      $date->setTimezone($output_timezone);
      $output = $date->format($configuration['dateTimeFormat']);

      return $output;
    };

    $result = is_array($data) ? array_map($reformat, $data) : $reformat($data);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context) {
    $reformat = function ($input) {
      $date = new \DateTime($input);

      // Adjust the date for storage.
      $storage_timezone = new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE);
      $date->setTimezone($storage_timezone);
      $output = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

      return $output;
    };

    $result = is_array($data) ? array_map($reformat, $data) : $reformat($data);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    $baseType = parent::getOutputJsonSchema();

    return [
      "anyOf" => [
        $baseType,
        ["type" => "array", "items" => $baseType],
        [
          "type" => "object",
          "properties" => [
            "value" => $baseType,
            "end_value" => $baseType,
          ],
        ],
      ],
    ];
  }

}
