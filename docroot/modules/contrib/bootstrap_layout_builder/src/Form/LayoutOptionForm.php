<?php

namespace Drupal\bootstrap_layout_builder\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;

/**
 * Form handler for the layout option entity forms.
 *
 * @internal
 */
class LayoutOptionForm extends EntityForm implements ContainerInjectionInterface {

  /**
   * The access manager service.
   *
   * @var \\Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a BootstrapLayoutBuilderBreakpointsForm object.
   *
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(CurrentRouteMatch $route_match, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\bootstrap_layout_builder\LayoutOptionInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\bootstrap_layout_builder\LayoutInterface $layout */
    $option = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $option->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $option->id(),
      '#machine_name' => [
        'exists' => '\Drupal\bootstrap_layout_builder\Entity\LayoutOption::load',
      ],
      '#disabled' => !$option->isNew(),
    ];

    $form['structure'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Structure'),
      '#maxlength' => 255,
      '#default_value' => $option->getStructure() ?: '',
      '#description' => $this->t('Add numbers seperated by space; if the number of columns at this layout is two and you are using bootstrap 12 Grid system<br/> this field must be two numbers at the sum of them sould equal 12. eg: <b>6 6</b> or <b>8 4</b> ...etc.'),
      '#required' => TRUE,
    ];

    $breakpoints = [];
    $default_breakpoints = [];
    $blb_breakpoint = $this->entityTypeManager->getStorage('blb_breakpoint')->getQuery()->accessCheck(TRUE)->sort('weight', 'ASC')->execute();
    foreach ($blb_breakpoint as $breakpoint_id) {
      $breakpoint_entity = $this->entityTypeManager->getStorage('blb_breakpoint')->load($breakpoint_id);
      $breakpoints[$breakpoint_id] = $breakpoint_entity->label();
      if (array_search($breakpoint_id, $option->getBreakpointsIds()) !== FALSE) {
        $default_breakpoints[$breakpoint_id] = $breakpoint_entity->label();
      }
    }

    $form['breakpoints'] = [
      '#title' => $this->t('Breakpoints'),
      '#type' => 'checkboxes',
      '#description' => $this->t('Select which breakpoints uses this layout option'),
      '#options' => $breakpoints,
      '#default_value' => $option->getBreakpointsIds() ?: [],
      '#ajax' => [
        'callback' => '::replaceDefaultBreakpointsOptions',
        'wrapper' => 'default-breakpoints-wrapper',
        'method' => 'replace',
      ],
    ];

    $form['default_breakpoints'] = [
      '#title' => $this->t('Default layout option for'),
      '#type' => 'checkboxes',
      '#description' => $this->t('Select the breakpoints if you want to make this layout option the default option for them.
        Note: if the breakpoint already selected as the default option for another layout option, this selection will override it.'),
      '#options' => $default_breakpoints,
      '#default_value' => $option->getDefaultBreakpointsIds() ?: [],
      '#prefix' => '<div id="default-breakpoints-wrapper">',
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * AJAX callback to update the default breakpoints element when the selection
   * changes.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The default breakpoints element render array.
   */
  public function replaceDefaultBreakpointsOptions(array $form, FormStateInterface $form_state) {
    return $form['default_breakpoints'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $layout_id = $this->routeMatch->getParameter('blb_layout');
    if ($layout_id) {
      $layout = $this->entity->getLayoutById($layout_id);
    }
    else {
      $layout = $this->entity->getLayout();
    }

    $structure = $form_state->getValue('structure');
    $structure = explode(' ', $structure);
    $invalid_structure = FALSE;
    // Make sure that all items are numbers.
    foreach ($structure as $col) {
      if (!is_numeric($col)) {
        $invalid_structure = TRUE;
        break;
      }
    }

    // Check the number of columns and the sum of the structure.
    if (
      array_sum($structure) != 12
    ) {
      $invalid_structure = TRUE;
    }

    if ($invalid_structure) {
      $form_state->setErrorByName(
        'structure',
        $this->t('Structure must be @cols numbers separated by space and the sum of these numbers must equal 12!', ['@cols' => $layout->getNumberOfColumns()])
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $layout_id = '';
    if ($this->routeMatch->getParameters()->has('blb_layout')) {
      $layout_id = $this->routeMatch->getParameter('blb_layout');
    }

    $entity = $this->entity;
    if ($layout_id) {
      $entity->setLayoutId($layout_id);
    }
    $status = $entity->save();

    if ($status == SAVED_UPDATED) {
      $layout_id = $entity->getLayoutId();
      $message = $this->t('The layout option @label has been updated.', ['@label' => $entity->label()]);
    }
    else {
      $message = $this->t('Added a layout option for @label.', ['@label' => $entity->label()]);
    }
    $this->messenger()->addStatus($message);
    $form_state->setRedirect(
      'entity.blb_layout.options_form',
      ['blb_layout' => $layout_id]
    );
  }

}
