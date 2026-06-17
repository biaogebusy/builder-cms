<?php

namespace Drupal\layout_builder_st\Plugin\SectionStorage;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage as CoreOverridesSectionStorage;
use Drupal\layout_builder_st\TranslatableSectionStorageInterface;

/**
 * {@inheritdoc}
 */
final class OverridesSectionStorage extends CoreOverridesSectionStorage implements TranslatableSectionStorageInterface {

  /**
   * The field name for translated configuration used by this storage.
   *
   * @var string
   */
  const TRANSLATED_CONFIGURATION_FIELD_NAME = 'layout_builder__translation';

  /**
   * {@inheritdoc}
   */
  protected function handleTranslationAccess(AccessResult $result, $operation, AccountInterface $account) {
    $entity = $this->getEntity();

    // Check if the entity is not null and allows overrides before accessing its
    // properties.
    if (!$entity || !$entity->hasField(static::FIELD_NAME)) {
      // Return a neutral result if the entity is null.
      return AccessResult::neutral();
    }

    $field_config = $entity->getFieldDefinition(static::FIELD_NAME)->getConfig($entity->bundle());

    // Access is allowed if one of the following conditions is true:
    // 1. This is the default translation.
    // 2. The entity is translatable and the layout is overridden and the layout
    //    field is not translatable.
    return $result->andIf(AccessResult::allowedIf($this->isDefaultTranslation() || ($entity instanceof TranslatableInterface && $this->isOverridden() && !$field_config->isTranslatable())))
      ->addCacheableDependency($entity)
      ->addCacheableDependency($field_config);
  }

  /**
   * Indicates if the layout is translatable.
   *
   * @return bool
   *   TRUE if the layout is translatable, otherwise FALSE.
   */
  protected function isTranslatable() {
    $entity = $this->getEntity();
    if ($entity instanceof TranslatableInterface) {
      return $entity->isTranslatable();
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isDefaultTranslation() {
    if ($this->isTranslatable()) {
      /** @var \Drupal\Core\Entity\TranslatableInterface $entity */
      $entity = $this->getEntity();
      return $entity->isDefaultTranslation();
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslatedComponentConfiguration($uuid, array $configuration) {
    if (!$this->getEntity()->get(OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME)->isEmpty()) {
      $translation_settings = $this->getEntity()->get(OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME)->getValue()[0];
    }
    $translation_settings['value']['components'][$uuid] = $configuration;
    $this->getEntity()->set(OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME, [$translation_settings]);
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatedComponentConfiguration($uuid) {
    if ($this->getEntity()->get(OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME)->isEmpty()) {
      return [];
    }
    $translation_settings = $this->getEntity()->get(OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME)->getValue()[0];
    return $translation_settings['value']['components'][$uuid] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatedConfiguration() {
    if ($this->getEntity()->get(OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME)->isEmpty()) {
      return [];
    }
    return $this->getEntity()->get(OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME)->getValue()[0];
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationLanguage() {
    if (!$this->isDefaultTranslation()) {
      return $this->getEntity()->language();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLanguage() {
    if (!$this->isDefaultTranslation()) {
      /** @var \Drupal\Core\Entity\TranslatableInterface $entity */
      $entity = $this->getEntity();
      return $entity->getUntranslated()->language();
    }
    return NULL;
  }

}
