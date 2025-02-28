<?php

namespace Shaper\DataAdaptor;

use Shaper\Transformation\TransformationInterface;

/**
 * Transformation pair that can act as an adaptor between systems.
 *
 * Makes sure that data leaving A transforms into format B, and data arriving
 * from B complies with the format requirements of A.
 *
 * @package Shaper
 */
abstract class DataAdaptorBase implements TransformationInterface, ReversibleTransformationInterface, ReversibleTransformationValidationInterface {

  use DataAdaptorValidatorTrait;
  use DataAdaptorTransformerTrait;

}
