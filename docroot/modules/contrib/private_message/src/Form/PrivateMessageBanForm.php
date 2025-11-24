<?php

declare(strict_types=1);

namespace Drupal\private_message\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Private Message Ban edit forms.
 */
class PrivateMessageBanForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Private Message Ban.', [
          '%label' => $this->entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Private Message Ban.', [
          '%label' => $this->entity->label(),
        ]));
    }

    $form_state->setRedirect('entity.private_message_ban.collection');
    return $status;
  }

}
