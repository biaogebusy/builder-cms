<?php

declare(strict_types = 1);

namespace Drupal\sms\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\sms\Plugin\Field\FieldWidget\SmsTelephoneWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;

/**
 * Form controller for phone number settings.
 *
 * @property \Drupal\sms\Entity\PhoneNumberSettingsInterface $entity
 */
class PhoneNumberSettingsForm extends EntityForm {

  /**
   * Form field value used to indicate to create a new attached field.
   */
  const CREATE_NEW_FIELD = '!create';

  /**
   * Constructs a new PhoneNumberSettingsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected EntityFieldManagerInterface $entityFieldManager,
    MessengerInterface $messenger,
  ) {
    $this->setEntityTypeManager($entityTypeManager);
    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = &$this->entity;
    $form = parent::buildForm($form, $form_state);

    $bundles = [];
    $storage = $this->entityTypeManager
      ->getStorage('phone_number_settings');

    // Generate a list of field-able bundles.
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($entity_type->entityClassImplements(ContentEntityInterface::class)) {
        foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type->id()) as $bundle => $bundle_info) {
          // Do not show combinations with pre-existing phone number settings.
          // But, make sure all options are available on existing phone
          // number settings. They are hidden + read only from user anyway.
          if (!$storage->load($entity_type->id() . '.' . $bundle) || !$config->isNew()) {
            $bundles[(string) $entity_type->getLabel()][$entity_type->id() . '|' . $bundle] = $bundle_info['label'];
          }
        }
      }
    }

    $bundle_default_value = !$config->isNew() ? $config->getPhoneNumberEntityTypeId() . '|' . $config->getPhoneNumberBundle() : NULL;

    // Field cannot be called 'bundle' or odd behaviour will happen on re-saves.
    $form['entity_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Bundle'),
      '#options' => $bundles,
      '#default_value' => $bundle_default_value,
      '#required' => TRUE,
      '#access' => $config->isNew(),
      '#ajax' => [
        'callback' => '::updateFieldMapping',
        'wrapper' => 'edit-field-mapping-wrapper',
      ],
    ];

    if (!$bundles) {
      $form['entity_bundle']['#empty_option'] = $this->t('No Bundles Available');
    }

    $field_options = [];
    $field_options['telephone'][self::CREATE_NEW_FIELD] = $this->t('- Create a new telephone field -');
    $field_options['boolean'][self::CREATE_NEW_FIELD] = $this->t('- Create a new boolean field -');

    if ($entity_bundle = $form_state->getValue('entity_bundle', $bundle_default_value ?: NULL)) {
      [$entity_type_id, $bundle] = explode('|', $entity_bundle);
      if (!empty($entity_type_id) && !empty($bundle)) {
        $field_definitions = $this->entityFieldManager
          ->getFieldDefinitions($entity_type_id, $bundle);
        foreach ($field_definitions as $field_definition) {
          $field_type = $field_definition->getType();
          if (isset($field_options[$field_type])) {
            $field_options[$field_type][$field_definition->getName()] = $field_definition->getLabel();
          }
        }
      }
    }

    $form['field_mapping'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field mapping'),
      '#prefix' => '<div id="edit-field-mapping-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    // Form ID must be the same as found in $config->getFieldName().
    // ($config->getFieldName($config_key) ==
    // $form['field_mapping'][$config_key]).
    $form['field_mapping']['phone_number'] = [
      '#type' => 'select',
      '#title' => $this->t('Phone number'),
      '#description' => $this->t('Select the field storing phone numbers.'),
      '#options' => $field_options['telephone'],
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $config->getFieldName('phone_number'),
    ];
    $form['field_mapping']['automated_opt_out'] = [
      '#type' => 'select',
      '#title' => $this->t('Automated messages opt out'),
      '#description' => $this->t('Select the field storing preference to opt out of automated messages.'),
      '#options' => $field_options['boolean'],
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $config->getFieldName('automated_opt_out'),
    ];

    $form['message'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Messages'),
    ];
    $form['message']['verification_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Verification message'),
      '#description' => $this->t('SMS message sent to verify a phone number. The message should contain the verification code and a link to the verification page.'),
      '#default_value' => $config->isNew() ? "Your verification code is '[sms-message:verification-code]'.\nGo to [sms:verification-url] to verify your phone number.\n- [site:name]" : $config->getVerificationMessage(),
    ];

    $tokens = ['sms-message'];
    if ($this->moduleHandler->moduleExists('token')) {
      $form['message']['tokens']['list'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => $tokens,
      ];
    }
    else {
      foreach ($tokens as &$token) {
        $token = "[$token:*]";
      }
      $form['message']['tokens']['list'] = [
        '#markup' => $this->t('Available tokens include: @token_types', ['@token_types' => implode(' ', $tokens)]),
      ];
    }

    $form['expiration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Verification expiration'),
    ];
    $form['expiration']['code_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Verification code lifetime'),
      '#description' => $this->t('How long a verification code is valid, before it expires. Existing verification codes are retroactively updated.'),
      '#field_suffix' => $this->t('seconds'),
      '#required' => TRUE,
      '#min' => 60,
      '#default_value' => $config->isNew() ? 3600 : $config->getVerificationCodeLifetime(),
    ];

    $form['expiration']['phone_number_purge'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Purge phone numbers'),
      '#description' => $this->t('Remove phone number if verification code expires.'),
      '#default_value' => $config->isNew() ?: $config->getPurgeVerificationPhoneNumber(),
    ];

    return $form;
  }

  /**
   * Handles AJAX callback for bundle field on new phone number settings.
   */
  public function updateFieldMapping($form, FormStateInterface $form_state) {
    return $form['field_mapping'];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $config = &$this->entity;

    [$entity_type_id, $bundle] = explode('|', $form_state->getValue('entity_bundle'));
    $config
      ->setPhoneNumberEntityTypeId($entity_type_id)
      ->setPhoneNumberBundle($bundle);

    $config
      ->setVerificationMessage($form_state->getValue('verification_message'))
      ->setVerificationCodeLifetime($form_state->getValue('code_lifetime'))
      ->setPurgeVerificationPhoneNumber((bool) $form_state->getValue('phone_number_purge'));

    foreach ($form_state->getValue('field_mapping') as $config_key => $field_name) {
      if ($field_name == self::CREATE_NEW_FIELD) {
        $field_config = $this->createNewField($entity_type_id, $bundle, $config_key);
        $field_name = $field_config->getName();
      }
      else {
        // Use existing field.
        /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $entity_form_display */
        $entity_form_display = $field_storage_config = $this->entityTypeManager
          ->getStorage('entity_form_display')
          ->load($entity_type_id . '.' . $bundle . '.default');

        if ($entity_form_display) {
          $component = $entity_form_display->getComponent($field_name) ?: [];
          // Only change existing form formatter if it is using default
          // widget, or none at all.
          if (!$component || ($component && $component['type'] == 'telephone_default')) {
            $component['type'] = SmsTelephoneWidget::PLUGIN_ID;
            $entity_form_display
              ->setComponent($field_name, $component)
              ->save();
          }
        }
      }

      $config->setFieldName($config_key, $field_name);
    }

    $saved = $config->save();
    $t_args['%id'] = $config->id();

    if ($saved == SAVED_NEW) {
      $this->messenger()->addMessage($this->t('Phone number settings %id created.', $t_args));
    }
    else {
      $this->messenger()->addMessage($this->t('Phone number settings %id saved.', $t_args));
    }

    $form_state->setRedirectUrl(Url::fromRoute('sms.phone_number_settings.list'));
  }

