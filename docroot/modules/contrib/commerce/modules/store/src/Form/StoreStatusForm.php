<?php

namespace Drupal\commerce_store\Form;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines the Store status form.
 */
class StoreStatusForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $store = $this->getEntity();
    assert($store instanceof StoreInterface);
    $t_args = ['%label' => $store->label()];
    return $store->isPublished() ?
      $this->t('Are you sure you want to disable the store %label?', $t_args) :
      $this->t('Are you sure you want to enable the store %label?', $t_args);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    $store = $this->getEntity();
    assert($store instanceof StoreInterface);
    return $store->isPublished() ? $this->t('Disable') : $this->t('Enable');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.commerce_store.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_store\Entity\StoreInterface $store */
    $store = $this->getEntity();
    $store->set('status', !$store->get('status')->value);
    $store->save();
    if ($store->isPublished()) {
      $this->messenger()->addStatus($this->t('Successfully enabled the store %label.', ['%label' => $store->label()]));
    }
    else {
      $this->messenger()->addStatus($this->t('Successfully disabled the store %label.', ['%label' => $store->label()]));
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
