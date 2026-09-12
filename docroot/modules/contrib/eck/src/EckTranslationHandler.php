<?php

namespace Drupal\eck;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_translation\ContentTranslationHandler;
use Drupal\eck\Entity\EckEntityBundle;

/**
 * Defines the translation handler for eck entities.
 */
class EckTranslationHandler extends ContentTranslationHandler {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity) {
    parent::entityFormAlter($form, $form_state, $entity);

    assert($entity instanceof EckEntityInterface);
    $entityType = $entity->getEntityType();

    if (isset($form['content_translation'])) {
      // We might not need to show these values on ECK forms: they inherit the
      // base field values.
      if ($entity->hasField($entityType->getKey('published'))) {
        $form['content_translation']['status']['#access'] = FALSE;
      }

      if ($entity->hasField('created')) {
        $form['content_translation']['created']['#access'] = FALSE;
      }
    }

    // Change the submit button labels if there was a status field they affect
    // in which case their publishing / unpublishing may or may not apply
    // to all translations.
    $formObject = $form_state->getFormObject();
    $formLangcode = $formObject->getFormLangcode($form_state);
    $translations = $entity->getTranslationLanguages();

    if (!$entity->isNew() && (!isset($translations[$formLangcode]) || count($translations) > 1) && $entity->hasField('status') && isset($form['actions']['submit'])) {
      $status_translatable = $entity->getFieldDefinition('status')->isTranslatable();
      $form['actions']['submit']['#value'] .= ' ' . ($status_translatable ? t('(this translation)') : t('(all translations)'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function entityFormTitle(EntityInterface $entity) {
    assert($entity instanceof EckEntityInterface);

    $typeLabel = NULL;
    $type = EckEntityBundle::load($entity->bundle());

    if ($type !== NULL) {
      $typeLabel = $type->label();
    }

    return $this->t('<em>Edit @type</em> @title', [
      '@type' => $typeLabel,
      '@title' => $entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function entityFormEntityBuild($entity_type, EntityInterface $entity, array $form, FormStateInterface $form_state) {
    assert($entity instanceof EckEntityInterface);
    $entityType = $entity->getEntityType();

    if ($form_state->hasValue('content_translation')) {
      $translation = &$form_state->getValue('content_translation');

      if ($entity->hasField($entityType->getKey('published'))) {
        $translation['status'] = $entity->isPublished();
      }

      if ($entity->hasField($entityType->getKey('uid'))) {
        $account = $entity->uid->entity;
        $translation['uid'] = $account ? $account->id() : 0;
      }

      if ($entity->hasField('created')) {
        $timestamp = $entity->get('created')->value;
        $translation['created'] = $this->dateFormatter->format($timestamp, 'custom', 'Y-m-d H:i:s O');
      }
    }

    parent::entityFormEntityBuild($entity_type, $entity, $form, $form_state);
  }

}
