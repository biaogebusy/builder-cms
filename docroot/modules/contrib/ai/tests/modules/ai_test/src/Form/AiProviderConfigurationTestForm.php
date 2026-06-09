<?php

declare(strict_types=1);

namespace Drupal\ai_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;

/**
 * Test form exposing the ai_provider_configuration element in 4 nested setups.
 *
 * Each scenario places the element under a different #parents structure so the
 * value callback can be exercised against the matrix of:
 *  - root element vs root container vs subform
 *  - #tree = FALSE vs #tree = TRUE.
 *
 * After submit, the captured value of every scenario is rendered inside its
 * own #result-{scenario} block so each case can be asserted independently.
 *
 * @see https://www.drupal.org/i/3583705
 */
final class AiProviderConfigurationTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_provider_configuration_nested_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = FALSE;

    // Scenario 1: the element is on the form root with no wrapping container.
    $form['scenario_1_heading'] = [
      '#markup' => '<h2>Scenario 1: Root element, not nested</h2>',
    ];
    $form['provider_root_no_tree'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('Provider'),
      '#operation_type' => 'chat',
      '#advanced_config' => TRUE,
      '#default_provider_allowed' => FALSE,
      '#required' => FALSE,
    ];

    // ----- Scenario 2: Root container, #tree = TRUE ------------------------.
    // The wrapping fieldset uses #tree, so the element's #parents become
    // ['root_tree', 'provider'].
    $form['root_tree'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Scenario 2: Root, #tree = TRUE'),
      '#tree' => TRUE,
      '#attributes' => ['id' => 'scenario-2'],
    ];
    $form['root_tree']['provider'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('Provider'),
      '#operation_type' => 'chat',
      '#advanced_config' => TRUE,
      '#default_provider_allowed' => FALSE,
      '#required' => FALSE,
    ];

    // ----- Scenario 3: Root container, #tree = FALSE -----------------------.
    $form['root_no_tree'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Scenario 3: Root container, #tree = FALSE'),
      '#attributes' => ['id' => 'scenario-3'],
    ];
    $form['root_no_tree']['provider'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('Provider'),
      '#operation_type' => 'chat',
      '#advanced_config' => TRUE,
      '#default_provider_allowed' => FALSE,
      '#required' => FALSE,
    ];

    // ----- Scenario 4: Actual subform, #tree = TRUE ------------------------.
    $subform_tree = [
      '#type' => 'fieldset',
      '#title' => $this->t('Scenario 4: Subform, #tree = TRUE'),
      '#attributes' => ['id' => 'scenario-4'],
    ];
    $form['subform_tree'] = $this->buildProviderSubform(
      $subform_tree,
      SubformState::createForSubform($subform_tree, $form, $form_state),
      TRUE
    );

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    // Render the captured values from the previous submission, if any. Each
    // scenario gets its own wrapper so functional tests can scrape them
    // independently.
    $results = $form_state->get('captured_results');
    if (is_array($results)) {
      $form['captured'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'captured-results'],
      ];
      foreach ($results as $key => $value) {
        $form['captured'][$key] = [
          '#type' => 'container',
          '#attributes' => ['id' => 'result-' . str_replace('_', '-', $key)],
          'heading' => [
            '#markup' => '<h3>' . $key . '</h3>',
          ],
          'dump' => [
            '#prefix' => '<pre>',
            '#suffix' => '</pre>',
            '#plain_text' => print_r($value, TRUE),
          ],
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $subform_tree_state = SubformState::createForSubform($form['subform_tree'], $form, $form_state);

    // Resolve the value of every scenario from its expected location in the
    // submitted values tree, mirroring the #parents the element should have.
    $captured = [
      // Scenario 1: ['provider_root_no_tree'] (parent fieldset has no #tree).
      'scenario_1_root_no_tree' => $values['provider_root_no_tree'] ?? NULL,
      // Scenario 2: ['root_tree', 'provider'].
      'scenario_2_root_tree' => $values['root_tree']['provider'] ?? NULL,
      // Scenario 3: root container with #tree = FALSE.
      'scenario_3_root_container_no_tree' => $values['provider'] ?? NULL,
      // Scenario 4: actual subform with #tree = TRUE.
      'scenario_4_subform_tree' => $subform_tree_state->getValue('provider'),
    ];

    $form_state->set('captured_results', $captured);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Builds the shared provider subform.
   *
   * @param array $subform
   *   The subform element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The subform state.
   * @param bool $tree
   *   Whether the subform should preserve nested values.
   *
   * @return array
   *   The populated subform.
   */
  protected function buildProviderSubform(array $subform, FormStateInterface $form_state, bool $tree): array {
    $subform['#tree'] = $tree;
    $subform['provider'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('Provider'),
      '#operation_type' => 'chat',
      '#advanced_config' => TRUE,
      '#default_provider_allowed' => FALSE,
      '#required' => FALSE,
    ];

    return $subform;
  }

}
