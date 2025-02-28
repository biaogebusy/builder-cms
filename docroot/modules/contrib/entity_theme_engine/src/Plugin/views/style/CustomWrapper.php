<?php

namespace Drupal\entity_theme_engine\Plugin\views\style;

use Drupal\views\Plugin\views\style\DefaultStyle;
use Drupal\Core\Form\FormStateInterface;

/**
 * Unformatted style plugin to render rows one after another with no
 * decorations.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "custom_wrapper",
 *   title = @Translation("Custom Wrapper"),
 *   help = @Translation("Displays rows with custom wrapper."),
 *   theme = "views_view_custom_wrapper",
 *   display_types = {"normal"}
 * )
 */
class CustomWrapper extends DefaultStyle {

  /**
   * Set default options
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    
    $options['wrapper_tag'] = ['default' => 'div'];
    $options['wrapper_class'] = ['default' => ''];
    $options['wrapper_attribute'] = ['default' => ''];
    $options['item_tag'] = ['default' => 'div'];
    
    return $options;
  }
  
  /**
   * Render the given style.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['wrapper_tag'] = [
      '#title' => $this->t('Wrapper Tag'),
      '#description' => $this->t('The wrapper tag.'),
      '#type' => 'textfield',
      '#size' => '30',
      '#default_value' => $this->options['wrapper_tag'],
    ];
    $form['wrapper_class'] = [
      '#title' => $this->t('Wrapper class'),
      '#description' => $this->t('The class to provide on the wrapper.'),
      '#type' => 'textfield',
      '#size' => '30',
      '#default_value' => $this->options['wrapper_class'],
    ];
    $form['wrapper_attribute'] = [
      '#title' => $this->t('Wrapper Attribute'),
      '#description' => $this->t('The attribue to provide on the wrapper'),
      '#type' => 'textfield',
      '#size' => '30',
      '#default_value' => $this->options['wrapper_attribute'],
    ];
    $form['item_tag'] = [
      '#title' => $this->t('Item tag'),
      '#description' => $this->t('The item tag.'),
      '#type' => 'textfield',
      '#size' => '30',
      '#default_value' => $this->options['item_tag'],
    ];
    parent::buildOptionsForm($form, $form_state);
  }
  
}
