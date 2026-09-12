<?php

namespace Drupal\ace_editor\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'ace_editor' formatter.
 *
 * @FieldFormatter (
 *   id = "ace_formatter",
 *   label = @Translation("Ace Format"),
 *   field_types = {
 *     "text_with_summary",
 *     "text_long",
 *   }
 * )
 */
class AceFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The config_factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs an AceFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The rendered service.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, RendererInterface $renderer, ConfigFactory $config_factory, EntityFieldManagerInterface $entity_field_manager) {

    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->renderer = $renderer;
    $this->configFactory = $config_factory;
    $this->entityFieldManager = $entity_field_manager;
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    // Get default ace_editor configuration, without the option sources of the
    // settings form: Drupal merges default settings into the saved display,
    // so theme_list and syntax_list would be written into every
    // core.entity_view_display.* configuration (issue #3618752).
    $config = \Drupal::config('ace_editor.settings')->get();
    $config = array_diff_key($config, array_flip(['theme_list', 'syntax_list', '_core']));
    // The three per-formatter options below need a fallback that does not
    // depend on the active configuration: config/install is not re-imported
    // on an existing site, so without this the settings form would read
    // undefined keys there (issue #2999328).
    $config += [
      'syntax_field' => '_none',
      'modelist' => FALSE,
      'inline' => FALSE,
    ];
    return $config + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();

