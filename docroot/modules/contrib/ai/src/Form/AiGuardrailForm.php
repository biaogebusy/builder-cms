<?php

declare(strict_types=1);

namespace Drupal\ai\Form;

use Drupal\ai\Guardrail\AiGuardrailPluginManager;
use Drupal\ai\Utility\Textarea;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\Entity\AiGuardrail;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Guardrail form.
 */
final class AiGuardrailForm extends EntityForm {

  use AutowireTrait;

  /**
   * AiGuardrailForm constructor.
   *
   * @param \Drupal\ai\Guardrail\AiGuardrailPluginManager $aiGuardrailPluginManager
   *   The guardrail plugin manager.
   */
  public function __construct(
    protected AiGuardrailPluginManager $aiGuardrailPluginManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\ai\Entity\AiGuardrail $guardrail */
    $guardrail = $this->entity;
    $plugin = $guardrail->getGuardrail();
    if ($plugin !== NULL) {
      $configuration = $guardrail->get('guardrail_settings') ?: [];
      $plugin->setConfiguration($configuration);
      $form_state->setValue('guardrail_settings', []);
      $form_state->getUserInput()['guardrail_settings'] = [];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\ai\Entity\AiGuardrail $guardrail */
    $guardrail = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $guardrail->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $guardrail->id(),
      '#machine_name' => [
        'exists' => [AiGuardrail::class, 'load'],
      ],
      '#disabled' => !$guardrail->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $guardrail->get('description'),
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    $form['guardrail_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'guardrail-wrapper'],
    ];

    $form['guardrail_wrapper']['guardrail'] = [
      '#type' => 'select',
      '#title' => $this->t('Guardrail'),
      '#options' => $this->aiGuardrailPluginManager->getOptions(),
      '#default_value' => $guardrail->getGuardrail()?->getPluginId(),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'ajaxUpdateSettings'],
        'event' => 'change',
        'wrapper' => 'guardrail-wrapper',
      ],
      '#disabled' => !$guardrail->isNew(),
    ];

    $form['guardrail_wrapper']['guardrail_settings'] = [
      '#type' => 'container',
      '#title' => $this->t('Guardrail settings'),
      '#title_display' => FALSE,
      '#tree' => TRUE,
    ];

    if ($guardrail->getGuardrail() instanceof PluginFormInterface) {
      $plugin_form_state = $this->createPluginFormState($form_state);
      $form['guardrail_wrapper']['guardrail_settings'] += $guardrail
        ->getGuardrail()
        ->buildConfigurationForm([], $plugin_form_state);

      // Save the plugin form state values to the form state.
      $form_state->setValue(
        'guardrail_settings',
        $plugin_form_state->getValues()
      );
    }

    return $form;
  }

  /**
   * Ajax callback to update the guardrail settings form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated guardrail settings form.
   */
  public function ajaxUpdateSettings(
    array &$form,
    FormStateInterface $form_state,
  ): array {
    return $form['guardrail_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    parent::validateForm($form, $form_state);

    $guardrail_id = $form_state->getValue('guardrail');
    if ($guardrail_id) {
      $guardrail = $this->aiGuardrailPluginManager->createInstance($guardrail_id);
      if ($guardrail instanceof PluginFormInterface) {
        $plugin_form_state = $this->createPluginFormState($form_state);
        $guardrail->validateConfigurationForm($form, $plugin_form_state);
        // Copy errors back from the cloned plugin form state to the main state.
        foreach ($plugin_form_state->getErrors() as $name => $error) {
          $form_state->setErrorByName($name, $error);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $guardrail_id = $form_state->getValue('guardrail');
    $guardrail = $this->aiGuardrailPluginManager->createInstance($guardrail_id);
    if (!$guardrail) {
      $form_state->setErrorByName(
        'guardrail',
        $this->t('The selected guardrail is not valid.')
      );
    }
    $this->entity->set('guardrail', $guardrail->getPluginId());

    if ($guardrail instanceof PluginFormInterface) {
      $plugin_form_state = $this->createPluginFormState($form_state);
      $guardrail->submitConfigurationForm($form, $plugin_form_state);
      if ($guardrail instanceof ConfigurableInterface) {
        $this->entity->set('guardrail_settings', $guardrail->getConfiguration());
      }
    }
    else {
      $this->entity->set('guardrail_settings', []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match ($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

  /**
   * Creates a plugin form state for the guardrail settings.
   *
   * This method clones the original form state and clears the values,
   * except for the settings specific to this guardrail plugin.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The original form state.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The cloned and modified form state.
   */
  protected function createPluginFormState(
    FormStateInterface $form_state,
  ): FormStateInterface {
    // Clone the form state.
    $plugin_form_state = clone $form_state;

    // Clear the values, except for this plugin type's settings.
    $plugin_form_state->setValues(
      $form_state->getValue('guardrail_settings', [])
    );

    return $plugin_form_state;
  }

}
