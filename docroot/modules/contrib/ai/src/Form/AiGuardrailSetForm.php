<?php

declare(strict_types=1);

namespace Drupal\ai\Form;

use Drupal\ai\Entity\AiGuardrail;
use Drupal\ai\Guardrail\AiGuardrailEntityInterface;
use Drupal\ai\Utility\Textarea;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\Entity\AiGuardrailSet;
use Drupal\ai\Guardrail\AiGuardrailPluginManager;

/**
 * Guardrail set form.
 */
final class AiGuardrailSetForm extends EntityForm {

  use AutowireTrait;

  /**
   * AiGuardrailSetForm constructor.
   *
   * @param \Drupal\ai\Guardrail\AiGuardrailPluginManager $guardrail_plugin_manager
   *   The guardrail plugin manager.
   */
  public function __construct(
    protected AiGuardrailPluginManager $guardrail_plugin_manager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [AiGuardrailSet::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    $form['stop_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Stop Threshold'),
      '#description' => $this->t(
        'The score threshold above which the guardrail will stop processing. Value should be between 0 and 1.'
      ),
      '#default_value' => $this->entity->get('stop_threshold') ?? 0.8,
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
    ];

    // Before guardrails section.
    $form['pre_generate_guardrails'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Pre generate guardrails'),
      '#description' => $this->t(
        'Guardrails that will be executed before processing the AI request. Guardrails are executed in the order they are listed here.'
      ),
      '#tree' => TRUE,
    ];

    $this->buildGuardrailSection(
      $form,
      $form_state,
      'pre_generate_guardrails'
    );

    // After guardrails section.
    $form['post_generate_guardrails'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Post generate Guardrails'),
      '#description' => $this->t(
        'Guardrails that will be executed after receiving the AI response. Guardrails are executed in the order they are listed here.'
      ),
      '#tree' => TRUE,
    ];

    $this->buildGuardrailSection(
      $form,
      $form_state,
      'post_generate_guardrails'
    );

    return $form;
  }

  /**
   * Builds a guardrail configuration section with dynamic plugin addition.
   */
  protected function buildGuardrailSection(
    array &$form,
    FormStateInterface $form_state,
    string $section,
  ): void {
    $options = AiGuardrail::loadMultiple();
    $plugin_options = ['' => $this->t('- Select -')] +
      array_map(function (AiGuardrailEntityInterface $guardrail) {
        return $guardrail->label();
      }, $options);

    $count = $form_state->get([__CLASS__, $section, 'count']);
    if ($count === NULL) {
      $existing = $this->entity->get($section)['plugin_id'] ?? [];
      $count = max(1, is_array($existing) ? count($existing) : 1);
      $form_state->set([__CLASS__, $section, 'count'], $count);
    }

    $form[$section]['guardrails'] = [
      '#type' => 'container',
      '#prefix' => '<div id="' . $section . '-guardrails-wrapper">',
      '#suffix' => '</div>',
    ];

    $default_values = $this->entity->get($section)['plugin_id'] ?? [];
    for ($i = 0; $i < $count; $i++) {
      $form[$section]['guardrails']['row_' . $i] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['guardrail-row', 'container-inline']],
        'plugin_id' => [
          '#type' => 'select',
          '#title' => $this->t('Guardrail'),
          '#options' => $plugin_options,
          '#default_value' => $default_values[$i] ?? '',
        ],
      ];

      // Show remove button only from the second instance onwards.
      if ($i > 0) {
        $form[$section]['guardrails']['row_' . $i]['remove'] = [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#name' => $section . '_remove_guardrail_' . $i,
          '#submit' => [[get_class($this), 'removeGuardrailSubmit']],
          '#ajax' => [
            'callback' => [get_class($this), 'addGuardrailAjax'],
            'wrapper' => $section . '-guardrails-wrapper',
          ],
          '#limit_validation_errors' => [],
          '#attributes' => ['class' => ['remove-guardrail-button']],
        ];
      }
    }

    $form[$section]['add_guardrail'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Guardrail'),
      '#submit' => [[get_class($this), 'addGuardrailSubmit']],
      '#ajax' => [
        'callback' => [get_class($this), 'addGuardrailAjax'],
        'wrapper' => $section . '-guardrails-wrapper',
      ],
      '#limit_validation_errors' => [],
      '#name' => $section . '_add_guardrail',
    ];
  }

  /**
   * AJAX callback for adding a guardrail plugin select.
   */
  public static function addGuardrailAjax(
    array &$form,
    FormStateInterface $form_state,
  ): array {
    $triggering_element = $form_state->getTriggeringElement();
    $name = $triggering_element['#name'];
    if (str_ends_with($name, '_add_guardrail')) {
      $section = str_replace('_add_guardrail', '', $name);
    }
    elseif (preg_match('/^(.*)_remove_guardrail_\\d+$/', $name, $matches)) {
      $section = $matches[1];
    }
    else {
      $section = '';
    }

    return $section && isset($form[$section]['guardrails']) ? $form[$section]['guardrails'] : [];
  }

  /**
   * Submit handler for adding a guardrail plugin select.
   */
  public static function addGuardrailSubmit(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $triggering_element = $form_state->getTriggeringElement();
    $section = str_replace('_add_guardrail', '', $triggering_element['#name']);
    $count = $form_state->get([__CLASS__, $section, 'count']) ?? 1;
    $form_state->set([__CLASS__, $section, 'count'], $count + 1);

    // Rebuild the form to show the new select.
    $form_state->setRebuild();
  }

  /**
   * Submit handler for removing a guardrail plugin select.
   */
  public static function removeGuardrailSubmit(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $triggering_element = $form_state->getTriggeringElement();
    $name = $triggering_element['#name'];
    if (preg_match('/^(.*)_remove_guardrail_(\d+)$/', $name, $matches)) {
      $section = $matches[1];
      $remove_index = (int) $matches[2];
      $count = $form_state->get([__CLASS__, $section, 'count']) ?? 1;
      if ($count > 1) {
        $count--;
        $form_state->set([__CLASS__, $section, 'count'], $count);
        $user_input = $form_state->getUserInput();
        if (isset($user_input[$section]['guardrails']['row_' . $remove_index])) {
          unset($user_input[$section]['guardrails']['row_' . $remove_index]);
          $form_state->setUserInput($user_input);
        }
      }
      $form_state->setRebuild();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    foreach ([
      'pre_generate_guardrails',
      'post_generate_guardrails',
    ] as $section) {
      $guardrails = $form_state->getValue([$section, 'guardrails']) ?? [];
      $plugin_ids = [];
      foreach ($guardrails as $row) {
        if (isset($row['plugin_id']) && $row['plugin_id'] !== '') {
          $plugin_ids[] = $row['plugin_id'];
        }
      }
      $this->entity->set($section, ['plugin_id' => $plugin_ids]);
    }
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match ($result) {
        \SAVED_NEW => $this->t(
          'Created new guardrail set %label.',
          $message_args
        ),
        \SAVED_UPDATED => $this->t(
          'Updated guardrail set %label.',
          $message_args
        ),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

}
