<?php

namespace Drupal\commerce_order\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the order details form.
 */
class OrderDetailsForm extends OrderFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['#prefix'] = '<div id="order-details-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['errors'] = [
      '#type' => 'container',
      '#weight' => -99,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#ajax'] = [
      'callback' => '::ajaxSubmit',
      'element' => ['errors'],
    ];
    $actions['delete']['#access'] = FALSE;
    $actions['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->entity->toUrl(),
      '#attributes' => [
        'class' => ['button', 'dialog-cancel'],
      ],
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->setData('customer_email_overridden', TRUE);
    return parent::save($form, $form_state);
  }

  /**
   * Submit form dialog #ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response that display validation error messages or represents a
   *   successful submission.
   */
  public function ajaxSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new HtmlCommand('#order-details-form-wrapper', $form));
      $response->addCommand(new PrependCommand('[data-drupal-selector="' . $form['errors']['#attributes']['data-drupal-selector'] . '"]', ['#type' => 'status_messages']));
      return $response;
    }

    $response->addCommand(new RedirectCommand($this->entity->toUrl()->toString()));
    return $response;
  }

}
