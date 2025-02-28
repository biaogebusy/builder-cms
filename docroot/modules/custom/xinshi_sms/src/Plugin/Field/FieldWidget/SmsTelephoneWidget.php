<?php

namespace Drupal\xinshi_sms\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\telephone\Plugin\Field\FieldWidget\TelephoneDefaultWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\user\Entity\User;

/**
 * Plugin implementation of the 'sms_telephone' widget.
 *
 * @FieldWidget(
 *   id = "xinshi_sms_telephone",
 *   label = @Translation("XINSHI SMS Framework Telephone"),
 *   field_types = {
 *     "telephone"
 *   }
 * )
 */
class SmsTelephoneWidget extends TelephoneDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $form_state->getFormObject()->getEntity();
    $display_element = [];
    $display_element['#attached']['library'][] = 'xinshi_sms/opt_login';
    $display_element['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $value = $items[$delta]->value;
    $display_element['#type'] = 'container';
    $display_element['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
      '#value' => $element['#title'],
    ];
    if ($value) {
      $display_element['value'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['id' => 'user-bind-phone'],
        '#value' => substr_replace($value, '****', 3, 4),
      ];
    } else {
      $display_element['value'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['id' => 'user-bind-phone'],
        '#value' => $this->t('You have not bound a mobile phone number！'),
      ];
    }
    if ($entity instanceof User && $entity->id() == \Drupal::currentUser()->id()) {
      $display_element['displayed']['bind'] = [
        '#type' => 'link',
        '#title' => $this->t($value ? 'Change Mobile Phone' : 'Bind Mobile Phone'),
        '#url' => Url::fromRoute('xinshi_sms.bind_phone_form'),
        '#options' => [
          'attributes' => [
            'class' => ['use-ajax', 'button'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode([
              'width' => 700,
            ]),
          ],
        ],
      ];
    }

    return $display_element;
  }
}
