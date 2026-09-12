<?php

namespace Drupal\blazy\Media;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
// @todo enable at 4.x: use Drupal\blazy\BlazyManagerInterface;
use Drupal\blazy\Internals\Internals;
use Drupal\file\FileInterface;

/**
 * A common file utility helper.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo tighten the callers, or leave it for the lazy.
 */
class File {

  /**
   * Returns a file object from an URI.
   *
   * @param string $uri
   *   The URI to to load the file entity.
   * @param \Drupal\blazy\BlazyManagerInterface|null $manager
   *   The blazy manager or null.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity or null.
   *
   * @todo add union types at 4.x:
   * BlazyManagerInterface|null $manager.
   */
  public static function fromUri(
    string $uri,
    $manager = NULL,
  ): ?FileInterface {
    return Internals::loadByProperty('uri', $uri, 'file', $manager);
  }

  /**
   * Returns TRUE if a File entity.
   *
   * @param object|null $file
   *   The URI to be tested may be null.
   *
   * @return bool
   *   TRUE if the URI is valid.
   *
   * @todo add union types at 4.x:
   * object|null $file.
   */
  public static function isValid($file): bool {
    return $file instanceof FileInterface;
  }

  /**
   * Returns the File entity from any object, or just settings, if applicable.
   *
   * Should be named entity, but for consistency with Image:item().
   *
   * @param object|null $object
   *   The URI to to load the file entity.
   * @param array $settings
   *   The given settings, or empty.
   * @param string $uri
   *   The URI to to load the file entity.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity or null.
   *
   * @todo add union types at 4.x:
   * object|null $object = NULL,
   * array $settings = [],
   * string|null $uri = NULL,
   */
  public static function item(
    $object = NULL,
    array $settings = [],
    $uri = NULL,
  ): ?FileInterface {
    $file = $object;
    Internals::verify($settings);

    // Bail out early if we are given what we want.
    /** @var \Drupal\file\FileInterface $file */
    if (self::isValid($file)) {
      return $file;
    }

    // Fake, or real image item. Might also be VEF.
    /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $object */
    if (Image::isValid($object) && $file = $object->entity ?? NULL) {
      // Ensures not locked here, in case VEF put its VEF, etc.
      if (self::isValid($file)) {
        return $file;
      }
    }

    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $object */
    if ($object instanceof EntityReferenceItem) {
      /** @var \Drupal\file\FileInterface $file */
      $file = $object->entity;
    }
    /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $object */
    elseif ($object instanceof EntityReferenceFieldItemListInterface) {
      // @phpstan Variable $image in PHPDoc tag @ var does not exist.
      /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $image */
      $image = $object->first();
      if ($image) {
        /** @var \Drupal\file\FileInterface $file */
        $file = $image->entity;
      }
    }

    // BlazyFilter without any entity/ formatters associated with.
    // Or any entities: Node, Paragraphs, User, etc. having settings.image.
    if (!self::isValid($file) && $settings) {
      // Extracts File entity from settings.image, the poster image.
      if ($name = $settings['image'] ?? NULL) {
        // With a mix of image and video, image is not always there.
        $file = self::fromField($file, $name, $settings);
      }

      // BlazyFilter without any entity/ formatters associated with.
      // Or legacy VEF with hard-coded image URL without file API.
      if (!self::isValid($file)) {
        $file = self::fromSettings($settings, $uri);
      }
    }

    return self::isValid($file) ? $file : NULL;
  }

  /**
   * Returns the File entity from a field name, if applicable.
   *
   * Main image can be separate image item from video thumbnail for highres.
   * Fallback to default thumbnail if any, which has no file API. This used to
   * be for non-media File Entity Reference at 1.x, things changed since then.
   * Some core methods during Blazy 1.x are now gone at 2.x.
   * Re-purposed for Paragraphs, Node, etc. which embeds Media or File.
   *
   * @param object $entity
   *   The URI to to load the file entity.
   * @param string $name
   *   The field name.
   * @param array $settings
   *   The given settings, or empty.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity or null.
   */
  private static function fromField(
    $entity,
    string $name,
    array $settings,
  ): ?FileInterface {
    $file = NULL;

    if (!isset($entity->{$name})) {
      return NULL;
    }

    // @phpstan Variable $field in PHPDoc tag @ var does not exist.
    /** @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $field */
    $field = $entity->get($name);
    if ($field && method_exists($field, 'referencedEntities')) {
      // Two designated types: MediaInterface and FileInterface.
      $reference = $field->referencedEntities()[0] ?? NULL;
      // The first is FileInterface.
      if (self::isValid($reference)) {
        $file = $reference;
      }
      else {
        // The last is MediaInterface, but let the dogs out for now.
        $options = [
          'entity' => $reference,
          'source' => $entity,
          'settings' => $settings,
        ];
        if ($image = Image::fromContent($options, $name)) {
          $file = $image->entity;
        }
      }
    }
    return self::isValid($file) ? $file : NULL;
  }

  /**
   * Returns the File entity from settings, if applicable, relevant for Filter.
   *
   * @param array $settings
   *   The given settings.
   * @param string|null $uri
   *   The file URI.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity or null.
   *
   * @todo add union types at 4.x:
   * string|null $uri.
   */
  private static function fromSettings(
    array $settings,
    $uri = NULL,
  ): ?FileInterface {
    $blazies = Internals::getBlazies($settings);
    $uri     = $uri ?: Uri::fromImage(NULL, $settings);
    $uuid    = $blazies->get('entity.uuid');
    $file    = $uuid ? Internals::loadByUuid($uuid, 'file') : NULL;

    if (!$file && $uri) {
      $file = self::fromUri($uri);
    }
    return self::isValid($file) ? $file : NULL;
  }

}
