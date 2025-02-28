<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Form;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base form for a Step.
 *
 * @package Drupal\forms_steps\Form
 */
class FormsStepsStepFormBase extends EntityForm {

  /**
   * The ID of the step that is being edited.
   *
   * @var string
   */
  protected string $stepId;

  /**
   * EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EntityDisplayRepositoryInterface.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * EntityTypeBundleInfoInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * Class constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): FormsStepsStepFormBase {
    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $forms_steps_step = '') {
    $this->stepId = $forms_steps_step;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\forms_steps\Entity\FormsSteps $formsSteps */
    $formsSteps = $this->getEntity();

    $step = NULL;

    try {
      /** @var \Drupal\forms_steps\Step $entity */
      $step = $formsSteps->getStep($this->stepId);
    }
    catch (\InvalidArgumentException $ex) {
      // New step.
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) ? $step->label() : '',
      '#description' => $this->t('Label for the step.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
    ];

    // @todo Remove 'node.' concatenation with step entity bundle.
    // We retrieve the list of entities.
    $entitiesDefinition = $this->entityTypeManager->getDefinitions();
    $entitiesSelectList = [];
    $fieldableInterface = 'Drupal\Core\Entity\FieldableEntityInterface';

    foreach ($entitiesDefinition as $entityDefinition) {
      // EntityDisplay entities can only handle fieldable entity types.
      if (in_array($fieldableInterface, class_implements($entityDefinition->getOriginalClass()))) {
        $entitiesSelectList[$entityDefinition->id()] = $entityDefinition->getLabel();
      }
    }

