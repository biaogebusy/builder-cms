<?php

namespace Drupal\xinshi_api;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Drupal\webform\Entity\Webform;

/**
 * Class EntityJsonBase
 * @package Drupal\xinshi_api
 */
class EntityJsonBase implements EntityJsonInterface {

  /**
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var string
   */
  protected $mode = 'json';

  /**
   * @var array
   */
  private $cacheTags = [];

  /**
   * Undocumented variable
   *
   * @var LayoutBuilderEntityViewDisplay
   */
  protected $layoutBuilder;

  /**
   * EntityJsonBase constructor.
   * @param EntityInterface $entity
   * @param string $mode
   */
  function __construct(EntityInterface $entity, $mode = 'json') {
    $this->entity = $entity;
    $this->mode = $mode;
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->setCacheTags($entity->getCacheTags());
  }

  public function getLayoutBuilder() {
    return $this->layoutBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public function getContent() {
    // TODO: Implement getJson() method.
    $data = [];
    $build = $this->entityTypeManager->getViewBuilder($this->entity->getEntityTypeId())->view($this->entity, $this->mode);
    switch ($this->entity->getEntityTypeId()) {
      case 'node':
      case 'user':
      case 'commerce_product':
        \Drupal::service('entity_theme_engine.entity_widget_service')->entityViewAlter($build, $this->entity, $this->mode);
        break;
    }
    $this->addCacheTags($build['content']['#cache']['tags'] ?? []);
    unset($build['#prefix']);
    unset($build['#suffix']);
    $content = \Drupal::service('renderer')->render($build);
    if (($str = (string) $content) && $data = Json::decode(htmlspecialchars_decode($str))) {
      $this->setFullText($data);
    }
    return $data ? $data : [];
  }

  /**
   * Set Full text value.
   * @param $data
   */
  public function setFullText(&$data) {
    if (empty($data)) {
      return;
    }
    foreach ($data as $key => &$val) {
      if (is_array($val)) {
        if (isset($val['dataType']) && isset($val['data'])) {
          switch ($val['dataType']) {
            case 'full_text':
              if (isset($val['limit']) && is_numeric($val['limit'])) {
                $val = mb_substr(urldecode($val['data']), 0, $val['limit']);
              } else {
                $val = urldecode($val['data']);
              }
              break;
            case "json_encode":
              $val = json_decode(urldecode($val['data']));
              break;
            case "boolean":
              $val = !empty($val['data']);
              break;
            case "webform":
              $val = $this->getWebform($val['data']);
              break;
            case 'query_link':
              $url = $val['data'];
              $append = $val['append'] ?? [];
              $this->setFullText($append);
              $val = $append;
              if (UrlHelper::isValid($url)) {
                $options = UrlHelper::parse($url);
                $val['href'] = $options['path'];
                $val['queryParams'] = $options['query'];
              } else {
                $val['href'] = $val['data'];
              }
              break;
          }
        } else {
          $this->setFullText($val);
        }
      }
    }
  }

  /**
   * Set Full text value.
   * @param $data
   */
  private function urlDecodeValue(&$data) {
    foreach ($data as $key => &$val) {
      if (is_array($val)) {
        $this->urlDecodeValue($val);
      } elseif (is_string($val)) {
        $val = urldecode($val);
      }
    }
  }

  /**
   * Return cache tags.
   * @return array
   */
  public function getCacheTags() {
    return $this->cacheTags;
  }

  /**
   * Set cache tags.
   * @param array $cacheTags
   */
  public function setCacheTags($cacheTags) {
    $this->cacheTags = $cacheTags;
  }

  /**
   * Add cache tags.
   * @param $tag string|array
   */
  public function addCacheTags($tag) {
    if ($tag) {
      $this->cacheTags = array_unique(array_merge($this->cacheTags, is_array($tag) ? $tag : [$tag]));
    }
  }

  private function getWebform($webform_id) {
    if (empty($webform_id) || empty($webform = Webform::load($webform_id))) {
      return [];
    }
    $elements = [];
    foreach ($webform->getElementsDecodedAndFlattened() as $key => $item) {
      $element = [];
      $element["label"] = $item['#title'];
      $element["key"] = $key;
      if (isset($item['#placeholder'])) {
        $element['placeholder'] = $item['#placeholder'];
      }
      foreach ($item as $name => $val) {
        if (!in_array($name, ['#title', '#placeholder', '#type'])) {
          if (strpos($name, '#') === 0) {
            $name = substr($name, 1);
          }
          $element['params'][$name] = $val;
        }
      }
      switch ($item['#type']) {
        case 'textfield':
        case 'email':
          $element["type"] = "input";
          break;
        case 'textarea':
          $element["type"] = "textarea";
          if (isset($item['#rows'])) {
            $element['params']['matAutosizeMinRows'] = $item['#rows'];
          }
          break;
        case 'webform_terms_of_service':
          $element["type"] = "terms_of_service";
          $element['params']['url'] = Url::fromUserInput(str_replace('_', '-', '/' . $webform_id . '-' . $key))->toString();
          break;
        default:
          $element = [];
          break;
      }
      if ($element) {
        $elements[] = $element;
      }
    }
    return $elements;
  }

  /**
   * 检查当前实体是否使用Layout Builder进行显示配置
   *
   * 不会修改原始 $this->mode，会尝试当前 mode、'full'、'default'
   *
   * @return bool
   */
  public function isLayoutBuilder() {
    $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
    $modes = [$this->mode, 'full', 'default'];
    foreach ($modes as $display_mode) {
      $display = $storage->load($this->entity->getEntityTypeId() . '.' . $this->entity->bundle() . '.' . $display_mode);
      if (!$display) {
        continue;
      }
      // 或者检查 third party settings 中是否有 layout_builder 的配置
      $settings = $display->get('third_party_settings') ?: [];
      if (!empty($settings['layout_builder']) && $settings['layout_builder']['enabled']) {
        $this->layoutBuilder = $display;
        return true;
      }
    }
    return false;
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
  protected function renderLayoutBuilder() {
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
