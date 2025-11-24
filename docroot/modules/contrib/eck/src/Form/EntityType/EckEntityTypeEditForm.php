<?php

namespace Drupal\eck\Form\EntityType;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the edit form for ECK Entity Type.
 *
 * @ingroup eck
 */
class EckEntityTypeEditForm extends EckEntityTypeFormBase {

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    // Change the submit button value.
    $actions['submit']['#value'] = $this->t('Update @type', ['@type' => $this->entity->label()]);

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $fieldStorage */
    $fieldStorage = $this->entityTypeManager->getStorage($this->entity->id());

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $efm */
    $definitions = $this->entityFieldManager->getBaseFieldDefinitions($this->entity->id());

    foreach (['title', 'created', 'changed', 'uid', 'status'] as $field) {
      // Lock entity base field configuration in case when that field already
      // contain some data.
      if (isset($definitions[$field]) && $fieldStorage->countFieldData($definitions[$field], TRUE)) {
        $form['base_fields'][$field]['#disabled'] = TRUE;
      }
    }

    return $form;
  }

}
