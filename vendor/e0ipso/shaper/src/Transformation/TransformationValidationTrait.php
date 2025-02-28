<?php

namespace Shaper\Transformation;

use Shaper\Util\Context;

trait TransformationValidationTrait {

  /**
   * {@inheritdoc}
   */
  public function conformsToExpectedInputShape($data, Context $context = NULL) {
    return $this->getInputValidator()->isValid($data);
  }

  /**
   * {@inheritdoc}
   */
  public function conformsToOutputShape($data, Context $context = NULL) {
    return $this->getOutputValidator()->isValid($data);
  }

}