  /**
   * Create a new field storage and field, and modify form display.
   *
   * @param string $entity_type_id
   *   An entity type ID.
   * @param string $bundle
   *   A bundle ID.
   * @param string $config_key
   *   A config ID as found in sms.phone.*.*.fields.$config_key.
   *
   * @return \Drupal\field\FieldConfigInterface
   *   A field config entity.
   */
  public static function createNewField($entity_type_id, $bundle, $config_key) {
    $entity_type_manager = \Drupal::entityTypeManager();

    // Definitions for field_storage_config.
    $definitions['field_storage']['phone_number']['type'] = 'telephone';
    $definitions['field_storage']['automated_opt_out']['type'] = 'boolean';
    // Definitions for field_config.
    $definitions['field']['phone_number']['label'] = \t('Phone number');
    $definitions['field']['automated_opt_out'] = [
      'label' => \t('Opt out of SMS messages.'),
      'description' => \t('SMS messages requested by you are exempt.'),
      'settings' => [
        'on_label' => \t('Do not receive SMS messages from this site.'),
        'off_label' => \t('Receive SMS messages from this site.'),
      ],
    ];
    // Definitions for form displays.
    $definitions['form_display']['phone_number']['type'] = SmsTelephoneWidget::PLUGIN_ID;
    $definitions['form_display']['automated_opt_out']['type'] = 'boolean_checkbox';

    // Generate a unique field name.
    $i = 1;
    $field_name = $config_key;
    while ($entity_type_manager->getStorage('field_storage_config')->load($entity_type_id . '.' . $field_name)) {
      $i++;
      $field_name = $config_key . '_' . $i;
    }

    /** @var \Drupal\field\FieldStorageConfigInterface $field_storage_config */
    $field_storage_config = $entity_type_manager
      ->getStorage('field_storage_config')
      ->create([
        'entity_type' => $entity_type_id,
        'field_name' => $field_name,
      ] + $definitions['field_storage'][$config_key]);
    $field_storage_config->save();

    $field_config = $entity_type_manager
      ->getStorage('field_config')
      ->create([
        'entity_type' => $entity_type_id,
        'bundle' => $bundle,
        'field_name' => $field_name,
      ] + $definitions['field'][$config_key]);
    $field_config->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $entity_form_display */
    $entity_form_display = $field_storage_config = $entity_type_manager
      ->getStorage('entity_form_display')
      ->load($entity_type_id . '.' . $bundle . '.default');
    // Only modify display if it already exists. Don't attempt to create it.
    if ($entity_form_display) {
      $entity_form_display->setComponent(
        $field_name,
        $definitions['form_display'][$config_key],
      );
      $entity_form_display->save();
    }

    return $field_config;
  }

}
