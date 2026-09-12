<?php

namespace Drupal\blazy\Hook;

use Drupal\blazy\BlazyApi;
use Drupal\blazy\Internals\Internals;
use Drupal\blazy\Theme\Attributes;
use Drupal\blazy\Utility\Arrays;
use Drupal\blazy\Views\BlazyStylePluginInterface;

/**
 * Hook implementations for views.
 *
 * @todo add #[Hook] attribute, and change them to instance class for D11.
 */
class ViewsHooks {

  /**
   * Implements hook_views_data_alter().
   *
   * {@inheritdoc}
   */
  public static function viewsDataAlter(&$data): void {
    // @todo let's keep it for a while as this can be useful for EB.
    $data['file_managed']['blazy_file'] = [
      'title' => t('Blazy'),
      'help'  => t('Displays a preview of a File using Blazy, if applicable.'),
      'field' => [
        'id' => 'blazy_file',
        'click sortable' => FALSE,
      ],
    ];

    // @todo recheck to use core Media post VEF/ VEM removal.
    $data['media_field_data']['blazy_media'] = [
      'title' => 'Blazy',
      'help'  => t('Displays a preview of a Media using Blazy, if applicable.'),
      'field' => [
        'id' => 'blazy_media',
        'click sortable' => FALSE,
      ],
    ];
  }

  /**
   * Implements hook_views_plugins_style_alter().
   *
   * {@inheritdoc}
   */
  public static function viewsPluginsStyleAlter(array &$plugins): void {
    $plugins['blazy'] = [
      'id'             => 'blazy',
      'label'          => 'Blazy Grid',
      'description'    => t('Display the results in a Blazy grid.'),
      'class'          => 'Drupal\blazy\Plugin\views\style\BlazyViews',
      'display_types'  => ['normal'],
      'help'           => t('Works best with Views field containing Blazy.'),
      'parent'         => 'parent',
      'plugin_type'    => 'style',
      'register_theme' => FALSE,
      'short_title'    => 'Blazy',
      'title'          => 'Blazy Grid',
      'provider'       => 'blazy',
    ];
  }

  /**
   * Returns one of the Blazy Views fields, if available.
   */
  public static function viewsField($view) {
    foreach (['file', 'media'] as $entity) {
      if (isset($view->field['blazy_' . $entity])) {
        return $view->field['blazy_' . $entity];
      }
    }
    return NULL;
  }

  /**
   * Checks if Blazy is applicable in a view.
   */
  public static function isApplicable(array &$variables): array {
    $view      = $variables['view'];
    $blazy     = self::viewsField($view);
    $css_class = $variables['css_class'] ?? NULL;

    return [
      'css' => $css_class && strpos($css_class, 'blazy--') !== FALSE,
      'field' => $view->ajaxEnabled() || !empty($blazy),
    ];
  }

  /**
   * Implements hook_preprocess_views_view().
   *
   * {@inheritdoc}
   */
  public static function preprocessViewsView(array &$variables): void {
    $check = self::isApplicable($variables);
    $valid = FALSE;
    if ($check['css']) {
      $valid = self::withViewsView($variables);
    }

    if ($check['field']) {
      $valid = self::withViewsField($variables) ?: $valid;
    }

    if ($view = $variables['view'] ?? NULL) {
      if ($fields = $view->field) {
        foreach ($fields as $field) {
          $settings = $field->options['settings'] ?? [];
          if (isset($settings['media_switch'])
            || isset($settings['optionset'])
            || isset($settings['grid_small'])) {
            $valid = TRUE;
            break;
          }
        }
      }

      if ($style = $view->style_plugin) {
        if ($style instanceof BlazyStylePluginInterface) {
          $valid = TRUE;
        }
      }
    }

    // Add own CSS class to fix theme compat like Olivero Grid surprises.
    // Adding `view--blazy` under Advanced > Other > CSS class should also work.
    if ($valid) {
      $variables['attributes']['class'][] = 'view--blazy';
    }
  }

  /**
   * Implements hook_preprocess_views_view().
   */
  private static function withViewsView(array &$variables): bool {
    $manager = Internals::blazy();
    if (!$manager) {
      return FALSE;
    }

    $lightboxes = $manager->getLightboxes();

    preg_match('~blazy--(.*?)-gallery~', $variables['css_class'], $matches);
    $lightbox = $matches[1] ? str_replace('-', '_', $matches[1]) : FALSE;

    // Given blazy--photoswipe-gallery, adds the [data-photoswipe-gallery], etc.
    if ($lightbox && in_array($lightbox, $lightboxes)) {
      $view = $variables['view'];
      $data = [
        'namespace' => 'blazy',
        'media_switch' => $lightbox,
      ];

      /** @var array $settings */
      $settings = BlazyApi::init($data);

      $settings[$lightbox] = $lightbox;
      $blazies = Internals::getBlazies($settings);
      $count = count($view->result);
      $blazies->set('count', $count)
        ->set('total', $count)
        ->set('use.ajax', $view->ajaxEnabled());

      $manager->moduleHandler()->alter('blazy_is_view', $settings, $variables);

      Attributes::container($variables['attributes'], $settings);
      $variables['#blazy'] = $settings;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Implements hook_preprocess_views_view().
   */
  private static function withViewsField(array &$variables): bool {
    $manager = Internals::blazy();
    if (!$manager) {
      return FALSE;
    }

    $view  = $variables['view'];
    $loads = [];
    $ajax  = $view->ajaxEnabled();
    $valid = FALSE;

    // At least, less aggressive than sitewide hook_library_info_alter().
    // @todo deprecate and remove when VIS alike added `Drupal.detachBehaviors()` to their JS.
    if ($ajax) {
      $loads['library'][] = 'blazy/bio.ajax';
    }

    // Load Blazy library once, not per field, if any Blazy Views field found.
    if ($blazy = self::viewsField($view)) {
      $plugin_id = $view->getStyle()->getPluginId();
      $settings  = $blazy->mergedSettings ?: $blazy->mergedViewsSettings();
      $blazies   = Internals::getBlazies($settings);

      $blazies->set('unlazy', FALSE);

      $load  = $manager->attach($settings);
      $loads = $manager->merge($load, $loads);
      $grid  = $plugin_id == 'blazy';

      if ($options = $view->getStyle()->options) {
        $grid = empty($options['grid']) ? $grid : TRUE;
      }

      // Prevents dup [data-LIGHTBOX-gallery] if the Views style supports Grid.
      if (!$grid) {
        $manager->moduleHandler()->alter('blazy_is_view', $settings, $variables);
        Attributes::container($variables['attributes'], $settings);
      }

      $valid = TRUE;
    }

    if ($loads) {
      $variables['#attached'] = Arrays::merge($loads, $variables, '#attached');
    }
    return $valid;
  }

}
