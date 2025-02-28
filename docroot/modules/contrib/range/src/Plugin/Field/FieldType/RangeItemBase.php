<?php

namespace Drupal\range\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\range\RangeItemInterface;

/**
 * Base class for 'range' configurable field types.
 */
abstract class RangeItemBase extends FieldItemBase implements RangeItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'from' => static::getColumnSpecification($field_definition),
        'to' => static::getColumnSpecification($field_definition),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'min' => '',
      'max' => '',
      'field' => [
        'prefix' => '',
        'suffix' => '',
      ],
      'from' => [
        'prefix' => '',
        'suffix' => '',
      ],
      'to' => [
        'prefix' => '',
        'suffix' => '',
      ],
      'combined' => [
        'prefix' => '',
        'suffix' => '',
      ],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = [];

    $element['min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum'),
      '#default_value' => $this->getSetting('min'),
      '#description' => $this->t('The minimum value that should be allowed in this field. Leave blank for no minimum.'),
    ];
    $element['max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum'),
      '#default_value' => $this->getSetting('max'),
      '#description' => $this->t('The maximum value that should be allowed in this field. Leave blank for no maximum.'),
    ];
    $element += $this->fieldSettingsFormSubElementPrefixSuffix($this->t('FIELD'), 'field');
    $element += $this->fieldSettingsFormSubElementPrefixSuffix($this->t('FROM'), 'from');
    $element += $this->fieldSettingsFormSubElementPrefixSuffix($this->t('TO'), 'to');
    $element += $this->fieldSettingsFormSubElementPrefixSuffix($this->t('COMBINED'), 'combined');

    return $element;
  }

  /**
   * Helper function. Returns field properties based on the given type.
   *
   * @param string $type
   *   Range field data type. Either 'integer', 'float' or 'string'.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   An array of property definitions of contained properties, keyed by
   *   property name.
   */
  protected static function propertyDefinitionsByType($type) {
    $properties = [];
    $properties['from'] = DataDefinition::create($type)
      ->setLabel(t('From value'))
      ->setRequired(TRUE);
    $properties['to'] = DataDefinition::create($type)
      ->setLabel(t('To value'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * Helper method. Builds settings fieldsets for the FROM/TO values.
   *
   * @param string $title
   *   Fieldset title.
   * @param string $element_name
   *   Form element machine name.
   *
   * @return array
   *   FROM/TO instance settings fieldset.
   */
  protected function fieldSettingsFormSubElementPrefixSuffix($title, $element_name) {
    $element = [];

    $element[$element_name] = [
      '#type' => 'fieldset',
      '#title' => $title,
    ];
    $element[$element_name]['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#default_value' => $this->getSetting($element_name)['prefix'] ?? '',
      '#size' => 60,
      '#description' => $this->t("Define a string that should be prefixed to the value, like '$ ' or '&euro; '. Leave blank for none."),
    ];
    $element[$element_name]['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix'),
      '#default_value' => $this->getSetting($element_name)['suffix'] ?? '',
      '#size' => 60,
      '#description' => $this->t("Define a string that should be suffixed to the value, like ' m', ' kb/s'. Leave blank for none."),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    if (empty($this->from) && (string) $this->from !== '0' && empty($this->to) && (string) $this->to !== '0') {
      return TRUE;
    }
    return FALSE;
  }

}
