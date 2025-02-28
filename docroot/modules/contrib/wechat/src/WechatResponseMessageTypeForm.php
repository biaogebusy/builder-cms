<?php

/**
 * @file
 * Contains \Drupal\wechat\WechatResponseMessageTypeForm.
 */

namespace Drupal\wechat;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base form for response message type edit forms.
 */
class WechatResponseMessageTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /* @var \Drupal\wechat\WechatResponseMessageTypeInterface $response_message_type */
    $response_message_type = $this->entity;

    if ($this->operation == 'add') {
      $form['#title'] = $this->t('Add response message type');
    }
    else {
      $form['#title'] = $this->t('Edit %label response message type', array('%label' => $response_message_type->label()));
    }

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#maxlength' => 255,
      '#default_value' => $response_message_type->label(),
      '#description' => t("Provide a label for this response message type to help identify it in the administration pages."),
      '#required' => TRUE,
    );
    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $response_message_type->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\wechat\Entity\WechatResponseMessageType::load',
      ),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
    );

    $form['description'] = array(
      '#type' => 'textarea',
      '#default_value' => $response_message_type->getDescription(),
      '#description' => t('Enter a description for this response message type.'),
      '#title' => t('Description'),
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $response_message_type = $this->entity;
    $status = $response_message_type->save();

    $edit_link = $this->entity->link($this->t('Edit'));
    $logger = $this->logger('wechat');
    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Response message type %label has been updated.', array('%label' => $response_message_type->label())));
      $logger->notice('Response message type %label has been updated.', array('%label' => $response_message_type->label(), 'link' => $edit_link));
    }
    else {
      drupal_set_message(t('Response message type %label has been added.', array('%label' => $response_message_type->label())));
      $logger->notice('Response message %label has been added.', array('%label' => $response_message_type->label(), 'link' => $edit_link));
    }

    $form_state->setRedirectUrl($this->entity->urlInfo('collection'));
  }

}
