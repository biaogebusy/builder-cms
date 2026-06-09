<?php

namespace Drupal\ai\Form;

use Drupal\ai\Entity\AiPromptInterface;
use Drupal\ai\Entity\AiPromptTypeInterface;
use Drupal\ai\Service\AiPromptManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Form\EnforcedResponseException;

/**
 * Form handler for AI Prompt add and edit forms.
 */
class AiPromptForm extends EntityForm {

  /**
   * Constructs an AI Prompt Form object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ai\Service\AiPromptManager $promptManager
   *   The prompt manager.
   * @param \Drupal\ai\Form\AiPromptSubform $aiPromptSubform
   *   The shared subform.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    protected AiPromptManager $promptManager,
    protected AiPromptSubform $aiPromptSubform,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('ai.prompt_manager'),
      $container->get('ai.prompt_subform'),
    );
  }

  /**
   * Title callback for the Add AI prompt routes.
   *
   * @return string
   *   The title.
   */
  public function titleCallback(): string {
    $prompt_type_from_route = $this->getRouteMatch()->getParameter('ai_prompt_type');
    if ($prompt_type_from_route) {
      $prompt_type = $this->entityTypeManager
        ->getStorage('ai_prompt_type')
        ->load($prompt_type_from_route);
      if ($prompt_type instanceof AiPromptTypeInterface) {
        return $this->t('Add "@type" AI Prompt', [
          '@type' => $prompt_type->label(),
        ]);
      }
    }
    return $this->t('Add AI Prompt');
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $ai_prompt = $this->entity;
    assert($ai_prompt instanceof AiPromptInterface);

    $form = parent::form($form, $form_state);
    $type_options = $this->promptManager->getTypeOptions();

    // No types available, bail out.
    if (empty($type_options)) {
      return $this->noPromptTypesForm($form, $form_state);
    }
    $prompt_type_from_route = $this->getRouteMatch()->getParameter('ai_prompt_type');
    if ($prompt_type_from_route) {
      if (!isset($type_options[$prompt_type_from_route])) {
        $message = $this->t('The selected prompt type "@type" does not exist.', [
          '@type' => $prompt_type_from_route,
        ]);
        $this->messenger->addWarning($message);
        return $this->noPromptTypesForm($form, $form_state);
      }

      // Make type immediately available in the form state. This helps
      // determine which submit button to show.
      $form_state->setValue('type', $prompt_type_from_route);
      $form['type'] = [
        '#type' => 'hidden',
        '#value' => $prompt_type_from_route,
      ];
      $prompt_type = $this->entityTypeManager
        ->getStorage('ai_prompt_type')
        ->load($prompt_type_from_route);
    }
    else {

      // Type has not been selected, maybe force selection first.
      $form = $this->selectTypeForm($form, $form_state);
      if (!$form_state->getValue('type') && !$this->entity->bundle()) {
        if (count($form['type']['#options']) === 1) {

          // Choose for the user.
          $option_keys = array_keys($form['type']['#options']);
          $form['type']['#value'] = reset($option_keys);
        }
        else {

          // Make the user choose first.
          return $form;
        }
      }
      $form['type']['#disabled'] = TRUE;

      // Ensure we have a valid prompt type.
      $prompt_type_id = $form_state->getValue('type') ?? $ai_prompt->bundle();
      if (!empty($form['type']['#value']) && !$prompt_type_id) {
        $prompt_type_id = $form['type']['#value'];
      }
      $prompt_type = $this->entityTypeManager
        ->getStorage('ai_prompt_type')
        ->load($prompt_type_id);
      if (!$prompt_type instanceof AiPromptTypeInterface) {
        return $this->promptTypeNonExistentForm($form, $form_state);
      }
      if (!$ai_prompt->bundle()) {
        // Redirect to add form if only one prompt type.
        $type_options = $this->promptManager->getTypeOptions();
        if (count($type_options) === 1) {
          $only_type = array_key_first($type_options);
          $url = Url::fromRoute('entity.ai_prompt.add_type_form', ['ai_prompt_type' => $only_type])->toString();
          // Redirect to the add form for the only available prompt type.
          $response = new RedirectResponse($url);
          throw new EnforcedResponseException($response);
        }
      }
    }

    // No cache available for subform, or we get closure serialization errors.
    $form_state->disableCache();
    return $this->aiPromptSubform->buildForm($form, $form_state, $ai_prompt, $prompt_type);
  }

