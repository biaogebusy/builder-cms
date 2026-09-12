<?php

declare(strict_types=1);

namespace Drupal\ai_test\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;

/**
 * Test form exposing ai_prompt in three distinct nesting modes.
 *
 * Background: AiPrompt::getInfo() returns '#tree' => FALSE. Because
 * FormBuilder loads element defaults (including #tree = FALSE) at line 1085 of
 * doBuildForm(), *before* the parent-inheritance check at line 1091, the
 * ai_prompt element never inherits a parent's #tree = TRUE. This means #parents
 * cannot be derived automatically from the form tree — every ai_prompt element
 * must set #parents explicitly, exactly as Completion.php and the other
 * ckeditor plugin forms do.
 *
 * Scenario 1 (root level): explicit #parents match #array_parents — the
 * standard, no-mismatch case. Baseline to confirm existing behavior works.
 *
 * Scenario 2 (manual #parents mismatch): the element sits inside a 'subform'
 * tree level but its explicit #parents skips that level, replicating exactly
 * what Drupal's editor module does when wrapping CKEditor5 plugin forms:
 *
 *  $form['editor']['settings']['subform']['#parents'] = ['editor', 'settings'];
 *  // → children's values path omits 'subform', tree path includes it
 *
 * Before the fix in \Drupal\ai\Element\AiPrompt the AJAX callbacks called
 * NestedArray::getValue($form, #parents) using the values path, missed
 * 'subform', and returned NULL causing:
 *
 *   TypeError: Argument #1 ($main_content) must be of type array, null given
 *
 * Scenario 3 (SubformState): mirrors the buildProviderSubform pattern from
 * \Drupal\ai_test\Form\AiProviderConfigurationTestForm. A local array is
 * created, SubformState::createForSubform() is called on it, a helper method
 * (buildPromptSubform) populates it with the ai_prompt element and its
 * explicit #parents, then the result is assigned to the form. The submit
 * handler reads the value back via SubformState::getValue() — mirroring
 * AiProviderConfigurationTestForm Scenario 4.
 *
 * After submit, each scenario's captured value is rendered in its own
 * #result-{scenario} block for independent assertion by functional tests.
 */
