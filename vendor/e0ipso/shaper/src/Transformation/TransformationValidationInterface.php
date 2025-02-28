<?php

namespace Shaper\Transformation;

use Shaper\Util\Context;

interface TransformationValidationInterface {

  /**
   * Checks if the data provided can be transformed by the validator.
   *
   * @param mixed $data
   *   The data to check.
   * @param \Shaper\Util\Context $context
   *   Additional information that will affect applicability.
   *
   * @return bool
   *   TRUE if the transformation can be used with the supplied data.
   */
  public function conformsToExpectedInputShape($data, Context $context = NULL);

  /**
   * Checks if the transformed data conforms to the expected shape.
   *
   * @param mixed $data
   *   The data to check.
   * @param \Shaper\Util\Context $context
   *   Additional information that will affect applicability.
   *
   * @return bool
   *   TRUE if the transformed data conforms to the expected shape.
   */
  public function conformsToOutputShape($data, Context $context = NULL);

  /**
   * The validator for the input data.
   *
   * @return \Shaper\Validator\ValidateableInterface
   */
  public function getInputValidator();

  /**
   * The validator for the output data.
   *
   * @return \Shaper\Validator\ValidateableInterface
   */
  public function getOutputValidator();

}