  /**
   * Show a form explaining how to create prompt types.
   *
   * The user is not yet able to proceed with this form. At least one prompt
   * type must exist first.
   *
   * @param array $form
   *   The original form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated form.
   */
  protected function selectTypeForm(array $form, FormStateInterface $form_state): array {
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Prompt Type'),
      '#options' => $this->promptManager->getTypeOptions(),
      '#required' => TRUE,
      '#default_value' => $this->entity->bundle(),
      '#empty_value' => '',
      '#empty_option' => $this->t('- Choose a Prompt Type -'),
      '#description' => $this->t('Different areas in the AI ecosystem expect different contexts via variables or tokens. The Prompt Type determines which variables and tokens should be available.'),
    ];

    // Filter the options to keep only allowed prompt types if we are coming
    // from the form element in context.
    // @todo Change to leverage https://www.drupal.org/project/drupal/issues/3555532
    // once it is resolved.
    $request = $this->getRequest();
    $prompt_types = $request->query->all('prompt_types');
    if (empty($prompt_types)) {
      $prompt_types = $request->request->all('prompt_types');
    }
    if (!empty($prompt_types)) {
      $form['type']['#options'] = array_intersect_key(
        $form['type']['#options'],
        array_flip($prompt_types)
      );
    }

    return $form;
  }

  /**
   * Show a form explaining how to create prompt types.
   *
   * The user is not yet able to proceed with this form. At least one prompt
   * type must exist first.
   *
   * @param array $form
   *   The original form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated form.
   */
  protected function noPromptTypesForm(array $form, FormStateInterface $form_state): array {
    $form['warning'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          $this->t('No AI Prompt Types exist yet. Before you can create an AI Prompt, you need to add at least one AI Prompt Type. These are normally provided by modules within the AI Ecosystem; however, you can also create your own for use in your own project implementations.'),
        ],
      ],
    ];

    $link_url = Url::fromRoute('entity.ai_prompt_type.add_form');
    $link_url->setOptions([
      'attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ]);
    $form['link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add AI Prompt Type'),
      '#url' => $link_url,
    ];

    return $form;
  }

  /**
   * Show a form explaining about a prompt type that no longer exists.
   *
   * Perhaps the type was deleted and this is an orphaned prompt.
   *
   * @param array $form
   *   The original form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated form.
   */
  protected function promptTypeNonExistentForm(array $form, FormStateInterface $form_state): array {
    $form['warning'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          $this->t('The selected AI Prompt Type no longer exists. This prompt can no longer be used.'),
        ],
      ],
    ];

    $link_url = Url::fromRoute('entity.ai_prompt_type.collection');
    $link_url->setOptions([
      'attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ]);
    $form['link'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage Prompt Types'),
      '#url' => $link_url,
    ];

    return $form;
  }

  /**
   * Save the type choice to redirect to the full form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitTypeChoice(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('type')) {
      $form_state->setRedirect('entity.ai_prompt.add_type_form', [
        'ai_prompt_type' => $form_state->getValue('type'),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $this->aiPromptSubform->validateForm($form, $form_state->getValues(), $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $ai_prompt = $this->entity;
    assert($ai_prompt instanceof AiPromptInterface);
    $status = $ai_prompt->save();
    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('The AI Prompt has been created.'));
    }
    else {
      $this->messenger()->addMessage($this->t('The AI Prompt has been updated.'));
    }

    $form_state->setRedirect('entity.ai_prompt.collection');
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {

    // No prompt types yet exist.
    if (empty($this->promptManager->getTypeOptions())) {
      return [];
    }

    $actions = parent::actions($form, $form_state);

    // If we are creating via the form element, we may have to select a prompt
    // type.
    if ($this->requestStack) {
      $prompt_types = $this->requestStack->getCurrentRequest()->query->get('prompt_types');
      if ($prompt_types && count($prompt_types) === 1) {
        $type = reset($prompt_types);
        $form_state->setValue('type', $type);
        return $actions;
      }
    }

    // No prompt selected yet, do not allow save, just submit.
    if (empty($form_state->getValue('type')) && empty($this->entity->bundle())) {
      $actions['submit']['#value'] = $this->t('Continue');
      $actions['submit']['#submit'] = ['::submitTypeChoice'];
    }

    return $actions;
  }

}
