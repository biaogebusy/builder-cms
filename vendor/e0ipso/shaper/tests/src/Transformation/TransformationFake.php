<?php

namespace Shaper\Tests\Transformation;

use JsonSchema\Validator;
use Shaper\Transformation\TransformationBase;
use Shaper\Util\Context;
use Shaper\Validator\JsonSchemaValidator;

class TransformationFake extends TransformationBase {
  public function getInputValidator() {
    return new JsonSchemaValidator(['type' => 'string'], new Validator());
  }
  public function getOutputValidator() {
    return new JsonSchemaValidator(['type' => 'number'], new Validator());
  }
  protected function doTransform($data, Context $context) {
    return 42;
  }

}
