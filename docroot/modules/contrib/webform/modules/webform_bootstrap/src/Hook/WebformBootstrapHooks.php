<?php

namespace Drupal\webform_bootstrap\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_bootstrap.
 */
class WebformBootstrapHooks {
    // phpcs:disable Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments) {
    if (!_webform_bootstrap_is_active_theme()) {
      return;
    }
    $attachments['#attached']['library'][] = 'webform_bootstrap/webform_bootstrap';
  }

  /**
   * Implements hook_webform_element_alter().
   */
  #[Hook('webform_element_alter')]
  public function webformElementAlter(array &$element, \Drupal\Core\Form\FormStateInterface $form_state, array $context) {
    if (!_webform_bootstrap_is_active_theme()) {
      return;
    }
    // Convert #description are being changed smart descriptions which
    // contain render arrays to rendered markup.
    // @see \Drupal\bootstrap\Utility\Element::smartDescription
    static $smart_description_enabled;
    if (!isset($smart_description_enabled)) {
      $theme = \Drupal\bootstrap\Bootstrap::getTheme();
      $smart_description_enabled = $theme->getSetting('tooltip_enabled') && $theme->getSetting('forms_smart_descriptions');
    }
    // We need to set $element['#smart_description'] to false if the element's
    // description_display is set to 'before' or 'after' otherwise Bootstrap will
    // display as a tooltip regardless of the setting.
    if ($smart_description_enabled && isset($element['#description']) && isset($element['#description_display']) && empty($element['#smart_description']) && ($element['#description_display'] === 'after' || $element['#description_display'] === 'before')) {
      $element['#smart_description'] = FALSE;
    }
    if ($smart_description_enabled && isset($element['#description']) && is_array($element['#description']) && (empty($element['#smart_description']) || $element['#smart_description'] === TRUE)) {
      $element['#description'] = \Drupal::service('renderer')->renderInIsolation($element['#description']);
    }
    // Enable Bootstrap .input-group wrapper for #field_prefix.
    // and/or #field_suffix support.
    // @see \Drupal\bootstrap\Plugin\ProcessManager::process
    if (isset($element['#field_prefix']) || isset($element['#field_suffix'])) {
      $element['#input_group'] = TRUE;
    }
    // Tweak element types.
    if (isset($element['#type'])) {
      $element_info = \Drupal::service('element_info')->getInfo($element['#type']);
      if (isset($element_info['#pre_render'])) {
        foreach ($element_info['#pre_render'] as $pre_render) {
          if (is_array($pre_render) && in_array($pre_render[1], ['preRenderCompositeFormElement', 'preRenderWebformCompositeFormElement'])) {
            // Prevent elements that extend radios and checkboxes from being wrapped
            // in a fieldset.
            // @see \Drupal\bootstrap\Plugin\Alter\ElementInfo::alter
            $element['#bootstrap_panel'] = FALSE;
            break;
          }
        }
      }
    }
  }

  /**
   * Implements hook_link_alter().
   */
  #[Hook('link_alter')]
  public function linkAlter(&$variables) {
    if (!_webform_bootstrap_is_active_theme()) {
      return;
    }
    // Convert .button classes to .btn CSS classes.
    if (isset($variables['options']['attributes']['class']) && is_array($variables['options']['attributes']['class'])) {
      _webform_bootstrap_convert_button_classes($variables['options']['attributes']['class']);
    }
  }

}
