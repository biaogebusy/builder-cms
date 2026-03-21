<?php

namespace Drupal\consumers\Entity\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form controller for the Consumer make default form.
 *
 * @ingroup consumers
 */
class MakeDefaultForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to make %consumer the default @label?', [
      '%consumer' => $this->entity->label(),
      '@label' => $this->entity->getEntityType()->getSingularLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('The @label currently marked as default will lose this property, since there can only be one default @label. This may break current assumptions in existing client-side applications.', [
      '@label' => $this->entity->getEntityType()->getSingularLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.consumer.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Make Default');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Make the submitted entity the new default.
    /** @var \Drupal\consumers\Entity\ConsumerInterface $entity */
    $entity = $this->getEntity();
    if ($entity->get('is_default')->value) {
      // This is already the default. Do nothing.
      return;
    }
    $entity->set('is_default', TRUE);
    $entity->save();
  }

}
