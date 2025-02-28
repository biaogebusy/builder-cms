<?php

namespace Shaper\DataAdaptor;

use Shaper\Transformation\TransformationValidationInterface;
use Shaper\Util\Context;

interface ReversibleTransformationValidationInterface extends TransformationValidationInterface {

  /**
   * Checks if the shape of the transformed data is valid for internal use.
   *
   * @param mixed $data
   *   The data in the internal format.
   * @param \Shaper\Util\Context $context
   *   Additional information that will affect how the data is transformed.
   *
   * @return bool
   *   TRUE if the format is valid.
   */
  public function conformsToInternalShape($data, Context $context = NULL);

  /**
   * The validator for the internal data.
   *
   * @return \Shaper\Validator\ValidateableInterface
   */
  public function getInternalValidator();

}
