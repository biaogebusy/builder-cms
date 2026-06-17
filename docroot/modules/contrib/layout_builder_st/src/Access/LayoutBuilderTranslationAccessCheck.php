<?php

namespace Drupal\layout_builder_st\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_st\TranslationsHelperTrait;
use Symfony\Component\Routing\Route;

/**
 * Provides an access check for the Layout Builder translations.
 *
 * @ingroup layout_builder_access
 *
 * @internal
 *   Tagged services are internal.
 */
final class LayoutBuilderTranslationAccessCheck implements AccessInterface {

  use LayoutEntityHelperTrait;
  use TranslationsHelperTrait;

  /**
   * Checks routing access to the default translation only layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(SectionStorageInterface $section_storage, Route $route) {
    $translation_type = $route->getRequirement('_layout_builder_translation_access');
    $is_translation = static::isTranslation($section_storage);
    switch ($translation_type) {
      case 'untranslated':
        $access = AccessResult::allowedIf(!$is_translation);
        break;

      case 'translated':
        $access = AccessResult::allowedIf($is_translation);
        break;

      default:
        throw new \UnexpectedValueException("Unexpected _layout_builder_translation_access route requirement: $translation_type");
    }
    return $access;
  }

}
