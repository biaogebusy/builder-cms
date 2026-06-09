<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\RuleBase;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\ai_automators\Traits\FileHelperTrait;

/**
 * The rules automator type for rewriting image filenames using LLM.
 */
#[AiAutomatorType(
  id: 'llm_rewrite_image_filename',
  label: new TranslatableMarkup('LLM: Rewrite Image Filename'),
  field_rule: 'image',
  target: 'file',
)]
class LlmRewriteImageFilename extends RuleBase implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  use FileHelperTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Rewrite Image Filename';

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can rewrite the filename of an image using a LLM. This will only work with the Field Widget processor, since it won't run if the image has a filename.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Look at the image and make sure the filename is descriptive and relevant to the image content. The filename should be in lowercase, use hyphens instead of spaces, and avoid special characters.";
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\": {\"filename\": \"The filename without an extension\"}}]. You should create one item for each image and give it back in the order it was provided.\n\n";
      $prompts[$key] = $prompt;
    }
    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      $values = $this->runChatMessage($prompt, $automatorConfig, $instance, $entity);
      if (!empty($values)) {
        $total = array_merge_recursive($total, $values);
      }
    }
    return $total;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, $automatorConfig) {
    // Has to have a filename.
    if (empty($value['filename'])) {
      return FALSE;
    }

    // Filename must be a string, with no space, only lowercase letters,
    // numbers, and hyphens.
    if (!is_string($value['filename']) || !preg_match('/^[a-z0-9-]+$/', $value['filename'])) {
      return FALSE;
    }

    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    foreach ($entity->get($fieldDefinition->getName()) as $delta => $item) {
      // Check the original value to get the extension.
      /** @var \Drupal\file\FileInterface $file */
      $image = $item->entity;
      // Get the original filepath and replace the filename with the new one.
      $original_filepath = $image->getFileUri();
      $original_filename = $image->getFilename();
      $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
      $new_filepath = str_replace($original_filename, $values[$delta]['filename'] . '.' . $extension, $original_filepath);
      // Move the file to the new location.
      $new_filepath = $this->getFileHelper()->getFileSystem()->move($original_filepath, $new_filepath, FileExists::Rename);
      // Set the new filepath.
      $image->setFileUri($new_filepath);
      // Get the new filename if it changed in the new filepath.
      $filename_parts = pathinfo($new_filepath);
      // Set the new filename.
      $image->setFilename($filename_parts['basename']);
      // Save the file entity.
      $image->save();
    }
  }

}
