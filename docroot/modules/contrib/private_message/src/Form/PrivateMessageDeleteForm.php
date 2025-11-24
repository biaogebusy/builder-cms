<?php

declare(strict_types=1);

namespace Drupal\private_message\Form;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityDeleteForm;

/**
 * Provides a form for deleting a private message.
 */
class PrivateMessageDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage(): MarkupInterface|string {
    return $this->t('The message has been deleted.');
  }

  /**
   * {@inheritdoc}
   */
  protected function logDeletionMessage(): void {
    $this->logger('private_message')
      ->notice('@user deleted a private message.', [
        '@user' => $this->currentUser()->getDisplayName(),
      ]);
  }

}
