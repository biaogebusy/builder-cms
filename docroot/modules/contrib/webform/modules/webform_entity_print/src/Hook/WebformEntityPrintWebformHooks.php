<?php

namespace Drupal\webform_entity_print\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_entity_print.
 */
class WebformEntityPrintWebformHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_webform_admin_third_party_settings_form_alter().
   */
  #[Hook('webform_admin_third_party_settings_form_alter')]
  public function webformAdminThirdPartySettingsFormAlter(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformThirdPartySettingsManagerInterface $third_party_settings_manager */
    $third_party_settings_manager = \Drupal::service('webform.third_party_settings_manager');
    $template_settings = $third_party_settings_manager->getThirdPartySetting('webform_entity_print', 'template') ?: [];
    $export_type_settings = $third_party_settings_manager->getThirdPartySetting('webform_entity_print', 'export_types') ?: [];
    // Set export type default values.
    $export_types = _webform_entity_print_get_export_types();
    foreach ($export_types as $export_type => $definition) {
      $t_args = ['@label' => $definition['label']];
      $export_type_settings += [$export_type => []];
      $export_type_settings[$export_type] += [
        'enabled' => FALSE,
        'link_text' => $this->t('Download @label', $t_args),
        'link_attributes' => [
          'class' => [
            'button',
          ],
        ],
      ];
    }
    _webform_entity_print_form($form['third_party_settings'], $template_settings, $export_type_settings);
    // Add debug settings.
    $form['third_party_settings']['webform_entity_print']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug generated documents'),
      '#description' => $this->t('If checked, administrators will see debug links below each export type.'),
      '#return_value' => TRUE,
      '#default_value' => $third_party_settings_manager->getThirdPartySetting('webform_entity_print', 'debug') ?: FALSE,
    ];
    $form['#validate'][] = '_webform_entity_print_form_submit';
  }

  /**
   * Implements hook_webform_third_party_settings_form_alter().
   */
  #[Hook('webform_third_party_settings_form_alter')]
  public function webformThirdPartySettingsFormAlter(&$form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformThirdPartySettingsManagerInterface $third_party_settings_manager */
    $third_party_settings_manager = \Drupal::service('webform.third_party_settings_manager');
    $default_template_settings = $third_party_settings_manager->getThirdPartySetting('webform_entity_print', 'template') ?: [];
    $default_export_type_settings = $third_party_settings_manager->getThirdPartySetting('webform_entity_print', 'export_types') ?: [];
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $form_state->getFormObject()->getEntity();
    $template_settings = $webform->getThirdPartySetting('webform_entity_print', 'template') ?: [];
    $export_type_settings = $webform->getThirdPartySetting('webform_entity_print', 'export_types') ?: [];
    _webform_entity_print_form($form['third_party_settings'], $template_settings, $export_type_settings, $default_template_settings, $default_export_type_settings);
    $form['#validate'][] = '_webform_entity_print_form_submit';
  }

}
