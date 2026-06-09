<?php

declare(strict_types=1);

namespace Drupal\ai\Form;

use Drupal\ai\Guardrail\AiGuardrailRepository;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure the guardrail sets that are applied to every AI request.
 *
 * @see https://www.drupal.org/project/ai/issues/3584851
 */
class GlobalGuardrailsSettingsForm extends ConfigFormBase {

  /**
   * Config name.
   */
  public const CONFIG_NAME = 'ai.settings';

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected readonly AiGuardrailRepository $guardrailRepository,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('Drupal\ai\Guardrail\AiGuardrailRepository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_global_guardrails_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $sets = $this->guardrailRepository->getAllGuardrailSets();
    $options = [];
    foreach ($sets as $id => $set) {
      $options[$id] = $set->label() ?? $id;
    }

    $default = $this->config(self::CONFIG_NAME)->get('global_guardrails') ?? [];

    if ($options === []) {
      $form['no_sets'] = [
        '#markup' => '<p>' . $this->t('No guardrail sets have been configured yet.') . '</p>',
      ];
      return parent::buildForm($form, $form_state);
    }

    // Sort.
    asort($options);

    // Fieldset to add title and description.
    $form['global_guardrails_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Global guardrail sets'),
      '#description' => $this->t('Guardrail sets selected here run on every AI request, even those that did not explicitly opt in.'),
      'global_guardrails' => [],
    ];

    // Global guardrails form part.
    $form_global_guardrails = &$form['global_guardrails_wrapper']['global_guardrails'];

    // Sortable table.
    $form_global_guardrails = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'item-weight',
        ],
      ],
    ];

    // Sorting options:
    // First, the checked ones by save order,
    // then the unchecked ones by name.
    uksort($options, function ($a, $b) use ($default) {
      if (!in_array($a, $default) && !in_array($b, $default)) {
        return 0;
      }
      elseif (!in_array($a, $default)) {
        return 1;
      }
      elseif (!in_array($b, $default)) {
        return -1;
      }
      else {
        return (array_search($a, $default) - array_search($b, $default)) < 0 ? -1 : 1;
      }
    });

    // Add form elements.
    foreach ($options as $id => $label) {
      $weight = 0;
      if (in_array($id, $default)) {
        $weight = ((int) array_search($id, $default)) - count($default);
      }

      $form_global_guardrails[$id]['#attributes']['class'][] = 'draggable';

      $form_global_guardrails[$id]['checkbox'] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#default_value' => in_array($id, $default),
      ];

      $form_global_guardrails[$id]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $label]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        '#attributes' => ['class' => ['item-weight']],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValue('global_guardrails') ?? [];
    $selected_items = [];

    foreach ($values as $id => $row) {
      if (!empty($row['checkbox'])) {
        $selected_items[$id] = (int) $row['weight'];
      }
    }
    asort($selected_items);

    $this->config(self::CONFIG_NAME)
      ->set('global_guardrails', array_keys($selected_items))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
