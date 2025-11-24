<?php

namespace Drupal\charts\Plugin\views\field;

use Drupal\charts\ChartViewsFieldInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @file
 * Defines Drupal\charts\Plugin\views\field\NumericArrayField.
 */
/**
 * Field handler to provide values for a numeric array.
 */
#[ViewsField("field_charts_numeric_array")]
class NumericArrayField extends FieldPluginBase implements ContainerFactoryPluginInterface, ChartViewsFieldInterface {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The ordinal labels.
   *
   * @var array
   */
  protected static $ordinalLabels = [
    'first',
    'second',
    'third',
    'fourth',
    'fifth',
    'sixth',
    'seventh',
    'eighth',
    'ninth',
    'tenth',
  ];

  /**
   * Constructs a new NumericArrayField object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // No extra query modifications are necessary.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $this->field_alias = 'numeric_array_field';

    // Define the array length option (defaulting to 3 items).
    $options['array_length'] = ['default' => 3];

    // Define an option for each possible ordinal key (up to 10).
    foreach (self::$ordinalLabels as $ordinal) {
      $options['ordinal_fields'][$ordinal] = ['default' => ''];
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Store the form state value for later use in AJAX.
    $form_state->set('numeric_array_field_plugin', $this);

    // Get the current array length from form_state or use the option value.
    // Note: Must account for where in the form state it might be.
    $array_length = $this->options['array_length'];
    $input = $form_state->getUserInput();

    // Try to load from user input - need to handle different form structures.
    if (!empty($input['options']['array_length'])) {
      $array_length = $input['options']['array_length'];
    }
    elseif (!empty($input['array_length'])) {
      $array_length = $input['array_length'];
    }

    // Add a select element for array length with AJAX.
    $form['array_length'] = [
      '#type' => 'select',
      '#title' => $this->t('Array Length'),
      '#description' => $this->t('Select the number of items in the array (1-10).'),
      '#options' => [
        1  => $this->t('1'),
        2  => $this->t('2'),
        3  => $this->t('3'),
        4  => $this->t('4'),
        5  => $this->t('5'),
        6  => $this->t('6'),
        7  => $this->t('7'),
        8  => $this->t('8'),
        9  => $this->t('9'),
        10 => $this->t('10'),
      ],
      '#default_value' => $array_length,
      '#ajax' => [
        'callback' => [static::class, 'updateOrdinalFieldsCallback'],
        'wrapper'  => 'ordinal-fields-wrapper',
        'event'    => 'change',
        // Use the Views UI-specific URL builder.
        // @phpstan-ignore-next-line
        'url' => views_ui_build_form_url($form_state),
      ],
      // Still need this to prevent full validation during AJAX.
      '#limit_validation_errors' => [],
    ];

    // Get available field labels from the view.
    $fieldList = $this->displayHandler->getFieldLabels();
    if (isset($fieldList[$this->options['id']])) {
      unset($fieldList[$this->options['id']]);
    }

    // Create the container for ordinal fields.
    $form['ordinal_fields'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Ordinal Field Mappings'),
      '#attributes' => ['id' => 'ordinal-fields-wrapper'],
      '#tree' => TRUE,
    ];

    // Add the ordinal field options.
    foreach (self::$ordinalLabels as $index => $ordinal) {
      if ($index < $array_length) {
        $form['ordinal_fields'][$ordinal] = [
          '#type' => 'radios',
          '#title' => $this->t('@ordinal value', ['@ordinal' => ucfirst($ordinal)]),
          '#options' => $fieldList,
          '#default_value' => $this->options['ordinal_fields'][$ordinal],
        ];
      }
    }

    return $form;
  }

  /**
   * Static AJAX callback to update the ordinal fields container.
   *
   * @param array $form
   *   The full form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The render array for the ordinal fields container.
   */
  public static function updateOrdinalFieldsCallback(array $form, FormStateInterface $form_state): array {
    // For add field form.
    if (isset($form['options']['array_length'])) {
      // This is an existing field being edited.
      if (isset($form['options']['ordinal_fields'])) {
        return $form['options']['ordinal_fields'];
      }
    }
    // For normal view edit form.
    elseif (isset($form['array_length'])) {
      if (isset($form['ordinal_fields'])) {
        return $form['ordinal_fields'];
      }
    }

    // Look in the section structure.
    $section = $form_state->get('section');
    if (!empty($section) && isset($form['options'][$section])) {
      if (isset($form['options'][$section]['ordinal_fields'])) {
        return $form['options'][$section]['ordinal_fields'];
      }
    }

    // Deep search for our container.
    $keys = array_keys($form);
    foreach ($keys as $key) {
      if (is_array($form[$key]) && isset($form[$key]['ordinal_fields'])) {
        return $form[$key]['ordinal_fields'];
      }
    }

    // Final fallback (should never reach here).
    return $form;
  }

  /**
   * Retrieves the numeric value from the view row for a given ordinal key.
   *
   * @param \Drupal\views\ResultRow $values
   *   The view result row.
   * @param string $ordinal
   *   The ordinal key (e.g., 'first', 'second').
   *
   * @return float|null
   *   The numeric value or NULL if not found.
   */
  protected function getFieldValue(ResultRow $values, string $ordinal): ?float {
    $field = $this->options['ordinal_fields'][$ordinal];
    if (!empty($field) && isset($this->view->field[$field])) {
      return floatval($this->view->field[$field]->getValue($values));
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    parent::getValue($values, $field);
    $array_length = $this->options['array_length'];
    $numeric_array = [];

    // Build the array using ordinal keys up to the selected length.
    for ($i = 0; $i < $array_length; $i++) {
      $ordinal = self::$ordinalLabels[$i];
      $numeric_array[$ordinal] = $this->getFieldValue($values, $ordinal);
    }

    return Json::encode(array_values($numeric_array));
  }

  /**
   * {@inheritdoc}
   */
  public function getChartFieldDataType(): string {
    return 'array';
  }

}
