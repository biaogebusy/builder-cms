<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use Drupal\ai_automators\Traits\FileHelperTrait;

/**
 * This is a base class that can be used for speech generators.
 */
class TextToSpeech extends RuleBase {

  use FileHelperTrait;

  /**
   * {@inheritDoc}
   */
  protected string $llmType = 'text_to_speech';

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can generate speech from text.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = [];
    // @phpstan-ignore-next-line
    if (!empty($automatorConfig['mode']) && $automatorConfig['mode'] == 'token' && \Drupal::service('module_handler')->moduleExists('token')) {
      $prompts[] = \Drupal::service('ai_automator.prompt_helper')->renderTokenPrompt($automatorConfig['token'], $entity); /* @phpstan-ignore-line */
    }
    elseif ($this->needsPrompt()) {
      // Run rule.
      foreach ($entity->get($automatorConfig['base_field'])->getValue() as $i => $item) {
        // Get tokens.
        $tokens = $this->generateTokens($entity, $fieldDefinition, $automatorConfig, $i);
        $prompts[] = \Drupal::service('ai_automator.prompt_helper')->renderPrompt($automatorConfig['prompt'], $tokens, $i); /* @phpstan-ignore-line */
      }
    }

    // Generate the audio.
    $audios = [];
    $instance = $this->prepareLlmInstance('text_to_audio', $automatorConfig);
    foreach ($prompts as $prompt) {
      // The audio binary.
      $input = new TextToSpeechInput($prompt);
      $response = $instance->textToSpeech($input, $automatorConfig['ai_model'])->getNormalized();
      if (!empty($response)) {
        foreach ($response as $audio) {
          $audios[] = [
            'filename' => $this->getFileName($automatorConfig),
            'binary' => $audio->getAsBinary(),
          ];
        }
      }
    }
    return $audios;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, $automatorConfig) {
    if (!isset($value['filename'])) {
      return FALSE;
    }
    // Detect if binary.
    return preg_match('~[^\x20-\x7E\t\r\n]~', $value['binary']) > 0;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, $automatorConfig) {
    $audios = [];
    foreach ($values as $value) {
      $fileHelper = $this->getFileHelper();
      $path = $fileHelper->createFilePathFromFieldConfig($value['filename'], $fieldDefinition, $entity);
      $audios[] = $fileHelper->generateFileFromBinary($value['binary'], $path);
    }
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $audios);
    return TRUE;
  }

  /**
   * Gets the filename. Override this.
   *
   * @param array $args
   *   If arguments are needed to create the filename.
   *
   * @return string
   *   The filename.
   */
  public function getFileName(array $args = []) {
    return 'ai_generated.mp3';
  }

}
