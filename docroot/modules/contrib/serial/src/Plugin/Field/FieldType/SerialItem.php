<?php

namespace Drupal\serial\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TranslatableInterface;

/**
 * Plugin implementation of the 'serial' field type.
 *
 * @todo should not be translatable, by default
 *
 * @FieldType(
 *   id = "serial",
 *   label = @Translation("Serial"),
 *   description = @Translation("Auto increment serial field type."),
 *   category = @Translation("Number"),
 *   default_widget = "serial_default_widget",
 *   default_formatter = "serial_default_formatter"
 * )
 */
class SerialItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field) {
    return [
      'columns' => [
        'value' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'sortable' => TRUE,
          'views' => TRUE,
          'index' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'start_value' => 1,
      'init_existing_entities' => 0,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = [];

    $element['start_value'] = [
      '#type' => 'number',
      '#title' => $this->t('Starting value'),
      '#default_value' => $this->getSetting('start_value'),
      '#min' => 1,
      '#required' => TRUE,
      '#disabled' => $has_data,
    ];
    $element['init_existing_entities'] = [
      '#type' => 'radios',
      '#title' => $this->t('Start on existing entities'),
      '#description' => $this->t('When this field is created for a bundle that already have entities.'),
      '#options' => [0 => $this->t('No'), 1 => $this->t('Yes')],
      '#default_value' => $this->getSetting('init_existing_entities'),
      '#required' => TRUE,
      '#disabled' => $has_data,
    ];

    // Only run the initialization when the field has no data.
    if (!$has_data) {
      // @todo ideally, use submit handler and not validate
      // $handlers = $form_state->getSubmitHandlers();
      // $handlers[] = [$this, 'initializeEntitiesCallback'];
      // $form_state->setSubmitHandlers($handlers);
      $form['#validate'][] = [$this, 'initializeEntitiesCallback'];
    }
    return $element;
  }

  /**
   * Initialize entities depending on the storage settings.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   */
  public function initializeEntitiesCallback(array &$form, FormStateInterface $form_state) {
    // Check if existing entities have to be initialized.
    $settings = $form_state->getValue('settings');
    if ((int) $settings['init_existing_entities'] === 1) {
      $startValue = (int) $settings['start_value'];
      // Check then first if the bundle has entities.
      $fieldConfig = $this->getFieldDefinition();
      $entityTypeId = $fieldConfig->getTargetEntityTypeId();
      $storage = \Drupal::entityTypeManager()->getStorage($entityTypeId);
      $bundleKey = $storage->getEntityType()->getKey('bundle');
      $bundle = $fieldConfig->getTargetBundle();
      $query = \Drupal::entityQuery($entityTypeId);
      $query->condition($bundleKey, $bundle);
      $ids = $query->execute();

      if (count($ids) > 0) {
        /** @var \Drupal\serial\SerialStorageInterface $serialStorage */
        $serialStorage = \Drupal::getContainer()->get('serial.sql_storage');
        // Set serial values for existing entities.
        $oldCount = $serialStorage->initOldEntries(
          $entityTypeId,
          $bundle,
          $fieldConfig->getFieldStorageDefinition()->getName(),
          $startValue
        );
        if ($oldCount > 0) {
          \Drupal::messenger()->addMessage(t('Serial values have been automatically set for %count existing entities, starting from %start_value.', [
            '%count' => $oldCount,
            '%start_value' => $startValue,
          ]));
        }
      }
      else {
        \Drupal::messenger()->addWarning(t('No entities to initialize, the next entity to be created will start from %start_value.', [
          '%start_value' => $startValue,
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Update the values and return them.
    foreach ($this->properties as $name => $property) {
      $value = $property->getValue();
      // Only write NULL values if the whole map is not NULL.
      if (isset($this->values) || isset($value)) {
        $this->values[$name] = $value;
      }
    }
    return $this->values;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // @todo review DataDefinition methods : setReadOnly, setComputed, setRequired, setConstraints
    $properties['value'] = DataDefinition::create('integer')
      ->setLabel(t('Serial'))
      ->setComputed(TRUE)
      ->setInternal(FALSE)
      ->setRequired(TRUE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    // For numbers, the field is empty if the value isn't numeric.
    // But should never be treated as empty.
    $empty = $value === NULL || !is_numeric($value);
    return $empty;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $value = $this->getSerial();
    if (isset($value)) {
      $this->setValue($value);
    }
  }

  /**
   * Gets the serial for this entity type, bundle, field instance.
   *
   * @return int
   *   serial id
   */
  private function getSerial() {
    $serial = NULL;
    $entity = $this->getEntity();
    $newSerial = FALSE;

    // Does not apply if the node is not new or translated.
    if ($entity->isNew()) {
      $newSerial = TRUE;
    }
    else {
      // Handle entity translation: fetch the same id or another one
      // depending of what is the design.
      // This should probably be solved by the end user decision
      // while setting the field translation.
      /** @var \Drupal\Core\Language\LanguageManagerInterface $languageManager */
      $languageManager = \Drupal::getContainer()->get('language_manager');
      // @todo isMultilingual is global, prefer local hasTranslation
      if ($languageManager->isMultilingual() && $entity instanceof TranslatableInterface) {
        $newSerial = $entity->isNewTranslation();
      }
    }

    if ($newSerial) {
      /** @var \Drupal\serial\SerialStorageInterface $serialStorage */
      $serialStorage = \Drupal::getContainer()->get('serial.sql_storage');
      $serial = $serialStorage->generateValue($this->getFieldDefinition(), $this->getEntity());

      // Get the starting value from the storage settings.
      $settings = $this->getSettings();
      $startValue = isset($settings['start_value']) ? $settings['start_value'] : 1;
      // Subtract one as it is already added in code above.
      $serial = $serial + $startValue - 1;
    }

    return $serial;
  }

}
