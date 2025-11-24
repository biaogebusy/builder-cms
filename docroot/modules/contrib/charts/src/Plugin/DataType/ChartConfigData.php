<?php

namespace Drupal\charts\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\TypedData;

/**
 * Defines the 'chart_config' data type.
 *
 * This data type represents a chart configuration object,
 * which can be used to store and manipulate chart settings.
 */
#[DataType(
  id: "chart_config",
  label: new TranslatableMarkup("Chart config"),
  description: new TranslatableMarkup("A chart configuration")
)]
class ChartConfigData extends TypedData {

  /**
   * Cached processed value.
   *
   * @var string
   */
  protected $value;

}
