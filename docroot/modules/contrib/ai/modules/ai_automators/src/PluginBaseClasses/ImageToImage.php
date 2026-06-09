<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai_automators\Traits\FileHelperTrait;
use Drupal\file\Entity\File;

/**
 * This is a base class that can be used for image generators from images.
 */
class ImageToImage extends RuleBase {

  use FileHelperTrait;

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
  public function allowedInputs() {
    return ['image'];
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can generate images from images.";
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
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Get amount if it exists.
    $amount = $automatorConfig['image_generation_amount'] ?? 1;

    // Generate the images.
    $images = [];
    foreach ($entity->get($automatorConfig['base_field']) as $target) {
      // The image binary.
      for ($i = 0; $i < $amount; $i++) {
        $image = $this->generateFileResponse($target->entity, $automatorConfig, $entity, $fieldDefinition);
        if ($image) {
          $images[] = [
            'filename' => $this->getFileName($automatorConfig),
            'binary' => $image,
          ];
        }
      }
    }
    return $images;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    if (!isset($value['filename'])) {
      return FALSE;
    }
    // Detect if binary.
    return preg_match('~[^\x20-\x7E\t\r\n]~', $value['binary']) > 0;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $images = [];
    foreach ($values as $value) {
      $fileHelper = $this->getFileHelper();
      $path = $fileHelper->createFilePathFromFieldConfig($value['filename'], $fieldDefinition, $entity);
      $images[] = $fileHelper->generateImageMetaDataFromBinary($value['binary'], $path);
    }
    // Then set the value.
    $entity->set($fieldDefinition->getName(), $images);
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
    return 'ai_generated.jpg';
  }

  /**
   * Mockup for generating response, have to be filled in by the rule.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file.
   * @param array $automatorConfig
   *   The configuration.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return mixed
   *   The response.
   */
  public function generateFileResponse(File $file, $automatorConfig, ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return NULL;
  }

}
