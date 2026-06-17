<?php

namespace Drupal\layout_builder_st;

use Drupal\layout_builder\SectionStorageInterface;

/**
 * Defines an interface for translatable section overrides.
 */
interface TranslatableSectionStorageInterface extends SectionStorageInterface {

  /**
   * Indicates if the layout is default translation layout.
   *
   * @return bool
   *   TRUE if the layout is the default translation layout, otherwise FALSE.
   */
  public function isDefaultTranslation();

  /**
   * Sets the translated component configuration.
   *
   * @param string $uuid
   *   The component UUID.
   * @param array $configuration
   *   The component's translated configuration.
   */
  public function setTranslatedComponentConfiguration($uuid, array $configuration);

  /**
   * Gets the translated component configuration.
   *
   * @param string $uuid
   *   The component UUID.
   *
   * @return array
   *   The component's translated configuration.
   */
  public function getTranslatedComponentConfiguration($uuid);

  /**
   * Gets the translated configuration for the layout.
   *
   * @return array
   *   The translated configuration for the layout.
   */
  public function getTranslatedConfiguration();

  /**
   * Gets the language of the translation if any.
   *
   * @return \Drupal\Core\Language\LanguageInterface|null
   *   The translation language if the current layout is for a translation
   *   otherwise NULL.
   */
  public function getTranslationLanguage();

  /**
   * Gets the source language of the translation if any.
   *
   * @return \Drupal\Core\Language\LanguageInterface|null
   *   The translation source language if the current layout is for a
   *   translation otherwise NULL.
   */
  public function getSourceLanguage();

}
