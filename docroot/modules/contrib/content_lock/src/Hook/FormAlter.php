<?php

namespace Drupal\content_lock\Hook;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Generic form alter hook implementation for the Content Lock module.
 */
class FormAlter {
  use DependencySerializationTrait;
  use StringTranslationTrait;

  public function __construct(
    private ContentLockInterface $contentLock,
    private MessengerInterface $messenger,
    private ConfigFactoryInterface $configFactory,
    private AccountInterface $currentUser,
    private RequestStack $requestStack,
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

    // We act only on edit form, not for a creation of a new entity.
    if (!is_null($entity->id())) {
      foreach (['submit', 'publish'] as $key) {
        if (isset($form['actions'][$key])) {
          $form['actions'][$key]['#submit'][] = [$this, 'formSubmit'];
        }
      }

      // We lock the content if it is currently edited by another user.
      $messages = [];
      if (!$this->contentLock->locking($entity, $form_op, $this->currentUser->id(), FALSE, NULL, $messages)) {
        $form['#process'][] = 'Drupal\content_lock\Hook\FormAlter::disableForm';
        $form['#disabled'] = TRUE;
      }
      else {
        // ContentLock::locking() returns TRUE if the content is locked by the
        // current user. Add an unlock button only for this user.
        $form['actions']['unlock'] = $this->contentLock->unlockButton($entity, $form_op, $this->requestStack->getCurrentRequest()->query->get('destination'));
      }

      // Add the messages to the form.
      $form['content_lock_messages'] = [
        '#type' => 'content_lock_messages',
        '#message_list' => $messages,
      ];
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * @see \Drupal\content_moderation\Form\EntityModerationForm::buildForm()
   */
  #[Hook('form_content_moderation_entity_moderation_form_alter', order: Order::Last)]
  public function contentModerationEntityFormAlter(&$form, FormStateInterface $form_state): void {
    // If the form has no entity there will be no form and we cannot check if it
    // is locked.
    if (!$form_state->has('entity')) {
      return;
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->get('entity');

    // We assume the moderation form is similar to the entity form edit
    // operation.
    $form_op = 'edit';

    // Check if we must lock this entity.
    if (!$this->contentLock->isLockable($entity, $form_op)) {
      return;
    }

    // Prevent access to the form if the entity is locked by another user.
    if (($lock = $this->contentLock->fetchLock($entity, $form_op)) && $this->currentUser->id() != $lock->uid) {
      $form['#access'] = FALSE;
    }
  }

  /**
   * Process callback to ensure all the form elements are disabled.
   *
   * @param array $element
   *   The form element. Passed by reference.
   *
   * @see \Drupal\Core\Form\FormBuilder::doBuildForm()
   */
  public static function disableForm(array $element): array {
    foreach (Element::children($element) as $key) {
      $element[$key] = static::disableForm($element[$key]);
    }
    // If the element has #disabled set to FALSE, set it to TRUE.
    if (!($element['#disabled'] ?? TRUE)) {
      $element['#disabled'] = TRUE;
    }
    return $element;
  }

  /**
   * Submit handler for content_lock.
   */
  public function formSubmit($form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();

    // If the user submitting owns the lock, release it.
    $this->contentLock->release($entity, $form_state->getFormObject()->getOperation(), (int) $this->currentUser->id());

    // We need to redirect to the canonical page after saving it. If not, we
    // stay on the edit form and we re-lock the entity.
    if (!$form_state->getRedirect() || ($form_state->getRedirect() && $entity->hasLinkTemplate('edit-form') && $entity->toUrl('edit-form')->toString() == $form_state->getRedirect()->toString())) {
      $form_state->setRedirectUrl($entity->toUrl());
    }
  }

}
