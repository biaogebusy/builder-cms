<?php

namespace Drupal\layout_builder_st;

use Drupal\layout_builder\SectionStorageInterface;

/**
 * Trait for methods that will be added to core's LayoutEntityHelperTrait.
 */
trait TranslationsHelperTrait {

  /**
   * Determines if the sections is for a translation.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return bool
   *   TRUE if the section storage is for translation otherwise false.
   */
  protected static function isTranslation(SectionStorageInterface $section_storage) {
    return $section_storage instanceof TranslatableSectionStorageInterface && !$section_storage->isDefaultTranslation();
  }

}
