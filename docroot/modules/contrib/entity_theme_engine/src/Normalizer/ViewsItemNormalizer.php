<?php

namespace Drupal\entity_theme_engine\Normalizer;


use Drupal\views\Views;

class ViewsItemNormalizer extends FieldItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ['Drupal\viewsreference\Plugin\Field\FieldType\ViewsReferenceItem'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    $data = parent::normalize($field, $format, $context);
    
    $view_name = $field->target_id;
    $display_id = $field->display_id;
    $options = unserialize($field->data);
    $view = Views::getView($view_name);
    $view->setDisplay($display_id);
    
    if(!empty($options)) {
      $view->element['#viewsreference'] = [
        'data' => $options,
        'enabled_settings' => array_keys(array_filter($options)),
      ];
    }
    
    $view->preExecute();
    $view->execute($display_id);
    
    $render = $view->render();
    
    $empty = empty($view->result);
    
    $data['rows'] = $render['#rows'];
    $data['header'] = $view->display_handler->renderArea('header', $empty);
    $data['footer'] = $view->display_handler->renderArea('footer', $empty);
    $data['empty'] = $empty ? $view->display_handler->renderArea('empty', $empty) : [];
    $data['exposed'] = !empty($view->exposed_widgets) ? $view->exposed_widgets : [];
    $data['more'] = $view->display_handler->renderMoreLink();
    $data['feed_icons'] = !empty($view->feedIcons) ? $view->feedIcons : [];
    if ($view->display_handler->renderPager()) {
      $exposed_input = isset($view->exposed_raw_input) ? $view->exposed_raw_input : NULL;
      $data['pager'] = $view->renderPager($exposed_input);
    }
    
    if (!empty($view->attachment_before)) {
      $data['attachment_before'] = $view->attachment_before;
    }
    if (!empty($view->attachment_after)) {
      $data['attachment_after'] = $view->attachment_after;
    }
    
    $data['render'] = empty($render['#rows'])?$data['empty']:$render['#rows'];
    $data['title'] = $view->getTitle();
    $data['#cache'] = $render['#cache'];
    return $data;
  }
}
