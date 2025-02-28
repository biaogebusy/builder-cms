<?php

namespace Drupal\advancedqueue\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Component\Serialization\Json as JsonDecoder;

/**
 * Field handler to show data of json stored fields.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("advancedqueue_json")
 */
class Json extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['key'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Which key should be displayed'),
      '#default_value' => $this->options['key'],
      '#states' => [
        'visible' => [
          ':input[name="options[format]"]' => ['value' => 'key'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $values->{$this->field_alias};

    $decoded = (array) JsonDecoder::decode($value);
    if (!empty($this->options['key'])) {
      if (isset($decoded[$this->options['key']])) {
        return $this->sanitizeValue($decoded[$this->options['key']]);
      }
      return '';
    }
    else {
      $decoded = (array) JsonDecoder::decode($value);
      return $this->sanitizeValue(print_r($decoded, TRUE));
    }
  }

}
