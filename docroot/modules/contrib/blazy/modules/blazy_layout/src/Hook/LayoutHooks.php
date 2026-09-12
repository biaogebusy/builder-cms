<?php

namespace Drupal\blazy_layout\Hook;

/**
 * Hook implementations for theme.
 *
 * @todo add #[Hook] attribute, and change them to instance class for D11.
 */
class LayoutHooks {

  /**
   * Implements hook_layout_alter().
   *
   * {@inheritdoc}
   */
  public static function layoutAlter(array &$definitions) {
    if ($layout = $definitions['blazy_layout'] ?? NULL) {
      if ($regions = $layout->getRegions()) {
        $count = count($regions);
        if ($count < 10) {
          $max = (int) blazy()->config('max_region_count');
          if ($max < 10) {
            $max = 20;
          }
          foreach (range(9, $max) as $delta) {
            $regions['blzyr_' . $delta] = [
              'label' => t('Region @delta', ['@delta' => $delta]),
            ];
          }
        }

        // Append the BG region into the defined regions.
        $regions['bg'] = [
          'label' => t('Background'),
        ];
        $layout->setRegions($regions);
      }
    }
  }

}
