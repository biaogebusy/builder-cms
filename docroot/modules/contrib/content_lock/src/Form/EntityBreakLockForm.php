<?php

namespace Drupal\content_lock\Form;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for break content lock forms.
 */
class EntityBreakLockForm extends FormBase {

  public function __construct(
    protected ContentLockInterface $lockService,
    protected LanguageManagerInterface $languageManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('content_lock'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity_type = $form_state->getValue('entity_type_id');
    $entity_id = $form_state->getValue('entity_id');
    $langcode = $form_state->getValue('langcode');
    $form_op = $form_state->getValue('form_op') ?: NULL;

    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if ($entity instanceof TranslatableInterface) {
      $entity = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity;
    }

    $this->lockService->release($entity, $form_op);
    if ($form_state->get('translation_lock')) {
      $this->messenger()->addStatus($this->t('Unlocked. Anyone can now edit this content translation.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Unlocked. Anyone can now edit this content.'));
    }

    // Gets the link templates using the URI template syntax.
    $link_templates = $entity->getEntityType()->getLinkTemplates();

    // Redirect URL to the request destination or the canonical entity view.
    if ($destination = $this->getRequest()->query->get('destination')) {
      $url = Url::fromUserInput($destination);
      $form_state->setRedirectUrl($url);
    }
    elseif (isset($link_templates['canonical'])) {
      $language = $this->languageManager->getLanguage($form_state->get('langcode_entity'));
      $url = Url::fromRoute("entity.$entity_type.canonical", [$entity_type => $entity_id], ['language' => $language]);
      $form_state->setRedirectUrl($url);
    }
    else {
      $form_state->setRedirectUrl(Url::fromRoute('<front>'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'break_lock_entity';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ContentEntityInterface $entity = NULL, ?string $langcode = NULL, ?string $form_op = NULL): array {
    // Save langcode of lock, before checking if translation lock is enabled.
    // This is needed to generate the correct entity URL for the given language.
    $form_state->set('langcode_entity', $langcode);

    $translation_lock = $this->lockService->isTranslationLockEnabled($entity->getEntityTypeId());
    if (!$translation_lock) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    $form_state->set('translation_lock', $translation_lock);

    $form_op_lock = $this->lockService->isFormOperationLockEnabled($entity->getEntityTypeId());
    if (!$form_op_lock) {
      $form_op = '*';
    }

    $form['#title'] = $this->t('Break Lock for content @label', ['@label' => $entity->label()]);
    $form['entity_id'] = [
      '#type' => 'value',
      '#value' => $entity->id(),
    ];
    $form['entity_type_id'] = [
      '#type' => 'value',
      '#value' => $entity->getEntityTypeId(),
    ];
    $form['langcode'] = [
      '#type' => 'value',
      '#value' => $langcode,
    ];
    $form['form_op'] = [
      '#type' => 'value',
      '#value' => $form_op,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm break lock'),
    ];
    return $form;
  }

  /**
   * Custom access checker for the form route requirements.
   */
  public function access(ContentEntityInterface $entity, $langcode, $form_op, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->hasPermission('break content lock') || $this->lockService->isLockedBy($entity, $form_op, $account->id()));
  }

}
