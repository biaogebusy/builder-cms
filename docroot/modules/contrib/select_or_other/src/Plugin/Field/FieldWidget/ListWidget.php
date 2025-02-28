<?php

namespace Drupal\select_or_other\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'select_or_other_list' widget.
 *
 * @FieldWidget(
 *   id = "select_or_other_list",
 *   label = @Translation("Select or Other"),
 *   field_types = {
 *     "list_integer",
 *     "list_float",
 *     "list_string"
 *   },
 *   multiple_values = TRUE
 * )
 */
class ListWidget extends WidgetBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a ListWidget instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'add_other_value_to_allowed_values' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['add_other_value_to_allowed_values'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add entered values from the other field to the allowed values list.'),
      '#default_value' => $this->getSetting('add_other_value_to_allowed_values'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions(FieldableEntityInterface $entity = NULL) {
    $options = [];

    if ($entity) {
      $options = $this->fieldDefinition
        ->getFieldStorageDefinition()
        ->getOptionsProvider($this->getColumn(), $entity)
        ->getSettableOptions($this->currentUser);
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    if (!$this->getSetting('add_other_value_to_allowed_values')) {
      // Add original options to the element.
      $element['#original_options'] = $element['#options'];

      // Add selected "Other" values to the element.
      $element['#other_options'] = [];
      $values = $items->getValue();
      if (!empty($values)) {
        foreach ($values as $value) {
          // Set value only if value exists and is not a default option.
          if (isset($value['value']) && !isset($element['#options'][$value['value']])) {
            $element['#other_options'][] = $value['value'];
          }
        }
      }
    }

    $element = $element + [
      '#merged_values' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    unset($values['select']);
    unset($values['other']);

    if ($this->getSetting('add_other_value_to_allowed_values')) {
      $new_values = $this->extractNewValues($values);

      if (!empty($new_values)) {
        $this->addNewValuesToAllowedValues($new_values);
      }
    }

    return parent::massageFormValues($values, $form, $form_state);
  }

  /**
   * Extract unknown values found in the values array.
   *
   * @param array $values
   *   The values.
   *
   * @return array
   *   Any unknown values found in the values array.
   */
  protected function extractNewValues(array $values) {
    $allowed_values = $this->fieldDefinition->getSetting('allowed_values');
    $new_values = [];
    foreach ($values as $value) {
      if (!empty($value) && !isset($allowed_values[$value])) {
        $new_values[] = $value;
      }
    }

    return $new_values;
  }

  /**
   * Adds new values to the allowed values for this field.
   *
   * @param array $values_to_add
   *   The values to add to the allowed values.
   */
  protected function addNewValuesToAllowedValues(array $values_to_add) {
    $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
    $field_name = $this->fieldDefinition->getName();
    /** @var \Drupal\field\FieldStorageConfigInterface $storage */
    $storage = $this->entityTypeManager->getStorage('field_storage_config')->load("$entity_type.$field_name");
    $allowed_values = $storage->getSetting('allowed_values');

    foreach ($values_to_add as $value) {
      if (!isset($allowed_values[$value])) {
        $allowed_values[$value] = $value;
      }
    }

    if ($allowed_values !== $storage->getSetting('allowed_values')) {
      $storage->setSetting('allowed_values', $allowed_values)->save();
      drupal_static_reset('options_allowed_values');
    }
  }

}
