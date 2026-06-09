<?php

namespace Drupal\field_widget_actions\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field_widget_actions\FieldWidgetActionManagerInterface;
use Drupal\field_widget_actions\FieldWidgetFormActionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Wrapper for the Field Widget Action form.
 */
class FieldWidgetActionFormWrapper extends FormBase {

  /**
   * The actual plugin instance.
   *
   * @var \Drupal\field_widget_actions\FieldWidgetFormActionInterface|null
   */
  protected $plugin;

  /**
   * Constructs the wrapper.
   *
   * @param \Drupal\field_widget_actions\FieldWidgetActionManagerInterface $manager
   *   The field widget action manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   */
  public function __construct(
    protected FieldWidgetActionManagerInterface $manager,
    protected FormBuilderInterface $formBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.field_widget_actions'),
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_widget_action_wrapper_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $plugin_id = NULL, $context_data = []) {
    if (!$plugin_id) {
      return ['#markup' => $this->t('No plugin ID specified.')];
    }

    // Maybe reload context data.
    if (empty($context_data) && $form_state->has('field_widget_action_context_data')) {
      $context_data = $form_state->get('field_widget_action_context_data');
    }

    // When this is on the modal form submission route, we need to verify
    // access to update the entity to avoid any Field Widget Action revealing
    // any sensitive information about the entity. We bail on building the form
    // via the plugin if 'update' is not allowed.
    if ($this->getRouteMatch()->getRouteName() === 'field_widget_actions.modal_form_action') {
      if (!isset($context_data['current_entity']) || !$context_data['current_entity'] instanceof EntityInterface) {
        return ['#markup' => $this->t('No entity specified.')];
      }

      if ($context_data['current_entity']->access('update') !== TRUE) {
        return ['#markup' => $this->t('Access denied.')];
      }
    }

    // Instantiate the plugin.
    /** @var \Drupal\field_widget_actions\FieldWidgetFormActionInterface $plugin */
    $this->plugin = $this->manager->createInstance($plugin_id);
    $form_state->set('field_widget_action_plugin', $this->plugin);
    $form_state->set('field_widget_action_context_data', $context_data);

    // Build the form, ensuring the submit button calls the callback here.
    $form = $this->plugin->buildForm($form, $form_state);
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#ajax']['callback'] = '::submitModalFormAjax';
    }

    // If the field widget action form is submitted already, run validation and
    // potentially run the submission. This is where we essentially swap
    // form processing over from the entity form to the field widget action
    // form temporarily.
    $user_input = $form_state->getUserInput();
    if (!empty($user_input['form_id']) && $user_input['form_id'] === 'field_widget_action_wrapper_form') {

      // Set the form state to be the user input from the modal.
      $form_state = $form_state->setFormState([
        'values' => $user_input,
      ]);

      // Now run the Field Widget Action form validation and submission.
      $this->validateForm($form, $form_state);
      if (!$form_state->getErrors()) {
        return $this->submitModalFormAjax($form, $form_state);
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $plugin = $this->getPluginInstance($form_state);
    if ($plugin) {
      $plugin->validateForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $plugin = $this->getPluginInstance($form_state);
    if ($plugin) {
      $plugin->submitForm($form, $form_state);
    }
  }

  /**
   * The AJAX Callback.
   *
   * This runs AFTER validation and submission are complete.
   * At this point, $form_state->getValues() is fully populated.
   */
  public function submitModalFormAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $plugin = $this->getPluginInstance($form_state);
    if ($plugin) {
      // Delegate the response generation to the plugin.
      return $plugin->submitModalFormAjax($form, $form_state);
    }

    $response = new AjaxResponse();
    $response->addCommand(new MessageCommand('There was a problem loading the Field Widget Action Plugin.', NULL, ['type' => 'error']));
    return $response;
  }

  /**
   * Get the Field Widget Action form based plugin instance.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\field_widget_actions\FieldWidgetFormActionInterface
   *   The plugin instance.
   */
  protected function getPluginInstance(FormStateInterface $form_state): FieldWidgetFormActionInterface {
    $plugin = $form_state->get('field_widget_action_plugin');
    if (!$plugin instanceof FieldWidgetFormActionInterface) {
      $plugin_id = $form_state->getBuildInfo()['args'][0] ?? NULL;
      if ($plugin_id) {
        $plugin = $this->manager->createInstance($plugin_id);
      }
    }
    return $plugin;
  }

}
