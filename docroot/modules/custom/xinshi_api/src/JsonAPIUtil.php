<?php

namespace Drupal\xinshi_api;

use Drupal\Component\Serialization\Json;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;


class JsonAPIUtil {

  /**
   * Return entity form request.
   * @param bool $load_entity
   * @param string $key
   * @return \Drupal\Core\Entity\EntityInterface|string
   */
  public static function getEntityByQuery($load_entity = TRUE, $key = 'content') {
    //get node id
    $path = \Drupal::request()->get($key);
    $revision = \Drupal::request()->get('revision');
    $id = NULL;
    $entity = NULL;
    try {
      $entity_manager = \Drupal::entityTypeManager();
      $base = \Drupal::request()->getBaseUrl();
      $path = str_replace([$base], '', $path);
      $path = '/' . trim($path, '/');
      $path = \Drupal::service('path_alias.manager')->getPathByAlias($path);
      if (preg_match('/node\/(\d+)/', $path, $matches)) {
        $id = $matches[1];
        if ($load_entity && $entity = (empty($id) == FALSE && is_numeric($id)) ? Node::load($id) : NULL) {
          if ($revision && is_numeric($revision)) {
            $node_revision = $entity_manager->getStorage('node')->loadRevision($revision);
            if ($node_revision instanceof Node && $node_revision->id() == $entity->id()) {
              $entity = $node_revision;
            }
          }
        }
      }

      if (preg_match('/taxonomy\/term\/(\d+)/', $path, $matches)) {
        $id = $matches[1];
        $entity = (empty($id) == FALSE && is_numeric($id)) ? Term::load($id) : NULL;
      }
      if (preg_match('/product\/(\d+)/', $path, $matches)) {
        $id = $matches[1];
        $entity = (empty($id) == FALSE && is_numeric($id)) ? $entity_manager->getStorage('commerce_product')->load($id) : NULL;
      }
      if (preg_match('/user\/(\d+)/', $path, $matches)) {
        $id = $matches[1];
        $entity = (empty($id) == FALSE && is_numeric($id)) ? $entity_manager->getStorage('user')->load($id) : NULL;
      }
    } catch (\Exception $e) {
      \Drupal::logger('xinshi_api')->error($e->getMessage());
    }

    if ($entity && \Drupal::moduleHandler()->moduleExists('content_translation') &&
      \Drupal::service('content_translation.manager')->isEnabled($entity->getEntityTypeId(), $entity->bundle()) &&
      $entity->hasTranslation(\Drupal::languageManager()->getCurrentLanguage()->getId())) {
      $entity = $entity->getTranslation(\Drupal::languageManager()->getCurrentLanguage()->getId());
    }
    return $entity ? ($load_entity ? $entity : $entity->id()) : NULL;
  }

  /**
   * Return page not found json.
   * @return array
   */
  public static function notFound() {
    $data = \Drupal::config('xinshi_api.settings')->get('page')['not_found'] ?? '';
    if ($data && $res = Json::decode($data)) {
      return $res;
    }
    return [];
  }

  /**
   * Return page access denied json.
   * @return array
   */
  public static function accessDenied() {
    $data = \Drupal::config('xinshi_api.settings')->get('page')['access_denied'] ?? '';
    if ($data && $res = Json::decode($data)) {
      return $res;
    }
    return [];
  }
}
