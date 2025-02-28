<?php

namespace Shaper\Tests\Transformation;

use Shaper\Util\Context;

class TransformationFail extends TransformationFake {
  protected function doTransform($data, Context $context) {
    return 'bar';
  }
}
