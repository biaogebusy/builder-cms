<?php

/**
 * @file
 * Contains \Drupal\wechat\WechatRequestMessageTypeForm.
 */

namespace Drupal\wechat;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base form for request message type edit forms.
 */
class WechatRequestMessageTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /* @var \Drupal\wechat\WechatRequestMessageTypeInterface $request_message_type */
    $request_message_type = $this->entity;

    if ($this->operation == 'add') {
      $form['#title'] = $this->t('Add request message type');
    }
    else {
      $form['#title'] = $this->t('Edit %label request message type', array('%label' => $request_message_type->label()));
    }

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#maxlength' => 255,
      '#default_value' => $request_message_type->label(),
      '#description' => t("Provide a label for this request message type to help identify it in the administration pages."),
      '#required' => TRUE,
    );
    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $request_message_type->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\wechat\Entity\WechatRequestMessageType::load',
      ),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
    );

    $form['description'] = array(
      '#type' => 'textarea',
      '#default_value' => $request_message_type->getDescription(),
      '#description' => t('Enter a description for this request message type.'),
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
    $request_message_type = $this->entity;
    $status = $request_message_type->save();

    $edit_link = $this->entity->link($this->t('Edit'));
    $logger = $this->logger('wechat');
    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Request message type %label has been updated.', array('%label' => $request_message_type->label())));
      $logger->notice('Request message type %label has been updated.', array('%label' => $request_message_type->label(), 'link' => $edit_link));
    }
    else {
      drupal_set_message(t('Request message type %label has been added.', array('%label' => $request_message_type->label())));
      $logger->notice('Request message %label has been added.', array('%label' => $request_message_type->label(), 'link' => $edit_link));
    }

    $form_state->setRedirectUrl($this->entity->urlInfo('collection'));
  }

}
