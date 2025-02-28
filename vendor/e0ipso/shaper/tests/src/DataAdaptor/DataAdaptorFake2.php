<?php

namespace Shaper\Tests\DataAdaptor;

use Shaper\DataAdaptor\DataAdaptorBase;
use Shaper\Util\Context;
use Shaper\Validator\AcceptValidator;

class DataAdaptorFake2 extends DataAdaptorFake {
  public function conformsToExpectedInputShape($data, Context $context = NULL) {
    return FALSE;
  }
}
