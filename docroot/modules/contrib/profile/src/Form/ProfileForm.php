<?php

namespace Drupal\profile\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the profile form.
 */
class ProfileForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  protected function showRevisionUi() {
    /** @var \Drupal\profile\Entity\ProfileTypeInterface $profile_type */
    $profile_type = $this->entityTypeManager->getStorage('profile_type')->load($this->entity->bundle());
    return $profile_type->showRevisionUi();
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = $this->entity;
    $save = $profile->save();

    $profile_type_storage = $this->entityTypeManager->getStorage('profile_type');
    /** @var \Drupal\profile\Entity\ProfileTypeInterface $profile_type */
    $profile_type = $profile_type_storage->load($profile->bundle());
    if ($profile_type->allowsMultiple()) {
      $this->messenger()->addMessage($this->t('%label has been saved.', ['%label' => $profile->label()]));
    }
    else {
      $this->messenger()->addMessage($this->t('The profile has been saved.'));
    }

    if ($profile->getOwnerId()) {
      $form_state->setRedirect('entity.user.canonical', [
        'user' => $profile->getOwnerId(),
      ]);
    }
    else {
      $form_state->setRedirect('entity.profile.collection');
    }

    return $save;
  }

}
