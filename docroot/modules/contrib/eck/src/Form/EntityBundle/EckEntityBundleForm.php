<?php

namespace Drupal\eck\Form\EntityBundle;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for ECK entity bundle forms.
 *
 * @ingroup eck
 */
class EckEntityBundleForm extends EntityForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, ConfigFactoryInterface $configFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $entity_type_id = $this->entity->getEntityType()->getBundleOf();
    $type = $this->entity;
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->create([
      'type' => $this->operation == 'add' ? $type->uuid() : $type->id(),
    ]
    );
    $type_label = $entity->getEntityType()->getLabel();

    $form['name'] = [
      '#title' => $this->t('Name'),
      '#type' => 'textfield',
      '#default_value' => $type->name,
      '#description' => $this->t(
        'The human-readable name of this entity bundle. This text will be displayed as part of the list on the <em>Add @type content</em> page. This name must be unique.',
        ['@type' => $type_label]),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['type'] = [
      '#type' => 'machine_name',
      '#default_value' => $type->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#disabled' => $type->isLocked(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['name'],
      ],
      '#description' => $this->t(
        'A unique machine-readable name for this entity type bundle. It must only contain lowercase letters, numbers, and underscores. This name will be used for constructing the URL of the Add %type content page, in which underscores will be converted into hyphens.',
        [
          '%type' => $type_label,
        ]
      ),
    ];

    $form['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#default_value' => $type->description,
      '#description' => $this->t(
        'Describe this entity type bundle. The text will be displayed on the <em>Add @type content</em> page.',
        ['@type' => $type_label]
      ),
    ];

    // Field title overrides.
    $entity_type_config = $this->configFactory->get('eck.eck_entity_type.' . $entity_type_id);

    $base_fields = $this->entityFieldManager->getBaseFieldDefinitions($type->getEntityType()->getBundleOf());
    $bundle_overrides = [];
    if ($type->id() !== NULL) {
      $bundle_overrides = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $type->id());
    }

    foreach (['title', 'uid', 'created', 'changed', 'status'] as $field) {
      if (!empty($entity_type_config->get($field))) {
        if (!isset($form['field_overrides'])) {
          $form['field_overrides'] = [
            '#type' => 'details',
            '#title' => $this->t('Base field title and description overrides'),
            '#open' => FALSE,
          ];
        }

        $form['field_overrides'][$field] = [
          '#type' => 'fieldset',
          '#title' => $base_fields[$field]->getLabel(),
          '#tree' => FALSE,
        ];

        $fieldset = &$form['field_overrides'][$field];

        if (isset($bundle_overrides[$field])) {
          $title_override = $bundle_overrides[$field]->getLabel();
          if ($title_override === $base_fields[$field]->getLabel()) {
            unset($title_override);
          }
        }

        $fieldset[$field . '_title_override'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Title'),
          '#description' => $this->t('New title for the base @title field.', ['@title' => $field]),
          '#default_value' => $title_override ?? '',
        ];

        if (isset($bundle_overrides[$field])) {
          $description_override = $bundle_overrides[$field]->getDescription();
          if ($description_override === $base_fields[$field]->getDescription()) {
            unset($description_override);
          }
        }

        $fieldset[$field . '_description_override'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Description'),
          '#description' => $this->t('New description for the base @title field. Enter %none to hide the default description.', [
            '@title' => $field,
            '%none' => '<none>',
          ]),
          '#default_value' => $description_override ?? '',
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save bundle');
    $actions['delete']['#value'] = $this->t('Delete bundle');

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $form_state) {
    $id = trim($form_state->getValue('type'));
    // '0' is invalid, since elsewhere we check it using empty().
    if ($id == '0') {
      $form_state->setErrorByName(
        'type',
        $this->t(
          "Invalid machine-readable name. Enter a name other than %invalid.",
          ['%invalid' => $id]
        )
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $type = $this->entity;
    $type->type = trim($type->id());
    $type->name = trim($type->name);

    $status = $type->save();

    $t_args = ['%name' => $type->label()];

    if ($status == SAVED_UPDATED) {
      $this->messenger()->addMessage($this->t('The entity bundle %name has been updated.', $t_args));
    }
    elseif ($status == SAVED_NEW) {
      $this->messenger()->addMessage($this->t('The entity bundle %name has been added.', $t_args));
      $context = array_merge(
        $t_args,
        [
          'link' => Link::fromTextAndUrl($this->t('View'), new Url('eck.entity.' . $type->getEntityType()
            ->getBundleOf() . '_type.list'))->toString(),
        ]
      );
      $this->logger($this->entity->getEntityTypeId())
        ->notice('Added entity bundle %name.', $context);
    }

    // Update field labels definition.
    $bundle_fields = $this->entityFieldManager->getFieldDefinitions($type->getEntityType()->getBundleOf(), $type->id());
    $base_fields = $this->entityFieldManager->getBaseFieldDefinitions($type->getEntityType()->getBundleOf());

    foreach (['created', 'changed', 'uid', 'title', 'status'] as $field) {
      if (!$form_state->hasValue($field . '_title_override')) {
        continue;
      }

      $has_changed = FALSE;
      $field_definition = $bundle_fields[$field];
      $field_config = $field_definition->getConfig($type->id());

      $label = $form_state->getValue($field . '_title_override') ?: $base_fields[$field]->getLabel();
      if ($field_definition->getLabel() != $label) {
        $field_config->setLabel($label);
        $has_changed = TRUE;
      }

      $description = $form_state->getValue($field . '_description_override') ?: $base_fields[$field]->getDescription();
      if ($field_definition->getDescription() != $description) {
        $field_config->setDescription($description);
        $has_changed = TRUE;
      }

      if ($has_changed) {
        $field_config->save();
      }
    }

    $this->entityFieldManager->clearCachedFieldDefinitions();

    $form_state->setRedirect(
      'eck.entity.' . $type->getEntityType()->getBundleOf() . '_type.list'
    );
    return $status;
  }

  /**
   * Checks for an existing ECK bundle.
   *
   * @param string $type
   *   The bundle type.
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE if this bundle already exists in the entity type, FALSE otherwise.
   */
  public function exists($type, array $element, FormStateInterface $form_state) {
    $bundleStorage = $this->entityTypeManager->getStorage($this->entity->getEckEntityTypeMachineName() . '_type');
    return (bool) $bundleStorage->load($type);
  }

}
