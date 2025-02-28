<?php

namespace Drupal\autocomplete_deluxe\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Plugin implementation of the 'options_buttons' widget.
 *
 * @FieldWidget(
 *   id = "autocomplete_deluxe",
 *   label = @Translation("Autocomplete Deluxe"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class AutocompleteDeluxeWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Current Account Interface.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Key value service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValue;

  /**
   * {@inheritdoc}
   *
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition for the operation.
   * @param array $settings
   *   The formatter settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current account.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value
   *   Key value storage.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ModuleHandlerInterface $module_handler, AccountInterface $account, KeyValueFactoryInterface $key_value) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->moduleHandler = $module_handler;
    $this->account = $account;
    $this->keyValue = $key_value;
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
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('keyvalue')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'match_operator' => 'CONTAINS',
      'autocomplete_route_name' => 'autocomplete_deluxe.autocomplete',
      'size' => 60,
      'selection_handler' => 'default',
      'limit' => 10,
      'min_length' => 0,
      'delimiter' => '',
      'not_found_message_allow' => FALSE,
      'not_found_message' => "The term '@term' will be added",
      'new_terms' => FALSE,
      'no_empty_message' => 'No terms could be found. Please type in order to add a new term.',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['match_operator'] = [
      '#type' => 'select',
      '#title' => $this->t('Match operator'),
      '#description' => $this->t('Specify the matcting operator.'),
      '#default_value' => $this->getSetting('match_operator'),
      '#options' => $this->getMatchOperatorOptions(),
    ];
    $element['limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Limit of the output.'),
      '#description' => $this->t('If set to zero no limit will be used'),
      '#default_value' => $this->getSetting('limit'),
      '#element_validate' => [[get_class($this), 'validateInteger']],
    ];
    $element['min_length'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Minimum length.'),
      '#description' => $this->t('The minimum length of characters to enter to open the suggestion list.'),
      '#default_value' => $this->getSetting('min_length'),
      '#element_validate' => [[get_class($this), 'validateInteger']],
    ];
    $element['delimiter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delimiter.'),
      '#description' => $this->t('A character which should be used beside the enter key, to separate terms.'),
      '#default_value' => $this->getSetting('delimiter'),
      '#size' => 1,
    ];
    $element['not_found_message_allow'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Term not found message'),
      '#description' => $this->t('If this is enabled, a message will be displayed when the term is not found.'),
      '#default_value' => $this->getSetting('not_found_message_allow'),
    ];
    $element['not_found_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Term not found message.'),
      '#description' => $this->t('A message text which will be displayed, if the entered term was not found.'),
      '#default_value' => $this->getSetting('not_found_message'),
    ];
    $element['new_terms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow new terms'),
      '#description' => $this->t('Should it be allowed, that user enter new terms?'),
      '#default_value' => $this->getSetting('new_terms'),
    ];
    $element['no_empty_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Empty value message.'),
      '#description' => $this->t('A text message that will be displayed when the field is focused and it does not contain values.'),
      '#default_value' => $this->getSetting('no_empty_message'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('Match operator: @match_operator', ['@match_operator' => $this->getSetting('match_operator')]);
    $summary[] = $this->t('Limit: @limit', ['@limit' => $this->getSetting('limit')]);
    $summary[] = $this->t('Min length: @min_length', ['@min_length' => $this->getSetting('min_length')]);
    $summary[] = $this->t('Delimiter: @delimiter', ['@delimiter' => $this->getSetting('delimiter')]);
    $summary[] = $this->t('Allow Not Found message: @not_found_message_allow', ['@not_found_message_allow' => $this->getSetting('not_found_message_allow') ? 'Yes' : 'No']);
    $summary[] = $this->t('Not Found message: @not_found_message', ['@not_found_message' => $this->getSetting('not_found_message')]);
    $summary[] = $this->t('Allow new terms: @new_terms', ['@new_terms' => $this->getSetting('new_terms') ? 'Yes' : 'No']);
    $summary[] = $this->t('Empty value message: @no_empty_message', ['@no_empty_message' => $this->getSetting('no_empty_message')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $items->getEntity();
    $instance = $this->fieldDefinition;
    $cardinality = $instance->getFieldStorageDefinition()->getCardinality();
    $target_type = $instance->getFieldStorageDefinition()->getSetting('target_type');
    $settings = $this->getSettings();
    $referenced_entities = $items->referencedEntities();

    $selection_settings = $this->getFieldSetting('handler_settings') + ['match_operator' => $this->getSetting('match_operator')];

    $allow_message = $settings['not_found_message_allow'] ?? FALSE;
    $not_found_message = $settings['not_found_message'] ?? "";

    $element += [
      '#type' => 'autocomplete_deluxe',
      '#title' => $this->fieldDefinition->getLabel(),
      '#target_type' => $target_type,
      '#selection_handler' => $this->getFieldSetting('handler'),
      '#selection_settings' => $selection_settings,
      '#size' => 60,
      '#limit' => $settings['limit'] ?? 10,
      '#min_length' => $settings['min_length'] ?? 0,
      '#delimiter' => $settings['delimiter'] ?? '',
      '#not_found_message_allow' => $allow_message,
      '#not_found_message' => $this->t('@not_found_message', ['@not_found_message' => $not_found_message]),
      '#new_terms' => $settings['new_terms'] ?? FALSE,
      '#no_empty_message' => isset($settings['no_empty_message']) ? $this->t('@no_empty_message', ['@no_empty_message' => $settings['no_empty_message']]) : '',
    ];

    $multiple = $cardinality > 1 || $cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;

    // If new terms are allowed to be created, set the bundle and the uid of the
    // term.
    if ($this->getSetting('new_terms') && $this->getSelectionHandlerSetting('auto_create') && ($bundle = $this->getAutocreateBundle())) {
      $element['#autocreate'] = [
        'bundle' => $bundle,
        'uid' => ($entity instanceof EntityOwnerInterface) ? $entity->getOwnerId() : $this->account->id(),
      ];
    }

    $entities = [];
    foreach ($referenced_entities as $item) {
      $entities[$item->id()] = $item;
    }

    $selection_settings = $element['#selection_settings'] ?? [];
    $data = serialize($selection_settings) . $element['#target_type'] . $element['#selection_handler'];
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());

    $key_value_storage = $this->keyValue->get('entity_autocomplete');
    if (!$key_value_storage->has($selection_settings_key)) {
      $key_value_storage->set($selection_settings_key, $selection_settings);
    }

    $route_parameters = [
      'target_type' => $target_type,
      'selection_handler' => $element['#selection_handler'],
      'selection_settings_key' => $selection_settings_key,
    ];

    $element += [
      '#multiple' => $multiple,
      '#autocomplete_deluxe_path' => Url::fromRoute('autocomplete_deluxe.autocomplete', $route_parameters, ['absolute' => TRUE])->getInternalPath(),
      '#default_value' => self::implodeEntities($entities),
    ];

    return ['target_id' => $element];
  }

  /**
   * Implodes the tags from the taxonomy module.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   List of entities.
   * @param string $bundle
   *   Bundle name.
   *
   * @return array
   *   Imploded list of entity labels.
   */
  public static function implodeEntities(array $entities, $bundle = NULL) {
    $typed_entities = [];
    foreach ($entities as $entity) {
      $label = $entity->label();

      // Extract entities belonging to the bundle in question.
      if (!isset($bundle) || $entity->bundle() == $bundle) {
        // Make sure we have a completed loaded entity.
        if ($entity && $label) {
          // Commas and quotes in tag names are special cases, so encode 'em.
          if (strpos($label, ',') !== FALSE || strpos($label, '"') !== FALSE) {
            $label = '"' . str_replace('"', '""', $label) . '"';
          }

          $typed_entities[] = $label;
        }
      }
    }

    return implode(',', $typed_entities);
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    return $values['target_id'];
  }

  /**
   * Form element validation handler for integer textfields.
   */
  public static function validateInteger(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = $element['#value'];
    if ($value !== '' && (!is_numeric($value) || intval($value) != $value)) {
      $form_state->setError($element, \Drupal::translation()->translate('%name must be an integer.', ['%name' => $element['#title']]));
    }
  }

  /**
   * Form element validation handler for positive integer textfields.
   */
  public static function validateIntegerPositive(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = $element['#value'];
    if ($value !== '' && (!is_numeric($value) || intval($value) != $value || $value <= 0)) {
      $form_state->setError($element, \Drupal::translation()->translate('%name must be a positive integer.', ['%name' => $element['#title']]));
    }
  }

  /**
   * Returns the name of the bundle which will be used for autocreated entities.
   *
   * @return string
   *   The bundle name.
   */
  protected function getAutocreateBundle() {
    $bundle = NULL;

    if ($this->getSelectionHandlerSetting('auto_create') && $target_bundles = $this->getSelectionHandlerSetting('target_bundles')) {
      // If there's only one target bundle, use it.
      if (count($target_bundles) == 1) {
        $bundle = reset($target_bundles);
      }
      // Otherwise use the target bundle stored in selection handler settings.
      elseif (!$bundle = $this->getSelectionHandlerSetting('auto_create_bundle')) {
        // If no bundle has been set as auto create target means that there is
        // an inconsistency in entity reference field settings.
        trigger_error(sprintf(
          "The 'Create referenced entities if they don't already exist' option is enabled but a specific destination bundle is not set. You should re-visit and fix the settings of the '%s' (%s) field.",
          $this->fieldDefinition->getLabel(),
          $this->fieldDefinition->getName()
        ), E_USER_WARNING);
      }
    }

    return $bundle;
  }

  /**
   * Returns the value of a setting for the entity reference selection handler.
   *
   * @param string $setting_name
   *   The setting name.
   *
   * @return mixed
   *   The setting value.
   */
  protected function getSelectionHandlerSetting($setting_name) {
    $settings = $this->getFieldSetting('handler_settings');
    return $settings[$setting_name] ?? NULL;
  }

  /**
   * Returns the options for the match operator.
   *
   * @return array
   *   List of options.
   */
  protected function getMatchOperatorOptions() {
    return [
      'STARTS_WITH' => $this->t('Starts with'),
      'CONTAINS' => $this->t('Contains'),
    ];
  }

}
