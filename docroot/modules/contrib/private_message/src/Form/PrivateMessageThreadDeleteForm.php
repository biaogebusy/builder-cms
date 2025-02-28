<?php

namespace Drupal\private_message\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Form definition for the private message delete form.
 *
 * @method PrivateMessageThreadInterface getEntity()
 */
class PrivateMessageThreadDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $members = $this->getEntity()->getMembers();
    $member_names = [];
    foreach ($members as $member) {
      if ($member->id() == $this->currentUser()->id()) {
        continue;
      }
      $member_names[] = $member->getDisplayName();
    }
    return $this->t('Are you sure you want to delete the thread between you and the following users: @others?', ['@others' => implode(', ', $member_names)]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.private_message_thread.canonical', ['private_message_thread' => $this->getEntity()->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete thread');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->getEntity()->delete();

    $form_state->setRedirect('private_message.private_message_page');
  }

}
