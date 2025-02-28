<?php

namespace Drupal\private_message\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;

/**
 * Provides a form for deleting a private message.
 *
 * @internal
 */
class PrivateMessageDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage(): string {
    return $this->t('The message has been deleted.');
  }

  /**
   * {@inheritdoc}
   */
  protected function logDeletionMessage() {
    $this->logger('private_message')
      ->notice('@user deleted a private message.', [
        '@user' => $this->currentUser()->getAccountName(),
      ]);
  }

}
