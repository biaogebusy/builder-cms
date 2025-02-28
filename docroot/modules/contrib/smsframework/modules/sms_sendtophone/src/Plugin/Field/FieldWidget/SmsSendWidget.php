<?php

declare(strict_types = 1);

namespace Drupal\sms_sendtophone\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'sms_sendtophone' widget.
 *
 * @FieldWidget(
 *   id = \Drupal\sms_sendtophone\Plugin\Field\FieldWidget\SmsSendWidget::PLUGIN_ID,
 *   label = @Translation("Text Field and SMS send to phone"),
 *   field_types = {
 *     "text"
 *   }
 * )
 */
class SmsSendWidget extends WidgetBase {

  public const PLUGIN_ID = 'sms_sendtophone';

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field = $this->fieldDefinition;

    $element['value'] = $element + [
      '#title' => $this->getSetting('label'),
      '#default_value' => $items[$delta]->value,
      '#required' => $field->isRequired(),
      '#description' => $this->getSetting('description'),
      '#maxlength' => $field->getSetting('max_length'),
      '#weight' => $this->getSetting('weight'),
    ];
    if ($this->getSetting('rows') == 1) {
      $element['#type'] = 'textfield';
    }
    else {
      $element['#type'] = 'textarea';
      $element['#rows'] = $this->getSetting('rows');
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return ['rows' => 1];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['rows'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Rows'),
      '#default_value' => $this->settings['rows'],
      '#required' => TRUE,
    ];
    return $form;
  }

}
