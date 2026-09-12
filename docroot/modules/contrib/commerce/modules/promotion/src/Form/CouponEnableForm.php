<?php

namespace Drupal\commerce_promotion\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines the coupon enable form.
 */
class CouponEnableForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to enable the coupon %label?', [
      '%label' => $this->getEntity()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Enable');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    /** @var \Drupal\commerce_promotion\Entity\CouponInterface $coupon */
    $coupon = $this->getEntity();
    return new Url('entity.commerce_promotion_coupon.collection', [
      'commerce_promotion' => $coupon->getPromotionId(),
    ]);
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
    /** @var \Drupal\commerce_promotion\Entity\CouponInterface $coupon */
    $coupon = $this->getEntity();
    $coupon->setEnabled(TRUE);
    $coupon->save();
    $this->messenger()->addStatus($this->t('Successfully enabled the coupon %label.', ['%label' => $coupon->label()]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
