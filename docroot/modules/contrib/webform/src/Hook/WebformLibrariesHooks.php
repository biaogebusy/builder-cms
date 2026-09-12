<?php

namespace Drupal\webform\Hook;

use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Utility\WebformDialogHelper;

/**
 * Hook implementations for webform.
 */
class WebformLibrariesHooks {

  /**
   * Implements hook_library_info_build().
   */
  #[Hook('library_info_build')]
  public function libraryInfoBuild() {
    $base_path = base_path();
    $default_query_string = \Drupal::service('asset.query_string')->get();

    // Note:
    // Setting the 'type' to 'external' and manually build the CSS/JS file path
    // to prevent JS from being parsed by locale_js_alter()
    // @see locale_js_alter()
    // @see https://www.drupal.org/node/1803330
    $library_settings = ['type' => 'external', 'preprocess' => FALSE, 'minified' => FALSE];

    $libraries = [];

    // Set global webform CSS/JSS libraries when defined.
    $config = \Drupal::config('webform.settings');
    if ($config->get('assets.css')) {
      $libraries["webform.css"] = [
        'css' => [
          'theme' => [
            "{$base_path}webform/assets/global.css?{$default_query_string}" => $library_settings,
          ],
        ],
      ];
    }
    if ($config->get('assets.javascript')) {
      $libraries["webform.javascript"] = [
        'js' => [
          "{$base_path}webform/assets/global.js?{$default_query_string}" => $library_settings,
        ],
      ];
    }

    // Set webform specific CSS/JSS libraries when defined.
    $webform_libraries = \Drupal::state()->get('webform_libraries', []);
    $webforms = Webform::loadMultiple($webform_libraries);
    foreach ($webforms as $webform_id => $webform) {
      $assets = $webform->getAssets();
      if (!empty($assets['css'])) {
        $libraries["webform.css.{$webform_id}"] = [
          'css' => [
            'theme' => [
              "{$base_path}webform/css/{$webform_id}/custom.css?{$default_query_string}" => $library_settings,
            ],
          ],
        ];
      }
      if (!empty($assets['javascript'])) {
        $libraries["webform.javascript.{$webform_id}"] = [
          'js' => [
            "{$base_path}webform/javascript/{$webform_id}/custom.js?{$default_query_string}" => $library_settings,
          ],
        ];
      }
    }

    return $libraries;
  }

  /**
   * Implements hook_library_info_alter().
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(&$libraries, $extension) {
    // Only alter modules that declare webform libraries.
    // @see hook_webform_libraries_info()
    if ($extension !== 'webform' && !\Drupal::moduleHandler()->hasImplementations('webform_libraries_info', $extension)) {
      return;
    }
    /** @var \Drupal\webform\WebformLibrariesManagerInterface $libraries_manager */
    $libraries_manager = \Drupal::service('webform.libraries_manager');
    // If old version of the progress tracker (< 2.0.7) is installed
    // use the progress-tracker.css in /docs instead of /src.
    if (isset($libraries['libraries.progress-tracker']) && $libraries_manager->exists('progress-tracker') && !file_exists($libraries_manager->find('progress-tracker') . '/src/styles/progress-tracker.css')) {
      $libraries['libraries.progress-tracker']['css']['component'] = ['/libraries/progress-tracker/docs/styles/progress-tracker.css' => []];
    }
    // If chosen_lib.module is installed, then update the dependency.
    if (\Drupal::moduleHandler()->moduleExists('chosen_lib')) {
      if (isset($libraries['webform.element.chosen'])) {
        $dependencies =& $libraries['webform.element.chosen']['dependencies'];
        foreach ($dependencies as $index => $dependency) {
          if ($dependency === 'webform/libraries.jquery.chosen') {
            $dependencies[$index] = 'chosen_lib/chosen';
            $dependencies[] = 'chosen_lib/chosen.css';
            break;
          }
        }
      }
    }
    // If select2.module is installed, then update the dependency.
    if (\Drupal::moduleHandler()->moduleExists('select2')) {
      if (isset($libraries['webform.element.select2'])) {
        $dependencies =& $libraries['webform.element.select2']['dependencies'];
        foreach ($dependencies as $index => $dependency) {
          if ($dependency === 'webform/libraries.jquery.select2') {
            $dependencies[$index] = 'select2/select2';
            break;
          }
        }
      }
    }
    // Map /library/* paths to CDN.
    // @see webform.libraries.yml.
    foreach ($libraries as $library_name => &$library) {
      // Remove excluded libraries.
      if ($libraries_manager->isExcluded($library_name)) {
        unset($libraries[$library_name]);
        continue;
      }
      // Skip libraries installed by other modules.
      if (isset($library['module'])) {
        continue;
      }
      if (!empty($library['dependencies'])) {
        // Remove excluded libraries from dependencies.
        foreach ($library['dependencies'] as $dependency_index => $dependency_name) {
          if ($libraries_manager->isExcluded($dependency_name)) {
            $library['dependencies'][$dependency_index] = NULL;
            $library['dependencies'] = array_filter($library['dependencies']);
          }
        }
      }
      // Handle CDN support.
      if (isset($library['cdn']) && isset($library['directory']) && !$libraries_manager->exists($library['directory'])) {
        _webform_library_info_alter_recursive($library, $library['cdn']);
      }
    }
  }

  /**
   * Implements hook_css_alter().
   */
  #[Hook('css_alter')]
  public function cssAlter(&$css, AttachedAssetsInterface $assets) {
    // Remove the off-canvas CSS reset for webform admin routes.
    // @see https://www.drupal.org/project/drupal/issues/2826722
    if (WebformDialogHelper::useOffCanvas()) {
      $has_webform_css = FALSE;
      foreach (array_keys($css) as $key) {
        if (str_contains($key, '/webform.admin.css')) {
          $has_webform_css = TRUE;
          break;
        }
      }
      if ($has_webform_css) {
        foreach ($css as $key => $item) {
          if (str_contains($key, '/off-canvas')) {
            unset($css[$key]);
          }
        }
      }
    }
    _webform_asset_alter($css, 'css');
  }

  /**
   * Implements hook_js_alter().
   */
  #[Hook('js_alter')]
  public function jsAlter(&$javascript, AttachedAssetsInterface $assets) {
    // Ensure codemirror.js always loads first with weight = -1.
    // This prevents "Uncaught ReferenceError: CodeMirror is not defined" errors.
    // If the CKEditor Codemirror module is installed, it will overwrite
    // the Webform module's codemirror.js weight.
    // @see ckeditor_codemirror/ckeditor_codemirror.libraries.yml
    // @see webform/webform.libraries.yml
    if (isset($javascript['libraries/codemirror/lib/codemirror.js'])) {
      $javascript['libraries/codemirror/lib/codemirror.js']['weight'] = -1;
    }
    _webform_asset_alter($javascript, 'javascript');
  }

}
