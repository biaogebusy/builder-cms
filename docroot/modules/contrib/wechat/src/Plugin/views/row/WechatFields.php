<?php

namespace Drupal\wechat\Plugin\views\row;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\row\RowPluginBase;

/**
 * Renders an wechat response item based on fields.
 *
 * @ViewsRow(
 *   id = "wechat_fields",
 *   title = @Translation("Wechat fields"),
 *   help = @Translation("Display fields as wechat response."),
 *   theme = "views_view_row_wechat",
 *   register_theme = FALSE,
 *   display_types = {"wechat_response"}
 * )
 */
class WechatFields extends RowPluginBase {

  /**
   * Does the row plugin support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['title_field'] = array('default' => '');
    $options['description_field'] = array('default' => '');
    $options['pic_url_field'] = array('default' => '');
    $options['url_field'] = array('default' => '');
    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $initial_labels = array('' => $this->t('- None -'));
    $view_fields_labels = $this->displayHandler->getFieldLabels();
    $view_fields_labels = array_merge($initial_labels, $view_fields_labels);

    $form['title_field'] = array(
      '#type' => 'select',
      '#title' => $this->t('Title field'),
      '#description' => $this->t('The field that is going to be used as the wechat response item title for each row.'),
      '#options' => $view_fields_labels,
      '#default_value' => $this->options['title_field'],
      '#required' => TRUE,
    );

    $form['description_field'] = array(
      '#type' => 'select',
      '#title' => $this->t('Description field'),
      '#description' => $this->t('The field that is going to be used as the wechat response item description for each row.'),
      '#options' => $view_fields_labels,
      '#default_value' => $this->options['description_field'],
      '#required' => TRUE,
    );
    $form['pic_url_field'] = array(
      '#type' => 'select',
      '#title' => $this->t('Picture url field'),
      '#description' => $this->t('The field that is going to be used as the wechat response item picture url for each row.'),
      '#options' => $view_fields_labels,
      '#default_value' => $this->options['pic_url_field'],
      '#required' => TRUE,
    );
    $form['url_field'] = array(
      '#type' => 'select',
      '#title' => $this->t('Url field'),
      '#description' => $this->t('The field that is going to be used as the wechat response item url for each row.'),
      '#options' => $view_fields_labels,
      '#default_value' => $this->options['url_field'],
      '#required' => TRUE,
    );	
  }

  public function validate() {
    $errors = parent::validate();
    $required_options = array('title_field', 'description_field', 'pic_url_field', 'url_field');
    foreach ($required_options as $required_option) {
      if (empty($this->options[$required_option])) {
        $errors[] = $this->t('Row style plugin requires specifying which views fields to use for wechat response item.');
        break;
      }
    }
    return $errors;
  }

  public function render($row) {
    static $row_index;
    if (!isset($row_index)) {
      $row_index = 0;
    }


    // Create the RSS item object.
    $item = new \stdClass();
    $item->title = $this->getField($row_index, $this->options['title_field']);
	$item->description = $this->getField($row_index, $this->options['description_field']) . t('(@亚艾元)');
	$item->pic_url = $this->getField($row_index, $this->options['pic_url_field']);
	$item->url = $this->getField($row_index, $this->options['url_field']);

    $row_index++;

    $build = array(
      '#theme' => 'views_view_row_wechat',
      '#view' => $this->view,
      '#options' => $this->options,
      '#row' => $item,
      '#field_alias' => isset($this->field_alias) ? $this->field_alias : '',
    );

    return $build;
  }

  /**
   * Retrieves a views field value from the style plugin.
   *
   * @param $index
   *   The index count of the row as expected by views_plugin_style::getField().
   * @param $field_id
   *   The ID assigned to the required field in the display.
   *
   * @return string|null|\Drupal\Component\Render\MarkupInterface
   *   An empty string if there is no style plugin, or the field ID is empty.
   *   NULL if the field value is empty. If neither of these conditions apply,
   *   a MarkupInterface object containing the rendered field value.
   */
  public function getField($index, $field_id) {
    if (empty($this->view->style_plugin) || !is_object($this->view->style_plugin) || empty($field_id)) {
      return '';
    }
    return $this->view->style_plugin->getField($index, $field_id);
  }

}
