<?php

namespace Drupal\ai_test\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\StructuredExecutableFunctionCallInterface;

/**
 * Plugin implementation of a trigger function - just for tests.
 */
#[FunctionCall(
  id: 'ai:trigger',
  function_name: 'trigger',
  name: 'Trigger',
  description: 'Triggers a button.',
  context_definitions: []
)]
class Trigger extends FunctionCallBase implements StructuredExecutableFunctionCallInterface {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $this->setOutput('Triggered');
  }

}
