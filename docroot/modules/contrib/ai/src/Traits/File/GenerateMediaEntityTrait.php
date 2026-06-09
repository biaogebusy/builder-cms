<?php

namespace Drupal\ai\Traits\File;

use Drupal\Core\Field\FieldConfigInterface;
use Drupal\ai\Exception\AiBrokenOutputException;
use Drupal\media\Entity\Media;

/**
 * Trait to add the possibility to store medias directly in the processor.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateMediaEntityTrait {

  use GenerateFileEntityTrait;

  /**
   * Generate media.
   *
   * @param string $media_type
   *   The media type.
   * @param string $file_path
   *   An optional path instead of the field configuration one.
   * @param string $filename
   *   The optional file name.
   *
   * @return \Drupal\media\Entity\Media
   *   The media entity.
   */
  public function getAsMediaEntity(string $media_type, string $file_path = "", string $filename = ""): Media {
    // Check that the media module is installed or fail.
    if (!\Drupal::moduleHandler()->moduleExists('media')) {
      throw new AiBrokenOutputException('Media module is not installed, getAsMediaReference will not work.');
    }
    // Check if the media type exists.
    if (!\Drupal::service('entity_type.manager')->getStorage('media_type')->load($media_type)) {
      throw new AiBrokenOutputException('Media type does not exist.');
    }
    // Get the base field.
    $base_field = $this->getBaseMediaFieldDefinition($media_type);
    if (!$base_field) {
      throw new AiBrokenOutputException('Media type does not have a base field.');
    }

    if (!$filename) {
      if ($this->filename) {
        $filename = $this->filename;
      }
      else {
        $filename = uniqid();
      }
    }
    // Generate the path.
    if (!$file_path) {
      $file_path = $this->getMediaFilePath($base_field);
    }

    // Create the file, depending on type.
    $file = $this->getAsFileEntity($file_path, $filename);

    // Create the media.
    $media = \Drupal::service('entity_type.manager')->getStorage('media')->create([
      'bundle' => $media_type,
      'uid' => \Drupal::currentUser()->id(),
      'status' => 1,
      $base_field->getName() => [
        'target_id' => $file->id(),
        'alt' => $filename,
      ],
    ]);
    $media->save();

    return $media;
  }

  /**
   * Gets the path from the field definition.
   *
   * @param \Drupal\field\Entity\FieldConfig $field_definition
   *   The field definition.
   * @param string $file_name
   *   The file name. No longer required.
   *
   * @return string
   *   The path.
   */
  private function getMediaFilePath(FieldConfigInterface $field_definition, string $file_name = ''): string {
    $config = $field_definition->getSettings();
    return \Drupal::token()->replace($config['uri_scheme'] . '://' . rtrim($config['file_directory'], '/'));
  }

  /**
   * Get the base media field for the media type.
   *
   * @param string $media_type
   *   The media type.
   *
   * @return string
   *   The base media field.
   */
  private function getBaseMediaField(string $media_type): string {
    /** @var \Drupal\media\Entity\MediaType $media_type */
    $media_type = \Drupal::entityTypeManager()->getStorage('media_type')->load($media_type);
    $base_field = $media_type->getSource()->getConfiguration()['source_field'] ?? '';
    return $base_field;
  }

  /**
   * Gets the field definition for the source field on the media type.
   *
   * @param string $media_type
   *   The media type.
   *
   * @return \Drupal\Core\Field\FieldConfigInterface|null
   *   The field definition.
   */
  private function getBaseMediaFieldDefinition(string $media_type): ?FieldConfigInterface {
    $base_field = $this->getBaseMediaField($media_type);
    /** @var \Drupal\field\FieldConfigStorage */
    $field_config_storage = \Drupal::entityTypeManager()->getStorage('field_config');
    $field_definition = $field_config_storage->load('media.' . $media_type . '.' . $base_field);
    return $field_definition;
  }

}