final class AiPromptElementNestingTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_prompt_element_nesting_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = FALSE;

    // ---- Scenario 1: root-level, #parents matches #array_parents ------------
    // Element tree path:  form['scenario_1']['prompt']
    // #parents (explicit): ['scenario_1', 'prompt']
    // #array_parents:      ['scenario_1', 'prompt']   ← same, no mismatch
    //
    // Note: #tree = TRUE is intentionally NOT set on the container; ai_prompt
    // sets #tree = FALSE in getInfo() and therefore must always have #parents
    // set explicitly by the hosting form.
    $form['scenario_1'] = [
      '#type' => 'details',
      '#title' => $this->t('Scenario 1: Root-level (normal)'),
      '#open' => TRUE,
      '#attributes' => ['id' => 'scenario-1'],
    ];
    $form['scenario_1']['prompt'] = [
      '#type' => 'ai_prompt',
      '#title' => $this->t('Prompt (root)'),
      '#prompt_types' => ['ai_test_nesting'],
      '#parents' => ['scenario_1', 'prompt'],
    ];

    // --- Scenario 2: #parents skips one tree level (#parents ≠ #array_parents)
    // Element tree path:   form['scenario_2']['settings']['subform']['prompt']
    // #parents (explicit): ['scenario_2', 'settings', 'prompt'] skips 'subform'
    // #array_parents:      ['scenario_2', 'settings', 'subform', 'prompt']
    //
    // This directly reproduces the mismatch that broke the AJAX callbacks.
    // The values path (used for form-state reads/writes) omits the 'subform'
    // key; the form-tree path (needed to find the element in $form) includes
    // it. The old code used #parents for NestedArray::getValue($form, …) and
    // returned NULL. The fix uses #array_parents for tree traversal.
    $form['scenario_2'] = [
      '#type' => 'details',
      '#title' => $this->t('Scenario 2: Subform-nested (editor module pattern)'),
      '#open' => TRUE,
      '#attributes' => ['id' => 'scenario-2'],
    ];
    $form['scenario_2']['settings'] = ['#type' => 'container'];
    $form['scenario_2']['settings']['subform'] = ['#type' => 'container'];
    $form['scenario_2']['settings']['subform']['prompt'] = [
      '#type' => 'ai_prompt',
      '#title' => $this->t('Prompt (subform-nested)'),
      '#prompt_types' => ['ai_test_nesting'],
      // Explicit #parents skips the 'subform' tree level.
      '#parents' => ['scenario_2', 'settings', 'prompt'],
    ];

    // ---- Scenario 3: SubformState (mirrors buildProviderSubform) ------------
    // Mirrors AiProviderConfigurationTestForm::buildForm() Scenario 4.
    //
    // Note: unlike ai_provider_configuration, the ai_prompt element's
    // #process callbacks are invoked by FormBuilder with the *main* form state
    // regardless of what form state was passed to buildPromptSubform. The
    // SubformState is therefore only meaningful at submit time — specifically
    // for SubformState::getValue(), which is the pattern being verified here.
    //
    // Element tree path:   form['scenario_3']['prompt']
    // #parents (explicit): ['scenario_3', 'prompt']   ← matches tree path
    // #array_parents:      ['scenario_3', 'prompt']   ← same, no mismatch
    //
    // The submit handler reads the value back via SubformState::getValue(),
    // mirroring AiProviderConfigurationTestForm::submitForm() which uses
    // $subform_tree_state->getValue('provider') for its Scenario 4.
    $scenario_3_subform = [
      '#type' => 'details',
      '#title' => $this->t('Scenario 3: SubformState (buildProviderSubform pattern)'),
      '#open' => TRUE,
      '#attributes' => ['id' => 'scenario-3'],
    ];
    $form['scenario_3'] = $this->buildPromptSubform($scenario_3_subform, ['scenario_3']);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    // Render captured values from a previous submission so functional tests can
    // scrape them independently.
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
          'heading' => ['#markup' => '<h3>' . $key . '</h3>'],
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

    // Scenario 3: read via SubformState exactly as
    // AiProviderConfigurationTestForm reads its Scenario 4 with
    // $subform_tree_state->getValue('provider').
    $subform_state_3 = SubformState::createForSubform($form['scenario_3'], $form, $form_state);

    $captured = [
      // Scenario 1: #parents = ['scenario_1', 'prompt'].
      'scenario_1_prompt' => NestedArray::getValue($values, ['scenario_1', 'prompt']),
      // Scenario 2: #parents = ['scenario_2', 'settings', 'prompt']
      // (no 'subform').
      'scenario_2_prompt' => NestedArray::getValue($values, ['scenario_2', 'settings', 'prompt']),
      // Scenario 3: read relative to subform root via SubformState.
      'scenario_3_prompt' => $subform_state_3->getValue('prompt'),
    ];

    $form_state->set('captured_results', $captured);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Builds a subform containing an ai_prompt element.
   *
   * Mirrors AiProviderConfigurationTestForm::buildProviderSubform() but for
   * ai_prompt. Because AiPrompt::getInfo() sets #tree = FALSE, automatic
   * parent inheritance is suppressed — #parents must be set explicitly, exactly
   * as Completion.php and other ckeditor plugin forms do.
   *
   * @param array $subform
   *   The subform array to populate.
   * @param array $base_parents
   *   The form-state values path to the subform root (e.g. ['scenario_3']).
   *   Used to build the prompt element's explicit #parents.
   *
   * @return array
   *   The populated subform.
   */
  protected function buildPromptSubform(array $subform, array $base_parents): array {
    $subform['prompt'] = [
      '#type' => 'ai_prompt',
      '#title' => $this->t('Prompt (SubformState)'),
      '#prompt_types' => ['ai_test_nesting'],
      '#parents' => array_merge($base_parents, ['prompt']),
    ];
    return $subform;
  }

}
