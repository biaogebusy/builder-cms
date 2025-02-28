<?php

namespace Drupal\shs\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\shs\StringTranslationTrait;
use Drupal\shs\WidgetDefaults;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'options_shs' widget.
 *
 * @FieldWidget(
 *   id = "options_shs",
 *   label = @Translation("Simple hierarchical select"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class OptionsShsWidget extends OptionsSelectWidget implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The widget defaults SHS service.
   *
   * @var \Drupal\shs\WidgetDefaults
   */
  protected $widgetDefaults;

  /**
   * Constructs a new OptionsShsWidget object.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param array $settings
   *   Field settings.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\shs\WidgetDefaults $widget_defaults
   *   The widget defaults SHS service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, WidgetDefaults $widget_defaults) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->widgetDefaults = $widget_defaults;
    // Set translation context.
    $this->translationContext = 'shs:options_widget';
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
      $container->get('shs.widget_defaults')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings_default = [
      'display_node_count' => FALSE,
      'create_new_items' => FALSE,
      'create_new_levels' => FALSE,
      'force_deepest' => FALSE,
    ];
    return $settings_default + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();

    $element['create_new_items'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow creating new items'),
      '#default_value' => $this->getSetting('create_new_items'),
      '#description' => $this->t('Allow users to create new items of the source bundle.'),
      '#disabled' => TRUE,
    ];
    $element['create_new_levels'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow creating new levels'),
      '#default_value' => $this->getSetting('create_new_levels'),
      '#description' => $this->t('Allow users to create new children for items which do not have any children yet.'),
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $field_name . '][settings_edit_form][settings][create_new_items]"]' => ['checked' => TRUE],
        ],
      ],
      '#disabled' => TRUE,
    ];
    $element['force_deepest'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force selection of deepest level'),
      '#default_value' => $this->getSetting('force_deepest'),
      '#description' => $this->t('Force users to select terms from the deepest level.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    if ($this->getSetting('create_new_items')) {
      $summary[] = $this->t('Allow creation of new items');
      if ($this->getSetting('create_new_levels')) {
        $summary[] = $this->t('Allow creation of new levels');
      }
      else {
        $summary[] = $this->t('Do not allow creation of new levels');
      }
    }
    else {
      $summary[] = $this->t('Do not allow creation of new items');
    }
    if ($this->getSetting('force_deepest')) {
      $summary[] = $this->t('Force selection of deepest level');
    }
    else {
      $summary[] = $this->t('Do not force selection of deepest level');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    if (isset($form_state->getBuildInfo()['base_form_id']) && ('field_config_form' === $form_state->getBuildInfo()['base_form_id'])) {
      // Do not display the shs widget in the field config.
      return $element;
    }

    // Rewrite element to use a simple textfield.
    $element['#type'] = 'textfield';
    unset($element['#options']);

    if (!$element['#default_value']) {
      $element['#default_value'] = '';
    }

    $field_name = $this->fieldDefinition->getName();
    $default_value = $element['#default_value'] ?: NULL;
    $user_input = $form_state->getUserInput();

    if (!empty($element['#field_parents'])) {
      $field_parents = $element['#field_parents'];
      $field_parents[] = $field_name;

      $value = NestedArray::getValue($user_input, $field_parents);
      $default_value = $value ?: $default_value;
    }
    elseif (isset($user_input[$field_name])) {
      $default_value = $user_input[$field_name];
    }
    if (is_array($default_value) && (count($default_value) === 1) && empty($default_value[0])) {
      // Sometimes we get a list of empty default values which equals to no
      // value at all.
      $default_value = NULL;
    }

    $target_bundles = $this->getFieldSetting('handler_settings')['target_bundles'];
    $settings_additional = [
      'required' => $this->required,
      'multiple' => $this->multiple,
      'anyLabel' => $this->getEmptyLabel() ?: $this->t('- None -'),
      'anyValue' => '_none',
      'addNewLabel' => $this->t('Add another item'),
    ];

    $bundle = reset($target_bundles);
    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($bundle);
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();

    // Define default parents for the widget.
    $parents = $this->widgetDefaults->getInitialParentDefaults($settings_additional['anyValue'], $cardinality);
    if ($default_value) {
      $parents = $this->widgetDefaults->getParentDefaults($default_value, $settings_additional['anyValue'], $this->fieldDefinition->getItemDefinition()->getSetting('target_type'), $cardinality);
    }

    $settings_shs = [
      'settings' => $this->getSettings() + $settings_additional,
      'bundle' => $bundle,
      'bundleLabel' => $vocabulary->label(),
      'baseUrl' => 'shs-term-data',
      'cardinality' => $cardinality,
      'parents' => $parents,
      'defaultValue' => $default_value,
    ];

    $hooks = [
      'shs_js_settings',
      "shs_{$field_name}_js_settings",
    ];
    // Allow other modules to override the settings.
    \Drupal::moduleHandler()->alter($hooks, $settings_shs, $bundle, $field_name);

    $element += [
      '#shs' => $settings_shs,
    ];
    if (empty($element['#attributes'])) {
      $element['#attributes'] = [];
    }
    $element['#attributes'] = array_merge($element['#attributes'], [
      'class' => ['shs-enabled'],
    ]);
    if (empty($element['#attached'])) {
      $element['#attached'] = [];
    }
    $element['#attached'] = array_merge($element['#attached'], [
      'library' => ['shs/shs.form'],
    ]);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function afterBuild(array $element, FormStateInterface $form_state) {
    $element = parent::afterBuild($element, $form_state);

    if (empty($element['#shs'])) {
      // Simply return the unaltered element if there is no information attached
      // about SHS (i.e. on field config forms).
      return $element;
    }

    $context = [
      'settings' => empty($element['#shs']['settings']) ? [] : $element['#shs']['settings'],
    ];
    // Create unique key for field.
    $element_key = Html::getUniqueId(sprintf('shs-%s', $element['#field_name']));
    $element['#attributes'] = array_merge($element['#attributes'], [
      'data-shs-selector' => $element_key,
    ]);

    $element['#shs'] += [
      'classes' => shs_get_class_definitions($element['#field_name'], $context),
    ];
    $element['#attached'] = $element['#attached'] ?: [];
    $element['#attached'] = array_merge($element['#attached'], [
      'drupalSettings' => [
        'shs' => [
          $element_key => $element['#shs'],
        ],
      ],
    ]);

    return $element;
  }

  /**
   * {@inheritDoc}
   */
  protected function getSelectedOptions(FieldItemListInterface $items) {
    $selected_options = [];
    foreach ($items as $item) {
      $selected_options[] = $item->target_id;
    }
    return $selected_options;
  }

  /**
   * {@inheritDoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    if (!isset($values[0]['target_id']) || ($values[0]['target_id'] === '')) {
      return NULL;
    }

    $exploded_values = [];

    if (strpos($values[0]['target_id'], ',') !== FALSE) {
      $exploded_values = explode(',', $values[0]['target_id']);
      $values = [];
    }
    elseif (strpos($values[0]['target_id'], ' ') !== FALSE) {
      $exploded_values = explode(' ', $values[0]['target_id']);
      $values = [];
    }

    foreach ($exploded_values as $value) {
      $values[]['target_id'] = $value;
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // The widget currently only works for taxonomy terms.
    if ($field_definition->getSetting('target_type') !== 'taxonomy_term') {
      return FALSE;
    }
    // The widget only works with fields having one target bundle as source.
    $handler_settings = $field_definition->getSetting('handler_settings');
    return isset($handler_settings['target_bundles']) && (count($handler_settings['target_bundles']) === 1);
  }

  /**
   * {@inheritdoc}
   */
  public static function validateElement(array $element, FormStateInterface $form_state) {
    parent::validateElement($element, $form_state);
    if (empty($element['#shs']['settings']['force_deepest']) || $form_state->hasAnyErrors()) {
      return;
    }
    $value = $element['#value'];
    if (!is_array($value)) {
      $value = [$value];
    }
    $first_value = reset($value);
    if ($element['#shs']['settings']['anyValue'] === $first_value || empty($first_value)) {
      if (!$element['#required']) {
        return;
      }
      elseif (!isset($element['#options']) || count($element['#options']) > 1) {
        $form_state->setError($element, t('You need to select a term from the deepest level in field @name.', ['@name' => $element['#title']]));
        return;
      }
    }
    foreach ($value as $element_value) {
      if (shs_term_has_children($element_value)) {
        $form_state->setError($element, t('You need to select a term from the deepest level in field @name.', ['@name' => $element['#title']]));
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsGroups() {
    // We do not support optgroups.
    return FALSE;
  }

  /**
   * Return string representation of a setting.
   *
   * @param string $key
   *   Name of the setting.
   *
   * @return string
   *   Value of the setting. If boolean, the value is "translated" to 'true' or
   *   'false'.
   */
  protected function settingToString($key) {
    $options = [
      FALSE => $this->t('false'),
      TRUE => $this->t('true'),
    ];
    $value = $this->getSetting($key);
    if (!is_bool($value)) {
      return $value;
    }
    return $options[$value];
  }

}
