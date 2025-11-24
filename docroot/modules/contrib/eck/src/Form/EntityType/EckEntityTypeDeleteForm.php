<?php

namespace Drupal\eck\Form\EntityType;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a confirm form for deleting the entity.
 *
 * @ingroup eck
 */
class EckEntityTypeDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete entity type %label?', [
      '%label' => $this->entity->label(),
    ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete entity type');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('eck.entity_type.list');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $content_number = $this->entityTypeManager
      ->getStorage($this->entity->id())
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    if (!empty($content_number)) {
      $warning_message = '<p>' . $this->formatPlural($content_number, 'There is 1 %type entity. You can not remove this entity type until you have removed all of the %type entities.', 'There are @count %type entities. You may not remove %type until you have removed all of the %type entities.', ['%type' => $this->entity->label()]) . '</p>';

      $form['#title'] = $this->getConfirmText();
      $form['description'] = ['#markup' => $warning_message];
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Redirect to list when completed.
    $form_state->setRedirectUrl(new Url('eck.entity_type.list'));
  }

}