    $selectedEntityType = $form_state->getValues()['target_entity_type'] ?? ($step ? $step->entityType() : 'node');
    $form['target_entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Type'),
      '#maxlength' => 255,
      '#default_value' => $selectedEntityType,
      '#description' => $this->t('Entity type (Node, User, ...)'),
      '#required' => TRUE,
      '#options' => $entitiesSelectList,
      '#ajax' => [
        'callback' => 'Drupal\forms_steps\Form\FormsStepsStepFormBase::updateFormModeCallback',
        'event' => 'change',
        'wrapper' => 'form-mode-container',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Updating entry...'),
        ],
      ],
    ];

    // We get the list of entity bundles.
    $entityBundles = $this->entityTypeBundleInfo->getBundleInfo($selectedEntityType);

    $entityBundlesSelectList = [];
    foreach ($entityBundles as $entityBundleKey => $entityBundleValue) {
      $entityBundlesSelectList[$entityBundleKey] = $entityBundleValue['label'];
    }

    $form['form_mode_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'form-mode-container',
      ],
    ];

    $selectedBundle =
      !empty($form_state->getValues()['target_entity_bundle']) && isset($entityBundlesSelectList[$form_state->getValues()['target_entity_bundle']])
        ? $form_state->getValues()['target_entity_bundle'] : (
        !is_null($step) && isset($entityBundlesSelectList[$step->entityBundle()]) ? $step->entityBundle() : key($entityBundlesSelectList)
      );

    $form['form_mode_container']['target_entity_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Bundle'),
      '#maxlength' => 255,
      '#description' => $this->t('Entity type of the form.'),
      '#required' => TRUE,
      '#default_value' => $selectedBundle,
      '#options' => $entityBundlesSelectList,
    ];

    // We get all the form mode for the selected Entity.
    $formModesSelectList = ['default' => $this->t('Default')];

    if (!is_null($selectedEntityType)) {
      $formModes = $this->entityDisplayRepository->getFormModes(
        $selectedEntityType
      );
      foreach ($formModes as $formModeDefinition) {
        $formModesSelectList[$formModeDefinition['id']] = $formModeDefinition['label'];
      }
    }

    $selectedFormMode = !empty($form_state->getValues()['target_form_mode']) ? $form_state->getValues()['target_form_mode'] : ($step ? $step->formMode() : key($formModesSelectList));

    $form['form_mode_container']['target_form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form mode'),
      '#maxlength' => 255,
      '#default_value' => $selectedFormMode,
      '#description' => $this->t('Form mode of the Content type.'),
      '#required' => TRUE,
      '#options' => $formModesSelectList,
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Step URL'),
      '#size' => 30,
      '#description' => $this->t('Form step URL.'),
      '#placeholder' => '/my_form/step1',
      '#default_value' => $step && $step->url() ? $step->url() : '',
      '#required' => TRUE,
    ];

    $form['submit_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Submit Button'),
      '#open' => FALSE,
    ];

    $form['submit_button']['override_submit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override submit label'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->submitLabel(),
      '#required' => FALSE,
    ];

    $form['submit_button']['submit_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Submit label'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->submitLabel() ? $step->submitLabel() : NULL,
      '#description' => $this->t('Label of the submit button.'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="override_submit"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['delete_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Delete Button'),
      '#open' => FALSE,
    ];

    $form['delete_button']['hide_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide delete button'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->hideDelete(),
      '#required' => FALSE,
    ];

    $form['delete_button']['override_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override delete label'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->deleteLabel(),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="hide_delete"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    $form['delete_button']['delete_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delete label'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->deleteLabel() ? $step->deleteLabel() : NULL,
      '#description' => $this->t('Label of the delete button.'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="override_delete"]' => [
            'checked' => TRUE,
          ],
          ':input[name="hide_delete"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    $form['cancel_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Cancel Button'),
      '#open' => FALSE,
    ];

    $form['cancel_button']['override_cancel'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override cancel label'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->cancelLabel(),
      '#required' => FALSE,
    ];

    $form['cancel_button']['cancel_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cancel label'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->cancelLabel() ? $step->cancelLabel() : NULL,
      '#description' => $this->t('Label of the cancel button.'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="override_cancel"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['cancel_button']['set_cancel_route'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set cancel route'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->cancelRoute(),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="set_cancel_step"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    $form['cancel_button']['cancel_route'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cancel route'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->cancelRoute() ? $step->cancelRoute() : NULL,
      '#description' => '',
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="set_cancel_route"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['cancel_button']['set_cancel_step'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set a step to go when cancel is clicked.'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->cancelStep(),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="set_cancel_route"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    $steps = $formsSteps->getSteps();
    $steps_options = [];
    /** @var \Drupal\forms_steps\Step $step */
    foreach ($steps as $_step) {
      $steps_options[$_step->id()] = $_step->label();
    }

    $form['cancel_button']['cancel_step'] = [
      '#type' => 'select',
      '#title' => $this->t('Step'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->cancelStep() ? $step->cancelStep()
        ->id() : NULL,
      '#required' => FALSE,
      '#options' => $steps_options,
      '#states' => [
        'visible' => [
          ':input[name="set_cancel_step"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['previous_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Previous Button'),
      '#open' => FALSE,
    ];

    $form['previous_button']['display_previous'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display previous button'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->displayPrevious(),
      '#required' => FALSE,
    ];

    $form['previous_button']['previous_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Previous label'),
      '#maxlength' => 255,
      '#default_value' => !is_null($step) && $step->previousLabel() ? $step->previousLabel() : NULL,
      '#description' => $this->t('Label of the previous button.'),
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="display_previous"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Copies top-level form values to entity properties.
   *
   * This form can only change values for a step, which is part of forms_steps.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the current form should operate upon.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    if ($form_state->isSubmitted()) {
      /** @var \Drupal\forms_steps\FormsStepsInterface $entity */
      $values = $form_state->getValues();

      if (!$entity->hasStep($values['id'])) {
        $entity->addStep(
          $values['id'], $values['label'], $values['target_entity_type'],
          $values['target_entity_bundle'], $values['target_form_mode'],
          $values['url']
        );
      }

      if (!empty($values['label'])) {
        $entity->setStepLabel($values['id'], $values['label']);
      }
      if (!empty($values['target_entity_type'])) {
        $entity->setStepEntityType(
          $values['id'],
          $values['target_entity_type']
        );
      }

      if (!empty($values['target_entity_bundle'])) {
        $entity->setStepEntityBundle($values['id'], $values['target_entity_bundle']);
      }

      if (!empty($values['target_form_mode'])) {
        $entity->setStepFormMode($values['id'], $values['target_form_mode']);
      }

      if (!empty($values['url'])) {
        $entity->setStepUrl($values['id'], $values['url']);
      }

      if ($values['override_submit'] == 1) {
        $entity->setStepSubmitLabel($values['id'], $values['submit_label']);
      }
      else {
        $entity->setStepSubmitLabel($values['id'], '');
      }

      if ($values['hide_delete'] == 1) {
        $entity->setStepDeleteState($values['id'], TRUE);
      }
      else {
        $entity->setStepDeleteState($values['id'], FALSE);

        if ($values['override_delete'] == 1) {
          $entity->setStepDeleteLabel($values['id'], $values['delete_label']);
        }
        else {
          $entity->setStepDeleteLabel($values['id'], NULL);
        }
      }

      if ($values['override_cancel'] == 1) {
        $entity->setStepCancelLabel($values['id'], $values['cancel_label']);

        if ($values['set_cancel_route'] == 1) {
          $entity->setStepCancelStep($values['id'], NULL);
          $entity->setStepCancelStepMode($values['id'], '');

          $entity->setStepCancelRoute($values['id'], $values['cancel_route']);
        }
        else {
          if ($values['set_cancel_step'] == 1) {
            $entity->setStepCancelRoute($values['id'], '');

            $entity->setStepCancelStep(
              $values['id'],
              $entity->getStep($values['cancel_step'])
            );
          }
        }
      }
      else {
        $entity->setStepCancelLabel($values['id'], '');
      }

      if ($values['display_previous'] == 1) {
        $entity->setStepPreviousState($values['id'], TRUE);
        $entity->setStepPreviousLabel($values['id'], $values['previous_label']);
      }
      else {
        $entity->setStepPreviousState($values['id'], FALSE);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->isSubmitted()) {
      parent::validateForm($form, $form_state);
      $values = $form_state->getValues();

      $entityFormDisplay =
        \Drupal::service('entity_display.repository')->getFormDisplay(
          $values['target_entity_type'],
          $values['target_entity_bundle'],
          preg_replace(
            "/^{$values['target_entity_type']}\./",
            '',
            $values['target_form_mode']
          )
        );

      if ($entityFormDisplay->isNew()) {
        $form_state->setErrorByName(
          'target_form_mode',
          $this->t('This form mode is not yet defined for the selected bundle.')
        );
      }
    }
  }

  /**
   * Determines if the forms steps step already exists.
   *
   * @param string $step_id
   *   The forms steps step ID.
   *
   * @return bool
   *   TRUE if the forms steps step exists, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function exists(string $step_id): bool {
    /** @var \Drupal\forms_steps\FormsStepsInterface $original_forms_steps */
    $original_forms_steps = $this->entityTypeManager
      ->getStorage('forms_steps')
      ->loadUnchanged($this->getEntity()->id());
    return $original_forms_steps->hasStep($step_id);
  }

  /**
   * Callback to return new nested select values.
   *
   * @param array $form
   *   The referenced form to use.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form_state.
   *
   * @return array
   *   Returns the new form_mode_container based on the change done in Ajax.
   */
  public static function updateFormModeCallback(array &$form, FormStateInterface $form_state) : array {
    $form_state->setRebuild();
    return $form['form_mode_container'];
  }

}
