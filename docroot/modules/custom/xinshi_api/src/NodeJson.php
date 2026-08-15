<?php

namespace Drupal\xinshi_api;

use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
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
    } elseif ($this->isPanelizer()) {
      $displays = $this->entity->get('panelizer')->panels_display;
      foreach ($displays['blocks'] as $display) {
        switch ($display['provider']) {
          case 'block_content':
            if (empty($display['vid'])) {
              $block = $this->entityTypeManager->getStorage($display['provider'])->loadByProperties(['uuid' => explode(':', $display['id'])[1]]);
            } else {
              $block = $this->entityTypeManager->getStorage('block_content')->loadRevision($display['vid']);
            }
            if ($block) {
              $entityJson = new EntityJsonBase(is_array($block) ? current($block) : $block);
              $widgets[] = [
                'weight' => $display['weight'],
                'content' => $entityJson->getContent(),
                'type' => $entityJson->entity->bundle(),
              ];
              $this->addCacheTags($entityJson->getCacheTags());
            }
            break;
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
              'title' => $content['#configuration']['views_label'] ? $content['#configuration']['views_label'] : $content['content']['#title']['#markup']
            ];
            $this->addCacheTags($content['content']['#cache']['tags']);
            break;
        }
      }
    } else {
      $panels = $this->renderLayoutBuilder();
    }
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

  /**
   * 渲染布局构建器中的视图块
   *
   * 该方法加载与当前实体类型和包关联的布局构建器配置，并渲染其中的视图块。
   * 对于每个视图组件，会检查访问权限，执行视图并收集渲染结果。
   *
   * @return array 返回渲染后的视图块数组，格式为：
   *   - 键：视图名称_显示ID（如"view_name_display_id"）
   *   - 值：包含以下键的数组：
   *     - 'rows': 视图的行数据
   *     - 'title': 视图标题或配置的标签
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function renderLayoutBuilder() {
    $builder = LayoutBuilderEntityViewDisplay::load($this->entity->getEntityTypeId() . '.' . $this->entity->bundle() . '.json');
    if (empty($builder)) {
      return [];
    }
    $blocks = [];
    /** @var Section $section */
    foreach ($builder->getSections() as $section) {
      /** @var SectionComponent $component */
      foreach ($section->getComponents() as $component) {
        $configuration = $component->get('configuration');
        if ($configuration['provider'] == 'views') {
          $id = explode(':', $configuration['id'])[1];
          $view_name = explode('-', $id)[0];
          $display_id = explode('-', $id)[1];
          /** @var ViewExecutable $view */
          $view = Views::getView($view_name);
          if ($view && $view->access($display_id)) {
            $view->setDisplay($display_id);
            $view->preExecute();
            $view->execute($display_id);
            $render = $view->render();
            $blocks["{$view_name}_{$display_id}"] = [
              'rows' => $render['#rows'][0]['#rows'] ?? [],
              'title' => empty($configuration['views_label']) ? $view->getTitle() : $configuration['views_label'],
            ];
            $this->addCacheTags($view->getCacheTags());
          }
        }
      }
    }
    return $blocks;
  }
}
