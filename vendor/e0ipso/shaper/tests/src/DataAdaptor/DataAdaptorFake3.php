<?php

namespace Shaper\Tests\DataAdaptor;

use Shaper\DataAdaptor\DataAdaptorBase;
use Shaper\Util\Context;
use Shaper\Validator\AcceptValidator;

class DataAdaptorFake3 extends DataAdaptorFake {
  public function conformsToInternalShape($data, Context $context = NULL) {
    return FALSE;
  }
}
