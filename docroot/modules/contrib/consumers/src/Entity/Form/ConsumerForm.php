<?php

namespace Drupal\consumers\Entity\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Consumer edit forms.
 */
class ConsumerForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['is_default']['#access'] = FALSE;
    $form['client_id']['generate'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate random Client ID'),
      '#limit_validation_errors' => [$form['client_id']['widget']['#parents']],
      '#attributes' => [
        'class' => [
          'button--small',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'generateClientId'],
        'disable-refocus' => TRUE,
        'wrapper' => 'edit-client-id-wrapper',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = $this->entity->save();
    $label = $this->entity->label();
    $args = [
      '%label' => $label,
      '@type' => $this->entity->getEntityType()->getLabel(),
    ];

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label @type.', $args));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label @type.', $args));
    }
    $form_state->setRedirect('entity.consumer.collection');
    return $status;
  }

  /**
   * AJAX callback that generates the client ID.
   */
  public function generateClientId(array &$form, FormStateInterface $form_state): array {
    $form['client_id']['widget'][0]['value']['#value'] = Crypt::randomBytesBase64();
    return $form['client_id'];
  }

}
