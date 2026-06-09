<?php

namespace Drupal\ai_test\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\StructuredExecutableFunctionCallInterface;

/**
 * Plugin implementation of a weather function - just for tests.
 */
#[FunctionCall(
  id: 'ai:weather',
  function_name: 'weather',
  name: 'Weather',
  description: 'Get the weather for a text based location.',
  context_definitions: [
    'city' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("City"),
      required: TRUE,
      description: new TranslatableMarkup("The city to get the weather for (e.g. London).")
    ),
    'country' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Country"),
      required: TRUE,
      description: new TranslatableMarkup("The country to get the weather for. If not provided, try to guess it from the context. The country where the city is located (e.g., USA for Los Angeles).")
    ),
    'unit' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Unit"),
      required: FALSE,
      description: new TranslatableMarkup("The unit to get the weather in (celsius or fahrenheit)."),
      default_value: 'celsius',
      constraints: [
        'Choice' => [
          'choices' => [
            'celsius',
            'fahrenheit',
          ],
        ],
      ],
    ),
  ]
)]
class Weather extends FunctionCallBase implements StructuredExecutableFunctionCallInterface {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Since this is for testing we will just simulate a weather response.
    $city = $this->getContextValue('city');
    $country = $this->getContextValue('country');
    $unit = $this->getContextValue('unit');

    // Simulate a temperature based on the city and country.
    if ($city === 'London' && $country === 'UK') {
      $this->stringOutput = $unit === 'celsius' ? '15°C' : '59°F';
    }
    elseif ($city === 'Los Angeles' && $country === 'USA') {
      $this->stringOutput = $unit === 'celsius' ? '25°C' : '77°F';
    }
    else {
      $this->stringOutput = $unit === 'celsius' ? '20°C' : '68°F';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getStructuredOutput(): array {
    return [
      'city' => $this->getContextValue('city'),
      'country' => $this->getContextValue('country'),
      'unit' => $this->getContextValue('unit'),
      'temperature' => $this->stringOutput,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setStructuredOutput(array $output): void {
    $this->stringOutput = $output['temperature'] ?? '';
    $this->setContextValue('city', $output['city'] ?? '');
    $this->setContextValue('country', $output['country'] ?? '');
    $this->setContextValue('unit', $output['unit'] ?? 'celsius');
  }

}
