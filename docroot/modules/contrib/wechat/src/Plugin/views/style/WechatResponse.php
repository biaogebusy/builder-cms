<?php

namespace Drupal\wechat\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Default style plugin to render an RSS feed.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "wechat_response",
 *   title = @Translation("Wechat response"),
 *   help = @Translation("Generates a wechat response from a view."),
 *   theme = "views_view_wechat",
 *   register_theme = FALSE,
 *   display_types = {"wechat_response"}
 * )
 */
class WechatResponse extends StylePluginBase {

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  public function attachTo(array &$build, $display_id, Url $feed_url, $title) {

  }

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['description'] = array('default' => '');

    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Return an array of additional XHTML elements to add to the channel.
   *
   * @return
   *   A render array.
   */
  protected function getChannelElements() {
    return array();
  }

  /**
   * Get RSS feed description.
   *
   * @return string
   *   The string containing the description with the tokens replaced.
   */
  public function getDescription() {
    $description = $this->options['description'];

    // Allow substitutions from the first row.
    $description = $this->tokenizeValue($description, 0);

    return $description;
  }

  public function render() {
    if (empty($this->view->rowPlugin)) {
      debug('Drupal\wechat\Plugin\views\style\WechatResponse: Missing row plugin');
      return array();
    }
    $rows = [];

    // This will be filled in by the row plugin and is used later on in the
    // theming output.
    $this->namespaces = array('xmlns:dc' => 'http://purl.org/dc/elements/1.1/');

    // Fetch any additional elements for the channel and merge in their
    // namespaces.
	/*
    $this->channel_elements = $this->getChannelElements();
    foreach ($this->channel_elements as $element) {
      if (isset($element['namespace'])) {
        $this->namespaces = array_merge($this->namespaces, $element['namespace']);
      }
    }
	*/

    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows[] = $this->view->rowPlugin->render($row);
    }

    $build = array(
      '#theme' => 'views_view_wechat',
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => $rows,
    );
    unset($this->view->row_index);
    return $build;
  }

}
