<?php

namespace Drupal\state_machine\Plugin\Field\FieldFormatter;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\state_machine\Form\StateTransitionForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'state_transition_form' formatter.
 *
 * @FieldFormatter(
 *   id = "state_transition_form",
 *   label = @Translation("Transition form"),
 *   field_types = {
 *     "state",
 *   },
 * )
 */
class StateTransitionFormFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new StateTransitionFormFormatter object.
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
   *   Any third party settings.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ClassResolverInterface $class_resolver, FormBuilderInterface $form_builder, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->classResolver = $class_resolver;
    $this->formBuilder = $form_builder;
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('class_resolver'),
      $container->get('form_builder'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $items->getEntity();
    // Do not show the form if the user isn't allowed to modify the entity.
    if (!$entity->access('update')) {
      return [];
    }
    /** @var \Drupal\state_machine\Form\StateTransitionFormInterface $form_object */
    $form_object = $this->classResolver->getInstanceFromDefinition(StateTransitionForm::class);
    $form_object->setEntity($entity);
    $form_object->setFieldName($items->getFieldDefinition()->getName());
    $form_state_additions = [];
    if ($this->supportsConfirmationForm()) {
      $form_state_additions += [
        // Store in the form state whether a confirmation is required before
        // applying the state transition.
        'require_confirmation' => (bool) $this->getSetting('require_confirmation'),
        'use_modal' => (bool) $this->getSetting('use_modal'),
      ];
    }
    $form_state = (new FormState())->setFormState($form_state_additions);
    // $elements needs a value for each delta. State fields can't be multivalue,
    // so it's safe to hardcode 0.
    $elements = [];
    $elements[0] = $this->formBuilder->buildForm($form_object, $form_state);

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'require_confirmation' => FALSE,
      'use_modal' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $supports_confirmation_form = $this->supportsConfirmationForm();
    $form['require_confirmation'] = [
      '#title' => $this->t('Require confirmation before applying the state transition'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('require_confirmation'),
      // We can't support confirmation forms for state transition forms without
      // the "state-transition-form" link template.
      '#access' => $supports_confirmation_form,
    ];

    $form['use_modal'] = [
      '#title' => $this->t('Display confirmation in a modal dialog'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('use_modal'),
      '#states' => [
        'visible' => [
          ':input[name*="require_confirmation"]' => ['checked' => TRUE],
        ],
      ],
      '#access' => $supports_confirmation_form,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    if (!$this->supportsConfirmationForm()) {
      return $summary;
    }

    if ($this->getSetting('require_confirmation')) {
      $summary[] = $this->t('Require confirmation before applying the state transition.');

      if ($this->getSetting('use_modal')) {
        $summary[] = $this->t('Display confirmation in a modal dialog.');
      }
    }
    else {
      $summary[] = $this->t('Do not require confirmation before applying the state transition.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getType() == 'state';
  }

  /**
   * Gets whether the target entity type supports the confirmation form.
   *
   * @return bool
   *   Whether the target entity type supports the confirmation form.
   */
  protected function supportsConfirmationForm() {
    // If no "state-transition-form" link template is defined, we can't
    // support the confirmation form/modal for applying state transitions.
    $entity_type = $this->entityTypeManager->getDefinition($this->fieldDefinition->getTargetEntityTypeId());
    return $entity_type->hasLinkTemplate('state-transition-form');
  }

}
