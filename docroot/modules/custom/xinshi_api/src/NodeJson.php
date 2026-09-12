<?php

namespace Drupal\xinshi_api;

use Drupal\layout_builder\SectionComponent;
use Drupal\Component\Serialization\Json;
use Drupal\entity_print\Plugin\EntityPrint\PrintEngine\DomPdf;

/**
 * Class NodeJson
 * @package Drupal\xinshi_api
 */
class NodeJson extends EntityJsonBase {

  /**
   * {@inheritdoc}
   */
  public function getContent() {
    // TODO: Implement getJson() method.
    switch ($this->entity->bundle()) {
      case 'landing_page':
        return $this->landingPageType();
      default:
        return $this->getNodeContent();
    }
  }

  /**
   * Return the content of json.
   * @return array
   */
  private function landingPageType() {
    $this->setMate($data);
    $this->setConfiguration($data);
    $data['nid'] = $this->entity->id();
    $data['langcode'] = $this->entity->language()->getId();
    $data['body'] = [];
    $this->setBanner($data);
    $widgets = [];
    if ($this->isLayoutBuilder()) {
      $builder =  $this->layoutBuilder->build($this->entity);
      $weight = 0;
      foreach ($builder['_layout_builder'] as $section) {
        /** @var SectionComponent $component */
        foreach ($section['content'] as $component) {
          if ($component['content']['#entity_type'] == 'block_content') {
            $entityJson = new EntityJsonBase($component['content']['#block_content']);
            $widgets[] = [
              'weight' => $weight,
              'content' => $entityJson->getContent(),
              'type' => $entityJson->entity->bundle(),
            ];
            $weight++;
            $this->addCacheTags($entityJson->getCacheTags());;
          }
        }
      }
    }

    //Sort widgets by weight
    array_multisort($widgets, SORT_ASC, SORT_NUMERIC, array_column($widgets, 'weight'));
    foreach ($widgets as $widget) {
      $item = $widget['content'];
      if ($widget['type'] == 'json' && isset($item['body']) && is_array($item['body'])) {
        //multi widgets
        $data['body'] = array_merge($data['body'], $item['body']);
      } else {
        $data['body'][] = $item;
      }
    }
    return $data;
  }

  /**
   * Set banner data.
   * @param $data
   */
  private function setBanner(&$data) {
    if ($this->entity->get('banner_style')->isEmpty()) {
      return;
    }
    $banner['type'] = 'banner-simple';
    $banner['fullWidth'] = TRUE;
    $banner['style'] = $this->entity->get('banner_style')->isEmpty() ? 'normal' : $this->entity->get('banner_style')->value;

    $media = $this->entity->get('media')->entity;
    $img = $media ? CommonUtil::getImageStyle($media->get('field_media_image')->target_id) : '';
    if ($img) {
      $class[] = 'bg-fill-width';
      if ($opacity = $this->entity->get('opacity')->value) {
        $class[] = 'overlay';
        $class[] = 'overlay-' . $opacity;
      }
      $banner['bannerBg'] = [
        'classes' => join(' ', $class),
        'img' => [
          'hostClasses' => 'bg-center',
          'src' => $img,
          'alt' => $media ? $media->label() : '',
        ],
      ];
    } else {
      $banner['style'] = 'no-bg';
    }
    $banner['title'] = $this->entity->get('is_display_title')->value ? $this->entity->label() : '';
    $banner['breadcrumb'] = [];
    $data['body'][] = $banner;
  }

  /**
   * Return node json.
   * @return array|mixed
   */
  private function getNodeContent() {
    $data = [];
    $build = $this->entityTypeManager->getViewBuilder($this->entity->getEntityTypeId())->view($this->entity);
    $panels = $this->renderLayoutBuilder();
    \Drupal::service('entity_theme_engine.entity_widget_service')->entityViewAlter($build, $this->entity, $this->mode);
    foreach ($panels as $key => $panel) {
      $build['content']['#context'][$key] = $panel;
    }

    if (isset($build['content'])) {
      $this->addCacheTags($build['content']['#cache']['tags']);
    }
    unset($build['#prefix']);
    unset($build['#suffix']);
    $content = \Drupal::service('renderer')->render($build);
    if ($str = $content->jsonSerialize()) {
      $str = htmlspecialchars_decode($str);
      $str = str_replace(["\t"], '', $str);
      $data = Json::decode($str);
      parent::setFullText($data);
    }
    return $data ? $data : [];
  }

  /**
   * Set page configuration.
   * @param $data
   */
  private function setConfiguration(&$data) {
    $config = [];
    if ($this->entity->hasField('configuration') && !$this->entity->get('configuration')->isEmpty()) {
      $config = Json::decode(htmlspecialchars_decode($this->entity->get('configuration')->value));
    }
    if ($this->entity->get('is_transparent')->value) {
      $config["headerMode"] = [
        "transparent" => TRUE,
        "style" => $this->entity->get('transparent_style')->value,
      ];
    }
    if ($config) {
      $data['config'] = $config;
    }
  }

  /**
   * Set mete (SEO).
   * @param $data
   */
  private function setMate(&$data) {
    $data['title'] = \Drupal::token()->replace('[node:title] | [site:name]', ['node' => $this->entity]);
    if ($meta = $this->entity->get('meta_tags')->value) {
      // Metatag v2 stores JSON; metatag_data_decode() reads both that and the
      // serialized values left over from v1, and always returns an array.
      $meta = metatag_data_decode($meta);
      foreach ($meta as $key => $value) {
        $content = \Drupal::token()->replace($value, ['node' => $this->entity]);
        if (empty($content)) {
          continue;
        }
        if ($key == 'title') {
          $data['title'] = \Drupal::token()->replace($value, ['node' => $this->entity]);
          continue;
        }
        $data['meta'][] = [
          'name' => $key,
          'content' => $content,
        ];
      }
    }
  }

}
