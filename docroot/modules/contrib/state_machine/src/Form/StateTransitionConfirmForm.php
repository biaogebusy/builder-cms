<?php

namespace Drupal\state_machine\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for performing an entity state transition.
 */
class StateTransitionConfirmForm extends ContentEntityConfirmFormBase {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The field name.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The transition.
   *
   * @var \Drupal\state_machine\Plugin\Workflow\WorkflowTransition
   */
  protected $transition;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return 'state_machine_transition_confirm_form';
  }

  /**
   * Returns the transition object.
   *
   * @return \Drupal\state_machine\Plugin\Workflow\WorkflowTransition
   *   The transition object.
   */
  public function getTransition() {
    return $this->transition;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $field_name = '', $transition_id = '') {
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $this->entity->get($field_name)->first();
    $transition = $state_item->getWorkflow()->getTransition($transition_id);
    $this->fieldName = $field_name;
    $this->transition = $transition;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $this->entity->get($this->fieldName)->first();
    $items = [
      $this->t('<b>Transition</b>: @transition_label', ['@transition_label' => $this->transition->getLabel()]),
      $this->t('<b>@entity_type</b>: @entity_label', ['@entity_type' => $this->entity->getEntityType()->getLabel(), '@entity_label' => $this->entity->label()]),
      $this->t('<b>From</b>: @from_state', ['@from_state' => $state_item->getOriginalLabel()]),
      $this->t('<b>To</b>: @to_state', ['@to_state' => $this->transition->getToState()]),
    ];
    $description = [
      'items' => [
        '#type' => 'html_tag',
        '#value' => implode('<br/>', $items),
        '#tag' => 'p',
      ],
      'warning' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => parent::getDescription(),
      ],
    ];

    return $this->renderer->renderPlain($description);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to apply this transition?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->toUrl('canonical');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface $state_item */
    $state_item = $this->entity->get($this->fieldName)->first();
    if ($state_item->isTransitionAllowed($this->transition->getId())) {
      $state_item->applyTransition($this->transition);
      $this->entity->save();
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
