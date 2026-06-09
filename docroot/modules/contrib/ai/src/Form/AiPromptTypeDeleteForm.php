<?php

namespace Drupal\ai\Form;

use Drupal\ai\Entity\AiPromptTypeInterface;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the form to delete an AI Prompt.
 */
class AiPromptTypeDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.ai_prompt_type.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (
      $this->entity instanceof AiPromptTypeInterface
      && $count = $this->entity->getPromptCount()
    ) {
      $form_state->setErrorByName('submit', $this->t('Unable to delete the AI Prompt type %label since it has %count AI Prompts still. Please delete the prompts first.', [
        '%label' => $this->entity->label(),
        '%count' => $count,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    $this->messenger()->addMessage($this->t('The AI Prompt type %label has been deleted.', [
      '%label' => $this->entity->label(),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
