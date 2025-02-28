<?php

namespace Drupal\eck\Form\EntityBundle;

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
   * The constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
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
    $entity_type_config = \Drupal::config('eck.eck_entity_type.' . $entity_type_id);

    $base_fields = $this->entityFieldManager->getBaseFieldDefinitions($type->getEntityType()->getBundleOf());
    $bundle_fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $type->id());

    foreach (['title', 'uid', 'created', 'changed'] as $field) {
      if (!empty($entity_type_config->get($field))) {
        if (!isset($form['title_overrides'])) {
          $form['title_overrides'] = [
            '#type' => 'details',
            '#title' => $this->t('Base field title overrides'),
            '#open' => $type->isNew(),
          ];
        }

        if (($value = $bundle_fields[$field]->getLabel()) == $base_fields[$field]->getLabel()) {
          $value = '';
        }

        $form['title_overrides'][$field . '_title_override'] = [
          '#type' => 'textfield',
          '#title' => $base_fields[$field]->getLabel(),
          '#default_value' => $value,
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
    parent::validate($form, $form_state);

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
      \Drupal::messenger()->addMessage($this->t('The entity bundle %name has been updated.', $t_args));
    }
    elseif ($status == SAVED_NEW) {
      \Drupal::messenger()->addMessage($this->t('The entity bundle %name has been added.', $t_args));
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

    foreach (['created', 'changed', 'uid', 'title'] as $field) {
      if (!$form_state->hasValue($field . '_title_override')) {
        continue;
      }

      $label = $form_state->getValue($field . '_title_override') ?: $base_fields[$field]->getLabel();
      $field_definition = $bundle_fields[$field];
      if ($field_definition->getLabel() != $label) {
        $field_definition->getConfig($type->id())->setLabel($label)->save();
      }
    }

    $this->entityFieldManager->clearCachedFieldDefinitions();

    $form_state->setRedirect(
      'eck.entity.' . $type->getEntityType()->getBundleOf() . '_type.list'
    );
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
    $bundleStorage = \Drupal::entityTypeManager()->getStorage($this->entity->getEckEntityTypeMachineName() . '_type');
    return (bool) $bundleStorage->load($type);
  }

}
