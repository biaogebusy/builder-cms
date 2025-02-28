<?php

namespace Shaper\DataAdaptor;

use Shaper\Transformation\TransformationValidationTrait;
use Shaper\Util\Context;

trait DataAdaptorValidatorTrait {

  use TransformationValidationTrait;

  /**
   * {@inheritdoc}
   */
  public function conformsToInternalShape($data, Context $context = NULL) {
    return $this->getInternalValidator()->isValid($data);
  }

}
