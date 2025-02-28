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
    $panels = [];
    if (isset($build['#panels_display']) && isset($build['content']['content'])) {
      foreach ($build['content']['content'] as $key => $content) {
        if (strpos($key, '#') === 0) {
          continue;
        }
        switch ($content['#base_plugin_id']) {
          case 'views_block':
            $panels[$content['content']['#name'] . '_' . $content['content']['#display_id']] = [
              'rows' => $content['content']['view_build']['#rows'] ? $content['content']['view_build']['#rows'][0]['#rows'] : [],
              'title' => $content['#configuration']['views_label'] ? $content['#configuration']['views_label'] : $content['content']['#title']['#markup'] ?? '',
            ];
            $this->addCacheTags($content['content']['#cache']['tags']);
            break;
        }
      }
    }
    \Drupal::service('entity_theme_engine.entity_widget_service')->entityViewAlter($build, $this->entity, $this->mode);
    foreach ($panels as $key => $panel) {
      $build['content']['#context'][$key] = $panel;
    }
    $this->addCacheTags($build['content']['#cache']['tags']);
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
