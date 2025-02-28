<?php

namespace Drupal\entity_theme_engine;


use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_theme_engine\Entity\EntityWidget;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\field\Entity\FieldConfig;
use Symfony\Component\Serializer\SerializerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\Element;

/**
 * Widget Helper.
 */
class EntityWidgetService {

  /**
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  public $serializer;

  /**
   * Constructs.
   *
   * @param SerializerInterface $serializer
   */
  public function __construct(SerializerInterface $serializer) {
    $this->serializer = $serializer;
  }

  /**
   * Entity view alter.
   * @param array $build
   * @param EntityInterface $entity
   * @param string $display
   */
  public function entityViewAlter(array &$build, EntityInterface $entity, string $display = 'default') {
    if($display == 'full'){
      $display = 'default';
    }
    if($widget = $this->getWidget($entity, $display)) {
      $build = $this->renderEntity($build, $widget, $entity, $display);
    }
  }
  /**
   * Render widget entity.
   *
   * @param array $build
   * @param EntityWidget $widget
   * @param EntityInterface $entity
   * @param string $display
   * @return array
   */
  public function renderEntity(array $build, EntityWidget $widget, EntityInterface $entity, $display) {
    $variables = $this->getRenderVariables($build, $widget, $entity);
    $cache = [
      'contexts' => [],
      'tags' => Cache::mergeTags($widget->getCacheTags(), $entity->getCacheTags()),
      'max-age' => -1,
    ];
    $cache = $this->mergeCache($cache, $variables['#cache']);
    if(isset($build['#cache'])) {
      $cache = $this->mergeCache($cache, $build['#cache']);
    }
    $variables['#cache'] = $cache;
    $render = [
      '#prefix' => "<div class=\"entity-widget-{$widget->id()} entity-widget-entity-type-{$entity->getEntityTypeId()}\">",
      '#suffix' => "</div>",
      "#{$entity->getEntityTypeId()}" => $entity,
      '#view_mode' => $display,
      '#attached' => isset($build['#attached'])?$build['#attached']:[],
    ];
    $render['content'] = [
      '#type' => 'inline_template',
      '#template' => $widget->getTemplate(),
      '#context' => $variables,
      '#cache' => $cache
    ];
    if($library = $widget->getLibrary()) {
      $render['#attached']['library'][] = $library;
    }
    $this->addSpacer($render['content']);
    return $render;
  }

