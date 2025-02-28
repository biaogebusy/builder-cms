<?php

namespace Drupal\wechat\Plugin\views\display;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * The plugin that handles an wechat display.
 *
 * @ingroup views_display_plugins
 *
 * @todo: Wait until annotations/plugins support access methods.
 * no_ui => !\Drupal::config('views.settings')->get('ui.show.display_embed'),
 *
 * @ViewsDisplay(
 *   id = "wechat_response",
 *   title = @Translation("Wechat response"),
 *   help = @Translation("Provide a display for wechat response."),
 *   uses_menu_links = FALSE,
 *   theme = "views_view"
 * )
 */
class WechatResponse extends DisplayPluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesAJAX = FALSE;


  /**
   * {@inheritdoc}
   */
  protected $usesAttachments = FALSE;
  
  
  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Force the style plugin to 'entity_reference_style' and the row plugin to
    // 'fields'.
	
    $options['style']['contains']['type'] = array('default' => 'wechat_response');
    $options['defaults']['default']['style'] = FALSE;
    $options['row']['contains']['type'] = array('default' => 'wechat_fields');
    $options['defaults']['default']['row'] = FALSE;

    // Make sure the query is not cached.
    $options['defaults']['default']['cache'] = FALSE;

    // Set the display title to an empty string (not used in this display type).
    $options['title']['default'] = '';
    $options['defaults']['default']['title'] = FALSE;

    return $options;
  }  
  
  /**
   * Overrides \Drupal\views\Plugin\views\display\DisplayPluginBase::optionsSummary().
   *
   * Disable 'cache' and 'title' so it won't be changed.
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);
    unset($options['query']);
    unset($options['title']);
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return 'wechat_response';
  }

  
  /**
   * {@inheritdoc}
   */
  public function buildRenderable(array $args = [], $cache = TRUE) {
    $build = parent::buildRenderable($args, $cache);
    //$build['#wechat'] = TRUE;
    return $build;
  }
  
  /**
   * {@inheritdoc}
   */
  public function execute() {
    parent::execute();

    return $this->view->render();
  }
  
  /**
   * {@inheritdoc}
   */
  public function preview() {
    $output = $this->view->render();

    if (!empty($this->view->live_preview)) {
      $output = array(
        '#prefix' => '<pre>',
        '#plain_text' => drupal_render_root($output),
        '#suffix' => '</pre>',
      );
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = $this->view->style_plugin->render($this->view->result);

    $this->applyDisplayCachablityMetadata($build);

    return $build;
  }  

}
