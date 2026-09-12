<?php

namespace Drupal\blazy\Internals;

use Drupal\blazy\Theme\Grid;
use Drupal\blazy\Theme\Lightbox;
use Drupal\blazy\Theme\BlazyViews;

/**
 * Provides feature check methods at container level, or globally.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please use the public method instead.
 *
 * @todo refine, and split them conditionally based on fields like libraries.
 * @todo deprecate and remove most $settings once migrated and after sub-modules
 * and tests.
 */
final class Check {

  /**
   * Checks for Blazy formatter such as from within a Views style plugin.
   *
   * @see \Drupal\blazy\BlazyManager::preserve()
   * @see \Drupal\blazy\BlazyManager::isBlazy()
   */
  public static function blazyOrNot(array &$settings, array $data = []): void {
    // Retrieves Blazy formatter related settings from within Views style.
    /** @var \Drupal\blazy\BlazySettings $blazies */
    $blazies = Internals::verify($settings);
    $data    = $data ?: $blazies->get('first.data');

    if (empty($data) || !is_array($data)) {
      return;
    }

    // 1. Blazy formatter within Views styles by supported modules.
    // $item_id might be slide, box, etc.
    $subsets = Internals::toHashtag($data);
    $item_id = $blazies->get('item.id');
    $content = $data[$item_id] ?? $data;

    // 2. Blazy Views fields by supported modules.
    // Prevents edge case with unexpected flattened Views results which is
    // normally triggered by checking "Use field template" option.
    // Flattenings were seen at D7, but no longer seen at D9, however...
    if (is_array($content) && ($view = ($content['#view'] ?? NULL))) {
      if ($blazy_field = BlazyViews::viewsField($view)) {
        $subsets = $blazy_field->mergedViewsSettings();
        $settings = array_merge(array_filter($subsets), array_filter($settings));
      }
    }

    // 3. Core image formatter.
    if (!$subsets && $image_style = $data['#image_style'] ?? NULL) {
      $subsets['image_style'] = $settings['image_style'] = $image_style;
    }

    // 4. Makes this container aware of Blazy formatter it might contain.
    if ($subsets) {
      Internals::preserve($settings, $subsets);

      // Rechecks container, etc. since we have $subsets.
      $blazies->set('was.initialized', FALSE);

      // @todo refactor to instance class at D11.
      if ($manager = Internals::blazy()) {
        $manager->preSettings($settings);
      }
    }

    // 4. No longer needed once extracted above, remove.
    $blazies->unset('first.data')
      ->set('was.blazy', TRUE);
  }

  /**
   * Checks for settings alter.
   */
  public static function settingsAlter(array &$settings, $entity = NULL): void {
    $blazies = Internals::getBlazies($settings);

    /** @var \Drupal\blazy\BlazyManagerInterface $manager */
    $manager = Internals::blazy();

    // Bail out early if not so configured.
    if (!$blazies->is('lightbox') || !$manager) {
      return;
    }

    // Gallery is determined by a view, or overriden by colorbox settings.
    // Might be set by formatters or filters, but not View styles/ fields.
    $gallery_id = $blazies->get('view.instance_id');
    $gallery_id = $blazies->get('lightbox.gallery_id') ?: $gallery_id;
    $is_gallery = !empty($gallery_id);

    // Respects colorbox settings unless for an explicit field/ view gallery.
    if (!$is_gallery
      && $blazies->get('colorbox')
      && function_exists('colorbox_theme')) {
      $is_gallery = (bool) $manager->config('custom.slideshow.slideshow', 'colorbox.settings');
    }

    // Re-define based on potential hook_alter().
    if ($is_gallery) {
      $gallery_id = str_replace('_', '-', $gallery_id);
      $blazies->set('lightbox.gallery_id', $gallery_id)
        ->set('is.gallery', TRUE);
    }

    // Only needed for lightbox captions with entity label and tokens.
    if ($entity) {
      $blazies->set('entity.instance', $entity);
    }
  }

  /**
   * Alias for Container::check().
   */
  public static function container(array &$settings): void {
    Container::check($settings);
  }

  /**
   * Alias for Container::checkUi().
   */
  public static function uiContainer(array &$settings): void {
    Container::checkUi($settings);
  }

  /**
   * Alias for Field::check().
   */
  public static function fields(array &$settings, $items): void {
    Field::check($settings, $items);
  }

  /**
   * Alias for Grid::check().
   */
  public static function grids(array &$settings): void {
    Grid::check($settings);
  }

  /**
   * Alias for Lightbox::check().
   */
  public static function lightboxes(array &$settings): void {
    Lightbox::check($settings);
  }

}
