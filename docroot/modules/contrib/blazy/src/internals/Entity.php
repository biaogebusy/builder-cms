<?php

namespace Drupal\blazy\Internals;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides feature check methods at container level, or globally.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please use the public method instead.
 */
final class Entity {

  /**
   * Modifies the common settings extracted from the given entity.
   */
  public static function settings(array &$settings, $entity): void {
    // Might be accessed by tests, or anywhere outside the workflow.
    $blazies  = Internals::verify($settings);
    $langcode = $blazies->get('language.current');

    if ($info = self::withTranslatedData($entity, $langcode)) {
      $data = $info['data'];
      $id   = $data['id'];
      $rid  = $data['rid'];

      $blazies->set('cache.metadata.keys', [$id, $rid], TRUE)
        ->set('entity', $data, TRUE);
    }
  }

  /**
   * Returns the translated entity if available.
   */
  public static function translated($entity, $langcode = NULL): object {
    if ($manager = Internals::blazy()) {
      return $manager->getTranslatedEntity($entity, $langcode);
    }
    return $entity;
  }

  /**
   * Returns entity data.
   */
  public static function withTranslatedData($entity, $langcode): array {
    if (!$entity instanceof EntityInterface) {
      return [];
    }

    $internal_path = $absolute_path = NULL;
    // Deals with UndefinedLinkTemplateException such as paragraphs type.
    // @see #2596385, or fetch the host entity.
    if (!$entity->isNew()) {
      try {
        // Provides translated $entity, if any.
        /** @var \Drupal\Core\Entity\EntityInterface
         * |\Drupal\media\MediaInterface $entity */
        $entity = self::translated($entity, $langcode);

        // Edge case when an entity does a stupid thing.
        if ($url = $entity->toUrl()) {
          // $media->toUrl()->toString()
          $internal_path = $url->getInternalPath();
          $absolute_path = $url->setAbsolute()->toString();
        }
      }
      catch (\Exception $ignore) {
        // Do nothing.
      }
    }

    if (method_exists($entity, 'getRevisionId')) {
      $rid = $entity->getRevisionId();
    }
    // @todo deprecate and remove, looks like a mispell?
    elseif (method_exists($entity, 'getRevisionID')) {
      $rid = $entity->getRevisionID();
    }
    else {
      $rid = NULL;
    }

    // Only eat what we can chew.
    $data = [
      'bundle'  => $entity->bundle(),
      'id'      => $entity->id(),
      'label'   => $entity->label(),
      'path'    => $internal_path,
      'rid'     => $rid,
      'type_id' => $entity->getEntityTypeId(),
      'url'     => $absolute_path,
    ];

    return ['data' => $data, 'entity' => $entity];
  }

}