    $summary = [];
    $summary[] = $this->t('Theme:') . ' ' . $settings['theme'];
    $summary[] = $this->t('Syntax:') . ' ' . $settings['syntax'];
    $syntax_field = $settings['syntax_field'] ?? '_none';
    $syntax_options = $this->getSyntaxOptions();
    $summary[] = $this->t('Syntax field:') . ' ' . ($syntax_options[$syntax_field] ?? $this->t('None'));
    $summary[] = $this->t('Syntax use modelist:') . ' ' . (!empty($settings['modelist']) ? $this->t('Yes') : $this->t('No'));
    $summary[] = $this->t('Inline:') . ' ' . (!empty($settings['inline']) ? $this->t('Yes') : $this->t('No'));
    $summary[] = $this->t('Height:') . ' ' . $settings['height'];
    $summary[] = $this->t('Width:') . ' ' . $settings['width'];
    $summary[] = $this->t('Font size:') . ' ' . $settings['font_size'];
    $summary[] = $this->t('Show line numbers:') . ' ' . ($settings['line_numbers'] ? $this->t('On') : $this->t('Off'));
    $summary[] = $this->t('Show print margin:') . ' ' . ($settings['print_margins'] ? $this->t('On') : $this->t('Off'));
    $summary[] = $this->t('Show invisible characters:') . ' ' . ($settings['show_invisibles'] ? $this->t('On') : $this->t('Off'));
    $summary[] = $this->t('Toggle word wrapping:') . ' ' . ($settings['use_wrap_mode'] ? $this->t('On') : $this->t('Off'));

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState) {

    $settings = $this->getSettings();

    // $this->getSettings() returns values from defaultSettings() on first use.
    // afterwards it will return the forms saved configuration.
    $config = $this->configFactory->get('ace_editor.settings');

    return [
      'theme' => [
        '#type' => 'select',
        '#title' => $this->t('Theme'),
        '#options' => $config->get('theme_list'),
        '#attributes' => [
          'style' => 'width: 150px;',
        ],
        '#default_value' => $settings['theme'],
      ],
      'syntax' => [
        '#type' => 'select',
        '#title' => $this->t('Syntax'),
        '#description' => $this->t('The syntax that will be highlighted.'),
        '#options' => $config->get('syntax_list'),
        '#attributes' => [
          'style' => 'width: 150px;',
        ],
        '#default_value' => $settings['syntax'],
      ],
      'syntax_field' => [
        '#type' => 'select',
        '#title' => $this->t('Syntax field'),
        '#description' => $this->t('Take the syntax from a field of the entity instead of the setting above.'),
        '#options' => $this->getSyntaxOptions(),
        '#default_value' => $settings['syntax_field'] ?? '_none',
      ],
      'modelist' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Modelist'),
        '#description' => $this->t('Use the Ace modelist to convert a file extension to a syntax mode.'),
        '#default_value' => !empty($settings['modelist']),
      ],
      'inline' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Inline'),
        '#description' => $this->t('Display the code as inline code.'),
        '#default_value' => !empty($settings['inline']),
      ],
      'height' => [
        '#type' => 'textfield',
        '#title' => $this->t('Height'),
        '#description' => $this->t('The height of the editor in either pixels or percents.'),
        '#attributes' => [
          'style' => 'width: 100px;',
        ],
        '#default_value' => $settings['height'],
      ],
      'width' => [
        '#type' => 'textfield',
        '#title' => $this->t('Width'),
        '#description' => $this->t('The width of the editor in either pixels or percents.'),
        '#attributes' => [
          'style' => 'width: 100px;',
        ],
        '#default_value' => $settings['width'],
      ],
      'font_size' => [
        '#type' => 'textfield',
        '#title' => $this->t('Font size'),
        '#description' => $this->t('The the font size of the editor.'),
        '#attributes' => [
          'style' => 'width: 100px;',
        ],
        '#default_value' => $settings['font_size'],
      ],
      'line_numbers' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show line numbers'),
        '#default_value' => $settings['line_numbers'],
      ],
      'print_margins' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show print margin (80 chars)'),
        '#default_value' => $settings['print_margins'],
      ],
      'show_invisibles' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show invisible characters (whitespaces, EOL...)'),
        '#default_value' => $settings['show_invisibles'],
      ],
      'use_wrap_mode' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Toggle word wrapping'),
        '#default_value' => $settings['use_wrap_mode'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Renders front-end of our formatter.
    $elements = [];

    foreach ($items as $delta => $item) {
      // Each item gets its own settings: a page can show several fields, or
      // several entities, each with its own theme and syntax (issue
      // #2999328). They travel on the wrapper rather than through a single
      // global drupalSettings key, which also keeps them correct when the
      // markup comes from the render cache - a generated identifier would
      // not, because its counter restarts every request while the cached
      // markup does not.
      $instance_settings = $this->instanceSettings();
      $instance_settings['syntax_field_value'] = $this->syntaxFieldValue($item);

      $elements[$delta] = [
        '#type' => 'textarea',
        '#value' => $item->value,
        // Attach libraries as per the setting.
        '#attached' => [
          'library' => [
            'ace_editor/formatter',
          ],
        ],
        '#attributes' => [
          'class' => ['content'],
          'readonly' => 'readonly',
        ],
        '#theme_wrappers' => [
          'container' => [
            '#attributes' => [
              'class' => ['ace_formatter'],
              'data-ace-formatter-settings' => Json::encode($instance_settings),
            ],
          ],
        ],
      ];
    }
    return $elements;
  }

  /**
   * Returns the settings this field instance hands to the JavaScript.
   *
   * Only the keys the JavaScript reads are passed on, so a display whose
   * stored settings still carry the option lists of an older release does not
   * ship them to every visitor.
   *
   * @return array
   *   The settings of this formatter instance.
   */
  protected function instanceSettings(): array {
    $keys = [
      'theme',
      'syntax',
      'height',
      'width',
      'font_size',
      'line_numbers',
      'show_invisibles',
      'print_margins',
      'print_margin',
      'modelist',
      'inline',
    ];
    return array_intersect_key($this->getSettings(), array_flip($keys));
  }

  /**
   * Returns the syntax taken from a field of the item's entity.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item being rendered.
   *
   * @return string|null
   *   The value of the configured syntax field, or NULL when no field is
   *   configured or it is empty on this entity.
   */
  protected function syntaxFieldValue($item): ?string {
    $field_name = $this->getSetting('syntax_field');
    if (!$field_name || $field_name === '_none') {
      return NULL;
    }

    $entity = $item->getEntity();
    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return NULL;
    }

    $property = $entity->get($field_name)
      ->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getMainPropertyName();
    $value = $entity->get($field_name)->{$property};
    return is_scalar($value) ? (string) $value : NULL;
  }

  /**
   * Returns the fields that can supply a syntax.
   *
   * Only text-like fields are offered: picking an image or a reference field
   * would hand Ace a value that cannot name a syntax mode.
   *
   * @return array
   *   Select options keyed by field name.
   */
  protected function getSyntaxOptions(): array {
    $syntax_options = ['_none' => $this->t('None')];
    $entity_type_id = $this->fieldDefinition->getTargetEntityTypeId();
    $bundle = $this->fieldDefinition->getTargetBundle();
    $allowed = ['string', 'string_long', 'list_string', 'text', 'text_long', 'text_with_summary'];
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field) {
      if (in_array($field->getType(), $allowed, TRUE)) {
        $syntax_options[$field->getName()] = $field->getLabel();
      }
    }

    return $syntax_options;
  }

}
