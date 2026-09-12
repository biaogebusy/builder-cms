<?php

namespace Drupal\eck\Form\EntityType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eck\EckEntityTypeInterface;

/**
 * Provides the add form for ECK Entity Type.
 *
 * @ingroup eck
 */
class EckEntityTypeAddForm extends EckEntityTypeFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['id']['#maxlength'] = EckEntityTypeInterface::ECK_ENTITY_ID_MAX_LENGTH;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    // Change the submit button value.
    $actions['submit']['#value'] = $this->t('Create entity type');

    return $actions;
  }

}
