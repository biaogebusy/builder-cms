<?php

namespace Drupal\paragraphs\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\paragraphs\MigrationPluginsAlterer;
use Drupal\Core\Render\Element;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for paragraphs.
 */
class ParagraphsHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the paragraphs module.
      case 'help.page.paragraphs':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The Paragraphs module provides a field type that can contain several other fields and thereby allows users to break content up on a page. Administrators can predefine <em>Paragraphs types</em> (for example a simple text block, a video, or a complex and configurable slideshow). Users can then place them on a page in any order instead of using a text editor to add and configure such elements. For more information, see the <a href=":online">online documentation for the Paragraphs module</a>.', [
          ':online' => 'https://www.drupal.org/node/2444881',
        ]) . '</p>';
        $output .= '<h3>' . $this->t('Uses') . '</h3>';
        $output .= '<dt>' . $this->t('Creating Paragraphs types') . '</dt>';
        $output .= '<dd>' . $this->t('<em>Paragraphs types</em> can be created by clicking <em>Add Paragraphs type</em> on the <a href=":paragraphs">Paragraphs types page</a>. By default a new Paragraphs type does not contain any fields.', [
          ':paragraphs' => Url::fromRoute('entity.paragraphs_type.collection')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Configuring Paragraphs types') . '</dt>';
        $output .= '<dd>' . $this->t('Administrators can add fields to a <em>Paragraphs type</em> on the <a href=":paragraphs">Paragraphs types page</a> if the <a href=":field_ui">Field UI</a> module is enabled. The form display and the display of the Paragraphs type can also be managed on this page. For more information on fields and entities, see the <a href=":field">Field module help page</a>.', [
          ':paragraphs' => Url::fromRoute('entity.paragraphs_type.collection')->toString(),
          ':field' => Url::fromRoute('help.page', [
            'name' => 'field',
          ])->toString(),
          ':field_ui' => \Drupal::moduleHandler()->moduleExists('field_ui') ? Url::fromRoute('help.page', [
            'name' => 'field_ui',
          ])->toString() : '#',
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Creating content with Paragraphs') . '</dt>';
        $output .= '<dd>' . $this->t('Administrators can add a <em>Paragraph</em> field to content types or other entities, and configure which <em>Paragraphs types</em> to include. When users create content, they can then add one or more paragraphs by choosing the appropriate type from the dropdown list. Users can also dragdrop these paragraphs. This allows users to add structure to a page or other content (for example by adding an image, a user reference, or a differently formatted block of text) more easily then including it all in one text field or by using fields in a pre-defined order.') . '</dd>';
        return $output;

      break;
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_entity_form_display_edit_form_alter')]
  public static function formEntityFormDisplayEditFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($form['#entity_type'], $form['#bundle']);
    // Loop over ERR field's display options with paragraph target type.
    foreach (array_keys($field_definitions) as $field_name) {
      if ($field_definitions[$field_name]->getType() == 'entity_reference_revisions') {
        if ($field_definitions[$field_name]->getSettings()['target_type'] == 'paragraph') {
          foreach ([
            'options_buttons',
            'options_select',
            'entity_reference_revisions_autocomplete',
          ] as $option) {
            unset($form['fields'][$field_name]['plugin']['type']['#options'][$option]);
          }
        }
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Indicate unsupported multilingual paragraphs field configuration.
   */
  #[Hook('form_field_config_edit_form_alter')]
  public function formFieldConfigEditFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    $field = $form_state->getFormObject()->getEntity();
    if (\Drupal::hasService('content_translation.manager')) {
      $bundle_is_translatable = \Drupal::service('content_translation.manager')->isEnabled($field->getTargetEntityTypeId(), $field->getTargetBundle());
      if ($bundle_is_translatable && $field->getType() === 'entity_reference_revisions' && $field->getSetting('target_type') === 'paragraph') {
        // This is a translatable ERR field pointing to a paragraph.
        $message_display = 'warning';
        $message_text = $this->t('Paragraphs fields do not support translation. See the <a href=":documentation">online documentation</a>.', [
          ':documentation' => Url::fromUri('https://www.drupal.org/node/2735121')->toString(),
        ]);
        if ($form['translatable']['#default_value'] == TRUE) {
          $message_display = 'error';
        }
        $form['paragraphs_message'] = [
          '#type' => 'container',
          '#markup' => $message_text,
          '#attributes' => [
            'class' => [
              'messages messages--' . $message_display,
            ],
          ],
          '#weight' => 0,
        ];
      }
    }
    if ($field->getType() == 'entity_reference') {
      $selector = 'field_storage[subform][settings][target_type]';
      // Add a note about paragraphs if selected.
      $form['field_storage']['subform']['settings']['paragraph_warning_wrapper'] = [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            ':input[name="' . $selector . '"]' => [
              'value' => 'paragraph',
            ],
          ],
        ],
        'warning' => [
          '#theme' => 'status_messages',
          '#message_list' => [
            'warning' => [
              $this->t('Note: Regular paragraph fields should use the revision based reference fields, entity reference fields should only be used for cases when an existing paragraph is referenced from somewhere else.'),
            ],
          ],
          '#status_headings' => [
            'error' => $this->t('Warning'),
          ],
        ],
      ];
    }
  }

  /**
   * Implements hook_preprocess_form_element__new_storage_type().
   */
  #[Hook('preprocess_form_element__new_storage_type')]
  public static function preprocessFormElementNewStorageType(&$variables) {
    $variables['#attached']['library'][] = 'paragraphs/paragraph-field-type-icon';
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Indicate unsupported multilingual paragraphs field configuration.
   *
   * Add a warning that paragraph fields can not be translated.
   * Switch to error if a paragraph field is marked as translatable.
   */
  #[Hook('form_language_content_settings_form_alter')]
  public function formLanguageContentSettingsFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    // Without it Paragraphs message are meaningless.
    if (!\Drupal::hasService('content_translation.manager')) {
      return;
    }
    $content_translation_manager = \Drupal::service('content_translation.manager');
    $message_display = 'warning';
    $message_text = $this->t('(* unsupported) Paragraphs fields do not support translation. See the <a href=":documentation">online documentation</a>.', [
      ':documentation' => Url::fromUri('https://www.drupal.org/node/2735121')->toString(),
    ]);
    $map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('entity_reference_revisions');
    foreach ($map as $entity_type_id => $info) {
      if (!$content_translation_manager->isEnabled($entity_type_id)) {
        continue;
      }
      $field_storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface  $storage_definition */
      foreach ($field_storage_definitions as $name => $storage_definition) {
        if ($storage_definition->getSetting('target_type') && $storage_definition->getSetting('target_type') == 'paragraph') {
          // For configurable fields, check all bundles on which the field exists,
          // for base fields that are translable, check all bundles,
          // untranslatable base fields do not show up at all.
          $bundles = [];
          if ($storage_definition instanceof FieldStorageConfigInterface) {
            $bundles = $storage_definition->getBundles();
          }
          elseif ($storage_definition->isTranslatable()) {
            $bundles = Element::children($form['settings'][$entity_type_id]);
          }
          foreach ($bundles as $bundle) {
            if (!$content_translation_manager->isEnabled($entity_type_id, $bundle)) {
              continue;
            }
            // Update the label and if the paragraph field is translatable,
            // display an error message instead of just a warning.
            if (isset($form['settings'][$entity_type_id][$bundle]['fields'][$name]['#label'])) {
              $form['settings'][$entity_type_id][$bundle]['fields'][$name]['#label'] = $this->t('@field_label (* unsupported)', [
                '@field_label' => $form['settings'][$entity_type_id][$bundle]['fields'][$name]['#label'],
              ]);
            }
            if (!empty($form['settings'][$entity_type_id][$bundle]['fields'][$name]['#default_value'])) {
              $message_display = 'error';
            }
          }
        }
      }
    }
    // Update the description on the hide untranslatable fields checkbox.
    if (isset($form['settings']['paragraph'])) {
      $paragraph_untranslatable_hide_description = $this->t('Paragraph types that are used in moderated content requires non-translatable fields to be edited in the original language form and this must be checked.');
      foreach (Element::children($form['settings']['paragraph']) as $bundle) {
        if (!empty($form['settings']['paragraph'][$bundle]['settings']['content_translation']['untranslatable_fields_hide'])) {
          $form['settings']['paragraph'][$bundle]['settings']['content_translation']['untranslatable_fields_hide']['#description'] = $paragraph_untranslatable_hide_description;
        }
      }
    }
    $form['settings']['paragraphs_message'] = [
      '#type' => 'container',
      '#markup' => $message_text,
      '#attributes' => [
        'class' => [
          'messages messages--' . $message_display,
        ],
      ],
      '#weight' => 0,
    ];
  }

  /**
   * Implements hook_preprocess_HOOK() for field_multiple_value_form().
   *
   * Reorganize table rows and columns added in
   * template_preprocess_field_multiple_value_form() to suite Paragraph's needs.
   *
   * @see template_preprocess_field_multiple_value_form()
   */
  #[Hook('preprocess_field_multiple_value_form')]
  public static function preprocessFieldMultipleValueForm(&$variables) {
    // Ensure we are dealing with one of the paragraphs widgets in a table.
    $paragraph_widget = !empty($variables['element']['#paragraphs_widget']);
    $inline_paragraph_widget = !empty($variables['element']['#inline_paragraphs_widget']);
    if (!$paragraph_widget && !$inline_paragraph_widget || !isset($variables['table'])) {
      return;
    }
    // Find paragraph_actions and move to header.
    // @see template_preprocess_field_multiple_value_form()
    if (!empty($variables['table']['#header']) && isset($variables['table']['#rows'][0]) && is_array($variables['table']['#rows'][0]['data'][1]) && !empty($variables['table']['#rows'][0]['data'][1]['data']['#paragraphs_header'])) {
      $variables['table']['#header'][0]['data'] = [
        'title' => $variables['table']['#header'][0]['data'],
        'button' => $variables['table']['#rows'][0]['data'][1]['data'],
      ];
      unset($variables['table']['#rows'][0]);
    }
    // Remove the drag handler if we are translating, if the field's cardinality
    // is 1 or if there are no paragraphs added.
    $translating = isset($variables['element']['#allow_reference_changes']) && !$variables['element']['#allow_reference_changes'];
    $cardinality = (int) ($variables['element']['#cardinality'] ?? NULL);
    $no_paragraphs = isset($variables['table']['#rows']) && count($variables['table']['#rows']) === 0;
    $remove_tabledrag = isset($variables['table']['#tabledrag']) && ($translating || $cardinality === 1 || $no_paragraphs);
    if ($remove_tabledrag) {
      unset($variables['table']['#tabledrag'], $variables['table']['#header'][2]);
    }
    foreach ($variables['table']['#rows'] as $key => $row) {
      // Add the paragraph type as a class to every row.
      if ($paragraph_widget && isset($row['data'][1]['data']['#paragraph_type'])) {
        $variables['table']['#rows'][$key]['class'][] = 'paragraph-type--' . str_replace('_', '-', $row['data'][1]['data']['#paragraph_type']);
      }
      // Remove the row cell added for the items _actions not implemented by
      // paragraphs.
      unset($variables['table']['#rows'][$key]['data'][2]);
      // Fix table drag related cells, if applicable.
      if ($remove_tabledrag) {
        $variables['table']['#rows'][$key]['data'][0]['class'][] = 'paragraph-bullet';
        // Restore the removed weight and give access FALSE.
        if (isset($row['data'][3])) {
          $variables['table']['#rows'][$key]['data'][1]['data']['_weight'] = $row['data'][3]['data'];
          unset($variables['table']['#rows'][$key]['data'][3]);
          $variables['table']['#rows'][$key]['data'][1]['data']['_weight']['#access'] = FALSE;
        }
      }
    }
    // Remove the header cell added for the _actions column not implemented by
    // paragraphs.
    unset($variables['table']['#header'][1]);
  }

  /**
   * Implements hook_migration_plugins_alter().
   *
   * @todo refactor/rethink this when
   * https://www.drupal.org/project/drupal/issues/2904765 is resolved
   */
  #[Hook('migration_plugins_alter')]
  public static function migrationPluginsAlter(array &$migrations) {
    $migration_plugins_alterer = \Drupal::service('paragraphs.migration_plugins_alterer');
    assert($migration_plugins_alterer instanceof MigrationPluginsAlterer);
    $migration_plugins_alterer->alterMigrationPlugins($migrations);
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public static function entityTypeAlter(array &$entity_types) {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    // Remove the handler class for moderation as it is managed by the host.
    $entity_types['paragraph']->setHandlerClass('moderation', '');
  }

  /**
   * Implements hook_entity_base_field_info_alter().
   */
  #[Hook('entity_base_field_info_alter')]
  public static function entityBaseFieldInfoAlter(&$fields, EntityTypeInterface $entity_type) {
    // Since the paragraph entity doesn't have uid fields anymore, remove the
    // content_translation_uid from the field definitions.
    if ($entity_type->id() == 'paragraph' && isset($fields['content_translation_uid'])) {
      unset($fields['content_translation_uid']);
    }
  }

}
