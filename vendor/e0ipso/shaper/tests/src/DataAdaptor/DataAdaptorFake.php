<?php

namespace Shaper\Tests\DataAdaptor;

use Shaper\DataAdaptor\DataAdaptorBase;
use Shaper\Util\Context;
use Shaper\Validator\AcceptValidator;

class DataAdaptorFake extends DataAdaptorBase {
  protected function doTransform($data, Context $context) {
    $key = $context->offsetExists('key') ? $context['key'] : 'lorem';
    return $data->{$key};
  }
  protected function doUndoTransform($data, Context $context) {
    $key = $context->offsetExists('key') ? $context['key'] : 'lorem';
    return (object) [$key => $data, 'bar' => 'default'];
  }
  public function getInputValidator() {
    return new AcceptValidator();
  }

  public function getInternalValidator() {
    return new AcceptValidator();
  }

  public function getOutputValidator() {
    return new AcceptValidator();
  }
}
