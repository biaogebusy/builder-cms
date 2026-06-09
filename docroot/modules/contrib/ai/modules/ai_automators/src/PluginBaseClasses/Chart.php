<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * This is a base class that can be used for LLMs simple chart rules.
 */
class Chart extends RuleBase implements AiAutomatorTypeInterface {

  /**
   * Colors to set.
   */
  public array $colors = [
    '#006fb0',
    '#f07c33',
  ];

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return $this->t("Scrape data for possible chart data and render it.");
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "From the context text, use the mobile phones name, the weight and RAM as values.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value, array $automatorConfig = []) {
    if (empty($value[0]['config']['series']['data_collector_table'][0][0]['data'])) {
      return [];
    }
    return $value;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n-------------------------------------\n\nDo not include any explanations, only provide a CSV file with keys in the first row and the values in the second rows without deviation. Use semicolon (;) as delimiter. Always quote text values that contain commas, semicolons, or special characters. Numeric values should not be quoted. The keys should have the prefix or suffix in parentheses and the value should be stripped of it.\n\n";
      $prompt .= "Examples would be:\n";
      $prompt .= "\"Hotel Name\"; \"Max Capacity (people)\"; \"Hotel Size (sqm)\"\n";
      $prompt .= "\"Hotel Radisson, Berlin\"; 300; 1280\n";
      $prompt .= "\"The Vichy, Jamestown\"; 840; 3880\n";
      $prompts[$key] = $prompt;
    }
    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);

    foreach ($prompts as $prompt) {
      // Create new messages.
      $input = new ChatInput([
        new ChatMessage("user", $prompt),
      ]);
      $input = $this->applyGuardrailsToInput($input, $automatorConfig);

      $response = $instance->chat($input, $automatorConfig['ai_model'])->getNormalized();
      $this->assertNotStoppedByGuardrail($input);
      // Normalize the response.
      $values = [str_replace(['```csv', '```'], '', $response->getText())];
      $total = array_merge_recursive($total, $values);
    }

    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    if (empty(str_getcsv($value))) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $defaults = $entity->get($fieldDefinition->getName())->getValue();
    $cols = explode("\n", $values[0]);
    $data = [];
    foreach ($cols as $colKey => $col) {
      if (strpos($col, ';') !== FALSE) {
        $rows = str_getcsv($col, ';');
      }
      else {
        $rows = str_getcsv($col, ',');
      }
      if (count($rows) < 2) {
        continue;
      }
      foreach ($rows as $rowKey => $row) {
        $row = trim(trim($row), '"');
        $data[$colKey][$rowKey]['data'] = $row;
        if ($colKey == 0 && $rowKey > 0) {
          $data[$colKey][$rowKey]['color'] = $this->colors[($rowKey - 1)];
        }
      }
    }
    $defaults[0]['config']['series']['data_collector_table'] = $data;

    // Then set the value.
    $entity->set($fieldDefinition->getName(), $defaults);
    return TRUE;
  }

}
