<?php

namespace Drupal\xinshi_api;

use Drupal\Component\Serialization\Json;

/**
 * Class TermJson
 * @package Drupal\xinshi_api
 */
class TermJson extends EntityJsonBase {

  /**
   * {@inheritdoc}
   */
  public function getContent() {
    // TODO: Implement getJson() method.
    $data = [];
    $build = $this->entityTypeManager->getViewBuilder($this->entity->getEntityTypeId())->view($this->entity);
    $panels = $this->renderLayoutBuilder();
    \Drupal::service('entity_theme_engine.entity_widget_service')->entityViewAlter($build, $this->entity, $this->mode);
    if (isset($build['content'])) {
      foreach ($panels as $key => $panel) {
        $build['content']['#context'][$key] = $panel;
      }
      if (isset($build['content']['#cache']['tags'])) {
        $this->addCacheTags($build['content']['#cache']['tags']);
      }
    }
    unset($build['#prefix']);
    unset($build['#suffix']);
    $content = \Drupal::service('renderer')->render($build);
    if ($str = $content->jsonSerialize()) {
      $data = Json::decode(htmlspecialchars_decode($str));
      parent::setFullText($data);
    }
    return $data ? $data : [];
  }
}
