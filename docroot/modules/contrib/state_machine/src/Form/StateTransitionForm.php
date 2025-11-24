<?php

namespace Drupal\state_machine\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StateTransitionForm extends FormBase implements StateTransitionFormInterface {

  /**
   * The redirect destination.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The state field name.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->redirectDestination = $container->get('redirect.destination');
    $instance->entityRepository = $container->get('entity.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(ContentEntityInterface $entity) {
    $this->entity = $this->entityRepository->getTranslationFromContext($entity);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName() {
    return $this->fieldName;
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldName($field_name) {
    $this->fieldName = $field_name;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return 'state_machine_transition_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    $entity = $this->getEntity();
    if (!$entity) {
      throw new \RuntimeException('No entity provided to StateTransitionForm.');
    }
    // Example ID: "state_machine_transition_form_commerce_order_state_1".
    $form_id = $this->getBaseFormId();
    $form_id .= '_' . $entity->getEntityTypeId() . '_' . $this->fieldName;
    $form_id .= '_' . $entity->id();

    return $form_id;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $this->entity->get($this->fieldName)->first();

    if (!isset($state_item)) {
      return;
    }

    $form['actions'] = [
      '#type' => 'container',
    ];
    // Determine whether we should output links to the confirmation form,
    // or submit buttons.
    $require_confirmation = $form_state->get('require_confirmation');
    foreach ($state_item->getTransitions() as $transition_id => $transition) {
      if (!$require_confirmation) {
        $form['actions'][$transition_id] = [
          '#type' => 'submit',
          '#value' => $transition->getLabel(),
          '#submit' => ['::submitForm'],
          '#transition' => $transition,
        ];
        continue;
      }
      $url = $this->entity->toUrl('state-transition-form');
      $route_parameters = $url->getRouteParameters() + [
        $this->entity->getEntityTypeId() => $this->entity->id(),
        'field_name' => $this->fieldName,
        'transition_id' => $transition_id,
      ];

      $form['actions'][$transition_id] = [
        '#type' => 'link',
        '#title' => $transition->getLabel(),
        '#url' => Url::fromRoute("entity.{$this->entity->getEntityTypeId()}.state_transition_form", $route_parameters, [
          'query' => $this->redirectDestination->getAsArray(),
        ]),
        '#attributes' => [
          'class' => [
            'button',
          ],
        ],
      ];

      if ($form_state->get('use_modal')) {
        $form['actions'][$transition_id]['#attributes']['class'][] = 'use-ajax';
        $form['actions'][$transition_id]['#attributes']['data-dialog-type'] = 'modal';
        $form['actions'][$transition_id]['#attributes']['data-dialog-options'] = Json::encode([
          'width' => 'auto',
        ]);
        $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    /** @var \Drupal\state_machine\Plugin\Workflow\WorkflowTransition $transition */
    $transition = $triggering_element['#transition'];
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $this->entity->get($this->fieldName)->first();

    // Ensure the transition is still allowed before applying it.
    if ($state_item->isTransitionAllowed($transition->getId())) {
      $state_item->applyTransition($triggering_element['#transition']);
      $this->entity->save();
    }
  }

}
