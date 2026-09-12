<?php

declare(strict_types=1);

namespace Drupal\ai\Form;

use Drupal\ai\Guardrail\AiGuardrailPluginManager;
use Drupal\ai\Utility\Textarea;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
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
    $plugin = $this->entity->getGuardrail();
    // Guardrails are not required to be configurable, so only seed the
    // configuration on those that actually accept it. Calling this
    // unconditionally fatals the form for guardrails without settings.
    if ($plugin instanceof ConfigurableInterface) {
      $plugin->setConfiguration($this->entity->get('guardrail_settings') ?: []);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    // FormBuilder only assigns #parents to the root element in doBuildForm(),
    // which runs after this method. SubformState::getParents() requires it on
    // the parent form as well as on the subform, so any guardrail whose
    // buildConfigurationForm() reads from the form state would otherwise die
    // with "The subform and parent form must contain the #parents property".
    // The value is identical to the one doBuildForm() would default to.
    $form['#parents'] = [];

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

    // Set #parents explicitly so SubformState can scope user input correctly
    // before doBuildForm() has had a chance to populate it.
    $form['guardrail_wrapper']['guardrail_settings'] = [
      '#type' => 'container',
      '#title' => $this->t('Guardrail settings'),
      '#title_display' => FALSE,
      '#tree' => TRUE,
      '#parents' => ['guardrail_settings'],
    ];

    if ($guardrail->getGuardrail() instanceof PluginFormInterface) {
      $plugin_form_state = SubformState::createForSubform(
        $form['guardrail_wrapper']['guardrail_settings'],
        $form,
        $form_state,
      );
      $form['guardrail_wrapper']['guardrail_settings'] += $guardrail
        ->getGuardrail()
        ->buildConfigurationForm([], $plugin_form_state);
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
        $plugin_form_state = SubformState::createForSubform(
          $form['guardrail_wrapper']['guardrail_settings'],
          $form,
          $form_state,
        );
        $guardrail->validateConfigurationForm($form['guardrail_wrapper']['guardrail_settings'], $plugin_form_state);
        // Copy errors back from the subform state to the main state.
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
      $plugin_form_state = SubformState::createForSubform(
        $form['guardrail_wrapper']['guardrail_settings'],
        $form,
        $form_state,
      );
      $guardrail->submitConfigurationForm($form['guardrail_wrapper']['guardrail_settings'], $plugin_form_state);
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

}
