<?php

namespace Drupal\entity_module_test\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity\Form\EntityDuplicateFormTrait;

class EnhancedEntityForm extends ContentEntityForm {

  use EntityDuplicateFormTrait;

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->postSave($this->entity, $this->operation);

    $this->messenger()->addMessage($this->t('Saved the %label enhanced entity.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.entity_test_enhanced.collection');
  }

}
