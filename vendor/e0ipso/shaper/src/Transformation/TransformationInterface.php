<?php

namespace Shaper\Transformation;

use Shaper\Util\Context;

/**
 * Interface for transformation classes.
 *
 * @package Shaper
 */
interface TransformationInterface {

  /**
   * Basic transformation from a shape into another shape.
   *
   * This method will validate data coming in and going out using validators.
   *
   * @param mixed $data
   *   The data to transform.
   *
   * @param \Shaper\Util\Context $context
   *   Additional information that will affect how the data is transformed.
   *
   * @return mixed
   *   The data in the new shape.
   *
   * @throws \TypeError
   *   When the transformation cannot be applied.
   */
  public function transform($data, Context $context = NULL);

}
