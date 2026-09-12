<?php

declare(strict_types=1);

namespace Drupal\content_lock_test\Hook;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Form alter hooks for the content_lock_test module.
 */
class FormAlter {

  public function __construct(
    private ContentLockInterface $contentLock,
  ) {
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id): void {
    if (!$form_state->getFormObject() instanceof EntityFormInterface) {
      return;
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();

    // Check if we must lock this entity.
    $form_op = $form_state->getFormObject()->getOperation();
    if (!$this->contentLock->isLockable($entity, $form_op)) {
      return;
    }

    // Ensure that moderation state is still disabled if the content is locked
    // and some custom code has marked it as not disabled.
    // @see \Drupal\content_lock\Hook\FormAlter::formAlter()
    if (isset($form['moderation_state'])) {
      $form['moderation_state']['#disabled'] = FALSE;
    }
  }

}