  /**
   * Get widget.
   * @param EntityInterface $entity
   * @param string $display
   * @return EntityWidget|NULL
   */
  public function getRenderVariables(array $build, EntityWidget $widget, EntityInterface $entity) {
    $context = [
      'entity_widget' => $widget,
      'entity' => $entity,
      'attach_mode' => FALSE,
      'level' => 1,
    ];
    $variables = $this->serializer->normalize($entity, 'twig_variable', $context);
    $variables['base_path'] = base_path();
    foreach ($variables as $key => $value) {
      if(isset($build[$key])) {
        $variables[$key]['#original'] = $build[$key];
      }
    }
    foreach (Element::children($build) as $key) {
      if(!isset($variables[$key])) {
        $variables[$key] = $build[$key];
      }
    }
    if($widget->isAttach()) {
      $attach_types = [
        'node',
        'group',
        'taxonomy_term',
      ];
      foreach ($attach_types as $type_id) {
        if($attach = \Drupal::routeMatch()->getParameter($type_id)) {
          if(!is_object($attach)) {
            $attach = \Drupal::entityTypeManager()->getStorage($type_id)->load($attach);
          }
          $context = [
            'entity_widget' => $widget,
            'entity' => $attach,
            'attach_mode' => TRUE,
            'level' => 1,
          ];
          $variables['attach_entity'] = $this->serializer->normalize($attach, 'twig_variable', $context);
          $variables['#cache'] = $this->mergeCache($variables['#cache'], $variables['attach_entity']['#cache']);
          break;
        }
      }
    }
    \Drupal::moduleHandler()->alter('entity_widget_variables', $variables, $widget, $entity);
    return $variables;
  }
  /**
   * Get widget.
   * @param EntityInterface $entity
   * @param string $display
   * @return EntityWidget|NULL
   */
  public function getWidget(EntityInterface $entity, string $display) {
    $widgets = $this->getAllWidgets();
    $active_theme = \Drupal::theme()->getActiveTheme()->getName();
    $keys = [
      "{$active_theme}:{$entity->getEntityTypeId()}:{$entity->bundle()}:{$display}",
      "{$entity->getEntityTypeId()}:{$entity->bundle()}:{$display}",
    ];
    foreach ($keys as $key) {
      if(isset($widgets[$key])) {
        return $widgets[$key];
      }
    }
    return NULL;
  }
  /**
   * Get all widgets.
   * @return array|mixed|mixed[]|\Drupal\Core\Entity\EntityInterface
   */
  public function getAllWidgets() {
    $cache = &drupal_static(__METHOD__, []);
    if(empty($cache)) {
      $widgets = \Drupal::entityTypeManager()->getStorage('entity_widget')->loadMultiple();
      foreach ($widgets as $widget) {
        if($widget->isEnabled()) {
          if(!empty($widget->getTheme())) {
            $cache["{$widget->getTheme()}:{$widget->getWidgetEntityType()}:{$widget->getBundle()}:{$widget->getDisplay()}"] = $widget;
          }else {
            $cache["{$widget->getWidgetEntityType()}:{$widget->getBundle()}:{$widget->getDisplay()}"] = $widget;
          }
          $cache["{$widget->getWidgetEntityType()}:{$widget->getBundle()}"] = $widget;
        }
      }
    }
    return $cache;
  }
  /**
   * Add spacer.
   * @param array $build
   */
  protected function addSpacer(array &$build)
  {
    $name = false;
    $variables = $build['#context'];
    if(!empty($variables['spacer']['value'])) {
      $name = $variables['spacer']['value'];
    }elseif(!empty($variables['field_spacer']['value'])) {
      $name = $variables['field_spacer']['value'];
    }
    if($name) {
      switch ($name) {
        case 'large_bottom':
          $build['#suffix'] = '<div class="spacer-lg"></div>';
          break;
        case 'large_top':
          $build['#prefix'] = '<div class="spacer-lg"></div>';
          break;
        case 'large_both':
          $build['#prefix'] = '<div class="spacer-lg"></div>';
          $build['#suffix'] = '<div class="spacer-lg"></div>';
          break;
        case 'medium_bottom':
          $build['#suffix'] = '<div class="spacer-md"></div>';
          break;
        case 'medium_top':
          $build['#prefix'] = '<div class="spacer-md"></div>';
          break;
        case 'medium_both':
          $build['#prefix'] = '<div class="spacer-md"></div>';
          $build['#suffix'] = '<div class="spacer-md"></div>';
          break;
        case 'small_bottom':
          $build['#suffix'] = '<div class="spacer-sm"></div>';
          break;
        case 'small_top':
          $build['#prefix'] = '<div class="spacer-sm"></div>';
          break;
        case 'small_both':
          $build['#prefix'] = '<div class="spacer-sm"></div>';
          $build['#suffix'] = '<div class="spacer-sm"></div>';
          break;
        default :
          break;
      }
    }
  }
  /**
   *
   * @param array $a
   * @param array $b
   * @return string[][]|number[]
   */
  public function  mergeCache(array $a, array $b) {
    $cache = [];
    $cache['contexts'] = Cache::mergeContexts($a['contexts']?:[],$b['contexts']?:[]);
    $cache['tags'] = Cache::mergeTags($a['tags']?:[],$b['tags']?:[]);
    $cache['max-age'] = Cache::mergeMaxAges($a['max-age'],$b['max-age']);
    return $cache;
  }
}
