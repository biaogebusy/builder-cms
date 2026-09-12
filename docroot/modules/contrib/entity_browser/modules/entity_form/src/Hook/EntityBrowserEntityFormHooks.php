<?php

namespace Drupal\entity_browser_entity_form\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for entity_browser_entity_form.
 */
class EntityBrowserEntityFormHooks {
  use StringTranslationTrait;

  /**
   * Constructs a new EntityBrowserEntityFormHooks object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_inline_entity_form_reference_form_alter().
   */
  #[Hook('inline_entity_form_reference_form_alter')]
  public function inlineEntityFormReferenceFormAlter(&$reference_form, FormStateInterface &$form_state) {
    /** @var \Drupal\field\FieldConfigInterface $instance */
    $instance = $form_state->get([
      'inline_entity_form',
      $reference_form['#ief_id'],
      'instance',
    ]);
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $entity_form_id = $instance->getTargetEntityTypeId() . '.' . $instance->getTargetBundle() . '.default';
    // @todo 'default' might become configurable or something else in the future.
    // See https://www.drupal.org/node/2510274
    $form_display = $this->entityTypeManager->getStorage('entity_form_display')->load($entity_form_id);
    $widget = $form_display->getRenderer($instance->getName());
    $entity_browser_id = $widget->getThirdPartySetting('entity_browser_entity_form', 'entity_browser_id', '_none');
    if ($entity_browser_id === '_none') {
      return;
    }
    // Fetch the number of currently selected entities, if any.
    $count_existing_selection = count($form_state->get([
      'inline_entity_form',
      $reference_form['#ief_id'],
      'entities',
    ]));
    $cardinality = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    if ($instance->getFieldStorageDefinition()->getCardinality() !== $cardinality) {
      $cardinality = $instance->getFieldStorageDefinition()->getCardinality() - $count_existing_selection;
    }
    $bundles = $reference_form['entity_id']['#selection_settings']['target_bundles'] ?? [];
    $target_entity_type = $reference_form['entity_id']['#target_type'];
    unset($reference_form['entity_id']);
    $reference_form['entity_browser'] = [
      '#type' => 'entity_browser',
      '#entity_browser' => $entity_browser_id,
      '#cardinality' => $cardinality,
      '#entity_browser_validators' => [
        'entity_type' => [
          'type' => $target_entity_type,
        ],
      ],
      '#widget_context' => [
        'target_bundles' => $bundles,
        'target_entity_type' => $target_entity_type,
        'cardinality' => $cardinality,
      ],
    ];
    $reference_form['#attached']['library'][] = 'entity_browser_entity_form/ief_autocomplete';
    $reference_form['actions']['ief_reference_save']['#ajax']['event'] = 'entities-selected';
    // Add custom validation and submit callbacks as we need to handle
    // multi-value cases.
    $reference_form['#element_validate'][0] = 'entity_browser_entity_form_reference_form_validate';
    $reference_form['#ief_element_submit'][0] = 'entity_browser_entity_form_reference_form_submit';
  }

  /**
   * Implements hook_field_widget_third_party_settings_form().
   */
  #[Hook('field_widget_third_party_settings_form')]
  public function fieldWidgetThirdPartySettingsForm(WidgetInterface $plugin, FieldDefinitionInterface $field_definition, $form_mode, $form, FormStateInterface $form_state) {
    if ($plugin instanceof InlineEntityFormComplex) {
      $entity_browsers = $this->entityTypeManager->getStorage('entity_browser')->loadMultiple();
      if ($entity_browsers) {
        $options = [];
        foreach ($entity_browsers as $entity_browser) {
          $options[$entity_browser->id()] = $entity_browser->label();
        }
        $element['entity_browser_id'] = [
          '#type' => 'select',
          '#title' => $this->t('Entity browser'),
          '#options' => $options,
          '#empty_option' => $this->t('- None -'),
          '#empty_value' => '_none',
          '#default_value' => $plugin->getThirdPartySetting('entity_browser_entity_form', 'entity_browser_id'),
        ];
      }
      else {
        $element['message'] = [
          '#markup' => $this->t('There are no entity browsers available. You can create one <a href="@url">here</a>.', [
            '@url' => Url::fromRoute('entity.entity_browser.collection')->toString(),
          ]),
        ];
      }
      return $element;
    }
  }

  /**
   * Implements hook_field_widget_settings_summary_alter().
   */
  #[Hook('field_widget_settings_summary_alter')]
  public function fieldWidgetSettingsSummaryAlter(&$summary, $context) {
    $plugin = $context['widget'];
    if ($plugin instanceof InlineEntityFormComplex && $plugin->getThirdPartySetting('entity_browser_entity_form', 'entity_browser_id') && $plugin->getThirdPartySetting('entity_browser_entity_form', 'entity_browser_id') !== '_none') {
      $entity_browser_id = $plugin->getThirdPartySetting('entity_browser_entity_form', 'entity_browser_id');
      $entity_browser = $this->entityTypeManager->getStorage('entity_browser')->load($entity_browser_id);
      if ($entity_browser) {
        $summary[] = $this->t('Entity browser: @entity_browser.', [
          '@entity_browser' => $entity_browser->label(),
        ]);
      }
      return $summary;
    }
  }

}
