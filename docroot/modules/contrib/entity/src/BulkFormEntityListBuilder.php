<?php

namespace Drupal\entity;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\system\ActionConfigEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list builder that allows using bulk actions.
 */
class BulkFormEntityListBuilder extends EntityListBuilder implements FormInterface {

  /**
   * The key to use for the form element containing the entities.
   *
   * @var string
   */
  protected $entitiesKey = 'entities';

  /**
   * The entities being listed.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities = [];

  /**
   * The bulk operations.
   *
   * @todo Change the typehint to ActionConfigEntityInterface when
   *   https://www.drupal.org/project/drupal/issues/3017214 is in.
   *
   * @var \Drupal\system\Entity\Action[]
   */
  protected $actions;

  /**
   * The action storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $actionStorage;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new BulkFormEntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $action_storage
   *   The action storage.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $entity_storage, EntityStorageInterface $action_storage, FormBuilderInterface $form_builder) {
    parent::__construct($entity_type, $entity_storage);

    $this->actionStorage = $action_storage;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')->getStorage('action'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->entityTypeId . '_list';
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Filter the actions to only include those for this entity type.
    $entity_type_id = $this->entityTypeId;
    $this->actions = array_filter($this->actionStorage->loadMultiple(), function (ActionConfigEntityInterface $action) use ($entity_type_id) {
      return $action->getType() == $entity_type_id;
    });
    $this->entities = $this->load();
    if ($this->entities) {
      return $this->formBuilder->getForm($this);
    }

    return parent::render();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form[$this->entitiesKey] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#empty' => $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]),
      '#tableselect' => TRUE,
      '#attached' => [
        'library' => ['core/drupal.tableselect'],
      ],
    ];

    $this->entities = $this->load();
    foreach ($this->entities as $entity) {
      $form[$this->entitiesKey][$entity->id()] = $this->buildRow($entity);
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply to selected items'),
      '#button_type' => 'primary',
    ];

    // Ensure a consistent container for filters/operations in the view header.
    $form['header'] = [
      '#type' => 'container',
      '#weight' => -100,
    ];

    $action_options = [];
    foreach ($this->actions as $id => $action) {
      $action_options[$id] = $action->label();
    }
    $form['header']['action'] = [
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#options' => $action_options,
    ];
    // Duplicate the form actions into the action container in the header.
    $form['header']['actions'] = $form['actions'];

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $form['pager'] = [
        '#type' => 'pager',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $selected = array_filter($form_state->getValue($this->entitiesKey));
    if (empty($selected)) {
      $form_state->setErrorByName($this->entitiesKey, $this->t('No items selected.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected = array_filter($form_state->getValue($this->entitiesKey));
    $entities = [];
    $action = $this->actions[$form_state->getValue('action')];
    $count = 0;

    foreach ($selected as $id) {
      $entity = $this->entities[$id];
      // Skip execution if the user did not have access.
      if (!$action->getPlugin()->access($entity)) {
        $this->messenger()->addError($this->t('No access to execute %action on the @entity_type_label %entity_label.', [
          '%action' => $action->label(),
          '@entity_type_label' => $entity->getEntityType()->getLabel(),
          '%entity_label' => $entity->label(),
        ]));
        continue;
      }

      $count++;
      $entities[$id] = $entity;
    }

    // Don't perform any action unless there are some elements affected.
    // @see https://www.drupal.org/project/drupal/issues/3018148
    if (!$count) {
      return;
    }

    $action->execute($entities);

    $operation_definition = $action->getPluginDefinition();
    if (!empty($operation_definition['confirm_form_route_name'])) {
      $options = [
        'query' => $this->getDestinationArray(),
      ];
      $form_state->setRedirect($operation_definition['confirm_form_route_name'], [], $options);
    }
    else {
      $this->messenger()->addStatus($this->formatPlural($count, '%action was applied to @count item.', '%action was applied to @count items.', [
        '%action' => $action->label(),
      ]));
    }
  }

}
