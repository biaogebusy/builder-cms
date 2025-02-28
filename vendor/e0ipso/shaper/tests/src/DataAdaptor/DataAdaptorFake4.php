<?php

namespace Shaper\Tests\DataAdaptor;

use Shaper\DataAdaptor\DataAdaptorBase;
use Shaper\Util\Context;
use Shaper\Validator\AcceptValidator;

class DataAdaptorFake4 extends DataAdaptorFake {
  public function conformsToOutputShape($data, Context $context = NULL) {
    return FALSE;
  }
}
