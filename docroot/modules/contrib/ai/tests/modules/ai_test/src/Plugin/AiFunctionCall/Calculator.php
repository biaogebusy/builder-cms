<?php

namespace Drupal\ai_test\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;

/**
 * Plugin implementation of the calculator function - just for tests.
 */
#[FunctionCall(
  id: 'ai:calculator',
  function_name: 'calculator',
  name: 'Calculator',
  description: 'Useful for getting the result of a math expression. Input is a string of a math expression, output is the result of the expression.',
  context_definitions: [
    'expression' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Expression"),
      description: new TranslatableMarkup("The expression to calculate (e.g '2 + 3')."),
      required: TRUE
    ),
  ],
)]
class Calculator extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * The calculation result.
   *
   * @var string
   */
  protected string $calculation;

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Do not use eval to evaluate the expression.
    $tokens = preg_split('/([\+\-\*\/\(\)])/u', $this->getContextValue('expression'), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
    $output = [];
    $operators = [];

    foreach ($tokens as $token) {
      $token = trim($token);
      if (is_numeric($token)) {
        $output[] = $token;
      }
      elseif (isset($precedence[$token])) {
        while (!empty($operators) && end($operators) !== '(' && $precedence[end($operators)] >= $precedence[$token]) {
          $output[] = array_pop($operators);
        }
        $operators[] = $token;
      }
      elseif ($token === '(') {
        $operators[] = $token;
      }
      elseif ($token === ')') {
        while (!empty($operators) && end($operators) !== '(') {
          $output[] = array_pop($operators);
        }
        array_pop($operators);
      }
    }
    while (!empty($operators)) {
      $output[] = array_pop($operators);
    }

    // Evaluate the reverse polish notation.
    $stack = [];
    foreach ($output as $token) {
      if (is_numeric($token)) {
        $stack[] = $token;
      }
      else {
        $b = array_pop($stack);
        $a = array_pop($stack);
        switch ($token) {
          case '+':
            $stack[] = $a + $b;
            break;

          case '-':
            $stack[] = $a - $b;
            break;

          case '*':
            $stack[] = $a * $b;
            break;

          case '/':
            $stack[] = $a / $b;
            break;
        }
      }
    }
    $this->calculation = $stack[0];
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return (string) $this->calculation;
  }

}
