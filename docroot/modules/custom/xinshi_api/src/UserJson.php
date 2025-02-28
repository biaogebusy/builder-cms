<?php

namespace Drupal\xinshi_api;

use Drupal\Component\Serialization\Json;

/**
 * Class TermJson
 * @package Drupal\xinshi_api
 */
class UserJson extends EntityJsonBase {

  /**
   * {@inheritdoc}
   */
  public function getContent() {
    $data = [];
    $build = $this->entityTypeManager->getViewBuilder($this->entity->getEntityTypeId())->view($this->entity, $this->mode);
    $content = \Drupal::service('renderer')->render($build, TRUE);
    $this->addCacheTags($build['content']['#cache']['tags'] ?? []);
    if (($str = $content->jsonSerialize()) && $data = Json::decode(htmlspecialchars_decode($str))) {
      $this->setFullText($data);
    }
    return $data ? $data : [];
  }
}
