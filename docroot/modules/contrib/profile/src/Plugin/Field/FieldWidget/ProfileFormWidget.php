<?php

namespace Drupal\profile\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'profile_form' widget.
 *
 * @FieldWidget(
 *   id = "profile_form",
 *   label = @Translation("Profile form"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ProfileFormWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'form_mode' => 'default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form_modes = $this->entityDisplayRepository->getFormModeOptions($this->getFieldSetting('target_type'));
    $element['form_mode'] = [
      '#type' => 'select',
      '#options' => $form_modes,
      '#title' => $this->t('Form mode'),
      '#default_value' => $this->getSetting('form_mode'),
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $form_modes = $this->entityDisplayRepository->getFormModeOptions($this->getFieldSetting('target_type'));
    $form_mode = $this->getSetting('form_mode');
    $form_mode = $form_modes[$form_mode] ?? $form_mode;
    $summary = [];
    $summary[] = $this->t('Form mode: @mode', ['@mode' => $form_mode]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    // Do not allow this widget to be used as a default value widget.
    if ($this->isDefaultValueWidget($form_state)) {
      return [];
    }

    return parent::form($items, $form, $form_state, $get_delta);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\user\UserInterface $account */
    $account = $items->getEntity();
    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->entityTypeManager->getStorage('profile');
    $profile_type_storage = $this->entityTypeManager->getStorage('profile_type');
    /** @var \Drupal\profile\Entity\ProfileTypeInterface $profile_type */
    $profile_type = $profile_type_storage->load($this->getFieldSetting('profile_type'));
    $property = ['profiles', $profile_type->id(), $delta];
    $profile = $form_state->get($property);
    if (!$profile) {
      if (!$account->isAnonymous() && !$account->isNew() && ($profile_id = $items->get($delta)->target_id)) {
        $profile = $profile_storage->load($profile_id);
      }
      if (!$profile) {
        $values = [
          'type' => $profile_type->id(),
        ];
        if (!$account->isAnonymous() && !$account->isNew()) {
          $values['uid'] = $account->id();
        }
        $profile = $profile_storage->create($values);
      }
      $form_state->set($property, $profile);
    }
    // Adding/editing profiles for existing users needs to respect access.
    if (!$account->isNew()) {
      $access_control_handler = $this->entityTypeManager->getAccessControlHandler('profile');
      if ($profile->isNew()) {
        $access = $access_control_handler->createAccess($profile_type->id(), NULL, [
          'profile_owner' => $account,
        ]);
      }
      else {
        $access = $access_control_handler->access($profile, 'update');
      }

      if (!$access) {
        $element['#access'] = FALSE;
        return $element;
      }
    }

    $element = [
      '#type' => 'details',
      '#description' => '',
      '#open' => TRUE,
      // Remove the "required" clue, it's display-only and confusing.
      '#required' => FALSE,
      '#field_title' => $profile_type->getDisplayLabel() ?: $profile_type->label(),
      '#after_build' => [
        [get_class($this), 'removeTranslatabilityClue'],
      ],
    ] + $element;

    $form_mode = $this->getSetting('form_mode');
    $element['entity'] = [
      '#parents' => array_merge($element['#field_parents'], [
        $items->getName(), $delta, 'entity',
      ]),
      '#bundle' => $profile->bundle(),
      '#delta' => $delta,
      '#element_validate' => [
        [get_class($this), 'validateProfileForm'],
      ],
      '#form_mode' => $form_mode,
    ];

    if (function_exists('field_group_attach_groups')) {
      $context = [
        'entity_type' => $profile->getEntityTypeId(),
        'bundle' => $profile->bundle(),
        'entity' => $profile,
        'context' => 'form',
        'display_context' => 'form',
        'mode' => $form_mode,
      ];
      field_group_attach_groups($element['entity'], $context);
      $element['entity']['#process'][] = 'field_group_form_process';
    }

    $form_display = EntityFormDisplay::collectRenderDisplay($profile, $form_mode);
    $form_display->removeComponent('revision_log_message');
    $form_display->buildForm($profile, $element['entity'], $form_state);

    $form_process_callback = [get_class($this), 'attachSubmit'];
    // Make sure the #process callback doesn't get added more than once
    // if the widget is used on multiple fields.
    if (!isset($form['#process']) || !in_array($form_process_callback, $form['#process'])) {
      $form['#process'][] = [get_class($this), 'attachSubmit'];
    }

    return $element;
  }

  /**
   * After-build callback for removing the translatability clue from the widget.
   *
   * @see ContentTranslationHandler::addTranslatabilityClue()
   */
  public static function removeTranslatabilityClue(array $element, FormStateInterface $form_state) {
    $element['#title'] = $element['#field_title'];
    return $element;
  }

  /**
   * Process callback: Adds the widget's submit handler.
   */
  public static function attachSubmit(array $form, FormStateInterface $form_state) {
    // Ensure that our save function runs in between ::submitForm which
    // generates the entity and ::save which stores the user.
    if (isset($form['actions']['submit']['#submit']) && is_array($form['actions']['submit']['#submit'])) {
      $submitFormIndex = array_search('::submitForm', $form['actions']['submit']['#submit'], TRUE);
      if ($submitFormIndex !== -1) {
        $form['actions']['submit']['#submit'] = array_merge(
          array_slice($form['actions']['submit']['#submit'], 0, $submitFormIndex + 1),
          [[static::class, 'saveProfiles']],
          array_slice($form['actions']['submit']['#submit'], $submitFormIndex + 1),
        );
      }
      else {
        $form['actions']['submit']['#submit'][] = [static::class, 'saveProfiles'];
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    if ($this->isDefaultValueWidget($form_state)) {
      $items->filterEmptyItems();
      return;
    }
    $property = ['profiles', $this->getFieldSetting('profile_type')];
    $profiles = $form_state->get($property);
    if (!empty($profiles)) {
      $values = array_map(fn($profile) => ['entity' => $profile], $profiles);
      $items->setValue($values);
      $items->filterEmptyItems();
    }
  }

  /**
   * Validates the profile form.
   *
   * @param array $element
   *   The profile form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateProfileForm(array &$element, FormStateInterface $form_state) {
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $property = ['profiles', $element['#bundle'], $element['#delta']];
    $profile = $form_state->get($property);
    if (!empty($profile)) {
      assert($profile instanceof ProfileInterface);
      $form_display = EntityFormDisplay::collectRenderDisplay($profile, $element['#form_mode']);
      $form_display->extractFormValues($profile, $element, $form_state);
      $form_display->validateFormValues($profile, $element, $form_state);
    }
  }

  /**
   * Completes and saves all profiles.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function saveProfiles(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\user\UserInterface $account */
    $account = $form_state->getFormObject()->getEntity();
    if (!$account) {
      return;
    }
    $profiles = $form_state->get('profiles');
    /** @var \Drupal\profile\ProfileUserSyncer $profile_user_syncer */
    $profile_user_syncer = \Drupal::service('profile.user_syncer');
    foreach ($profiles as $profile_list) {
      foreach ($profile_list as $profile) {
        assert($profile instanceof ProfileInterface);
        $profile_user_syncer->saveProfile($account, $profile);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    return $entity_type == 'user' && $field_definition->getSetting('target_type') == 'profile' && $field_definition->isComputed();
  }

}
