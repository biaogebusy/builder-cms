<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;

/**
 * Helper function for audio to text.
 */
class AudioToText extends RuleBase implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Audio To Text';

  /**
   * {@inheritDoc}
   */
  protected string $llmType = 'speech_to_text';

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "";
  }

  /**
   * {@inheritDoc}
   */
  public function allowedInputs() {
    return [
      'file',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $values = [];
    $instance = $this->prepareLlmInstance('speech_to_text', $automatorConfig);

    foreach ($entity->get($automatorConfig['base_field']) as $entityWrapper) {
      if ($entityWrapper->entity) {
        $fileEntity = $entityWrapper->entity;
        if (in_array($fileEntity->getMimeType(), [
          'audio/mpeg',
          'audio/aac',
          'audio/wav',
        ])) {
          $input = new SpeechToTextInput(new AudioFile(file_get_contents($fileEntity->getFileUri()), $fileEntity->getMimeType(), $fileEntity->getFilename()));
          $response = $instance->speechToText($input, $automatorConfig['ai_model'], ['ai_automator_speech_to_text']);
          $values[] = $response->getNormalized();
        }
      }
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Should be a string.
    if (!is_string($value)) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $values);
    return TRUE;
  }

}
