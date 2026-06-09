<?php

namespace Drupal\ai_test\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;

/**
 * Plugin implementation of the 'ai:url_test' function call.
 */
#[FunctionCall(
  id: 'ai:url_test',
  function_name: 'url_test',
  name: 'URL Test',
  description: 'Useful for testing URL input filtering. Input is a string of a URL, output is the result of the URL test.',
  context_definitions: [
    'input' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Input URL"),
      description: new TranslatableMarkup("The input URL to be tested"),
      required: TRUE
    ),
  ],
)]
class UrlTest extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $input = $this->getContextValue('input');
    // Check if the input is a valid URL.
    if (filter_var($input, FILTER_VALIDATE_URL)) {
      $this->setOutput('The input is a valid URL: ' . $input);
    }
    else {
      $this->setOutput('The input is not a valid URL: ' . $input);
    }
  }

}
