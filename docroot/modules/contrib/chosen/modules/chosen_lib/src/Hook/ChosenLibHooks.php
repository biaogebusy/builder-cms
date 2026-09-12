<?php

namespace Drupal\chosen_lib\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for chosen_lib.
 */
class ChosenLibHooks {

  /**
   * Implements hook_library_info_alter().
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, $module) {
    if ($module !== 'chosen_lib') {
      return;
    }
    if (!isset($libraries['chosen'])) {
      return;
    }
    $chosen_path = _chosen_lib_get_chosen_path();
    if ($chosen_path) {
      $minified_js = file_exists($chosen_path . '/chosen.min.js');
      $chosen_js = $minified_js ? '/chosen.min.js' : '/chosen.js';
      $libraries['chosen']['js']['/' . $chosen_path . $chosen_js] = [
        'minified' => $minified_js,
      ];
      $minified_css = file_exists($chosen_path . '/chosen.min.css');
      $chosen_css = $minified_css ? '/chosen.min.css' : '/chosen.css';
      $libraries['chosen.css']['css']['component']['/' . $chosen_path . $chosen_css] = [
        'minified' => $minified_css,
      ];
      return;
    }
    $libraries['chosen']['js']['https://cdn.jsdelivr.net/npm/@noli42/chosen@3.1.4/chosen.min.js'] = [
      'type' => 'external',
      'minified' => TRUE,
    ];
    $libraries['chosen.css']['css']['component']['https://cdn.jsdelivr.net/npm/@noli42/chosen@3.1.4/chosen.min.css'] = [
      'type' => 'external',
      'minified' => TRUE,
    ];
  }

}
