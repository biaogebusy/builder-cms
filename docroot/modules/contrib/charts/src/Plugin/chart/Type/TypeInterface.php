<?php

namespace Drupal\charts\Plugin\chart\Type;

/**
 * Defines an interface for Chart type plugins.
 */
interface TypeInterface {

  /**
   * Gets the chart type ID.
   *
   * @return string
   *   The chart type ID.
   */
  public function getId();

  /**
   * Gets the chart type label.
   *
   * @return string
   *   The chart type label.
   */
  public function getLabel();

  /**
   * Gets the chart type axis.
   *
   * @return string
   *   The chart type axis.
   */
  public function getAxis();

  /**
   * Gets whether the chart type axis is inverted.
   *
   * @return bool
   *   TRUE if the chart type axis is inverted, FALSE otherwise.
   */
  public function isAxisInverted();

  /**
   * Gets whether the chart type axis supports stacking.
   *
   * @return bool
   *   TRUE if the chart type axis supports stacking, FALSE otherwise.
   */
  public function supportStacking();

}
