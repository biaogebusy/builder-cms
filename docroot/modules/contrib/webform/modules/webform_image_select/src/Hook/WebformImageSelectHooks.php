<?php

namespace Drupal\webform_image_select\Hook;

use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_image_select.
 */
class WebformImageSelectHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_webform_help_info().
   */
  #[Hook('webform_help_info')]
  public function webformHelpInfo() {
    $help = [];
    $help['config_image_select_images'] = [
      'group' => 'configuration',
      'title' => $this->t('Configuration: Images'),
      'content' => $this->t('The <strong>Images configuration</strong> page lists reusable images for the image select element.'),
      'routes' => [
              // @see /admin/structure/webform/options/images
        'entity.webform_image_select_images.collection',
      ],
    ];
    return $help;
  }

  /**
   * Implements hook_webform_libraries_info().
   */
  #[Hook('webform_libraries_info')]
  public function webformLibrariesInfo() {
    $libraries = [];
    $libraries['jquery.image-picker'] = [
      'title' => $this->t('jQuery: Image Picker'),
      'description' => $this->t('A simple jQuery plugin that transforms a select element into a more user friendly graphical interface.'),
      'notes' => $this->t('Image Picker is used by the Image select element.'),
      'homepage_url' => Url::fromUri('https://rvera.github.io/image-picker/'),
      'download_url' => Url::fromUri('https://github.com/rvera/image-picker/archive/refs/tags/0.3.1.zip'),
      'version' => '0.3.1',
      'elements' => [
        'webform_image_select',
      ],
      'optional' => FALSE,
      'license' => 'MIT',
    ];
    return $libraries;
  }

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(&$data, $route_name) {
    // Change config entities 'Translate *' tab to be just label 'Translate'.
    if (isset($data['tabs'][0]["config_translation.local_tasks:entity.webform_image_select_images.config_translation_overview"]['#link']['title'])) {
      $data['tabs'][0]["config_translation.local_tasks:entity.webform_image_select_images.config_translation_overview"]['#link']['title'] = $this->t('Translate');
    }
  }

}
