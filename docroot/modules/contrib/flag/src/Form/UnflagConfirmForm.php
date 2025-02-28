<?php

namespace Drupal\flag\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\flag\Plugin\ActionLink\FormEntryInterface;

/**
 * Provides the confirm form page for unflagging an entity.
 *
 * @see \Drupal\flag\Plugin\ActionLink\ConfirmForm
 */
class UnflagConfirmForm extends FlagConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'unflag_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->flag->getLinkTypePlugin()->getUnflagQuestion();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->flag->getLongText('unflag');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    $link_plugin = $this->flag->getLinkTypePlugin();
    return $link_plugin instanceof FormEntryInterface ? $link_plugin->getDeleteButtonText() : $this->t('Delete flagging');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::service('flag')->unflag($this->flag, $this->entity);
  }

}
