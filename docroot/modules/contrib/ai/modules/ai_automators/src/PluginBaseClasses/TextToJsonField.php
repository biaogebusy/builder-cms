<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This is a base class that can be used for LLMs json output.
 */
class TextToJsonField extends RuleBase {

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This is a simple text to JSON field model.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the actor context, give back a list of all the movies that person has been in, together with year of release.\n\nContext:\n{{ context }}\n\n------------------------------\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"movie_title\": \"title of movie\", \"release_year\": \"year of release\"}]";
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompts[$key] = $prompt;
    }
    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      // Normalize the response.
      $values = str_replace("\n", "", trim(str_replace(['```json', '```'], '', $this->runRawChatMessage($prompt, $automatorConfig, $instance, $entity)->getText())));

      if (!empty($values)) {
        $total[] = $values;
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Check so its valid JSON.
    if (empty($value)) {
      return FALSE;
    }
    $json = json_decode($value, TRUE);
    if (empty($json)) {
      return FALSE;
    }
    return TRUE;
  }

}
