<?php

namespace Shaper\Transformation;

/**
 * Base implementation for transformation classes.
 *
 * @package Shaper
 */
abstract class TransformationBase implements TransformationInterface, TransformationValidationInterface {

  use TransformationValidationTrait;
  use TransformationTransformerTrait;

}
