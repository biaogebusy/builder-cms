<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascript;

use Drupal\ai\Entity\AiPrompt;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ai_prompt AJAX callbacks under different form-tree nesting modes.
 *
 * Three scenarios are exercised through
 * \Drupal\ai_test\Form\AiPromptElementNestingTestForm:
 *
 * Scenario 1 (root level): the element's #parents and #array_parents are
 * identical — the normal, no-mismatch case. Verifies that existing behavior
 * is preserved after the array_parents fix.
 *
 * Scenario 2 (manual #parents override): the element sits inside a container
 * whose #parents is explicitly overridden to skip one tree level, replicating
 * exactly what Drupal's editor module does when it wraps CKEditor5 plugin
 * configuration forms:
 *
 *  $form['editor']['settings']['subform']['#parents'] = ['editor', 'settings'];
 *
 * This creates a gap between the values path (#parents) and the real form-tree
 * path (#array_parents). Before the fix, every AJAX callback in
 * \Drupal\ai\Element\AiPrompt called NestedArray::getValue($form, #parents),
 * traversed the tree using the values path (which omits the 'subform' key),
 * and returned NULL. AjaxRenderer::renderResponse() then threw:
 *
 *   TypeError: Argument #1 ($main_content) must be of type array, null given
 *
 * After the fix, callbacks use #array_parents for form-tree traversal and
 * #parents only for form-state value operations, so the correct element is
 * found and the AJAX response is built correctly.
 *
 * Scenario 3 (SubformState): mirrors the buildProviderSubform pattern in
 * \Drupal\ai_test\Form\AiProviderConfigurationTestForm. A local array is
 * created, SubformState::createForSubform() is called on it, a helper method
 * populates it with the ai_prompt element, and the result is assigned to the
 * form. The submit handler reads the value back via SubformState::getValue(),
 * mirroring the AiProviderConfigurationTestForm Scenario 4 approach.
 *
 * @group ai
 * @group ai_prompt
 */
#[RunTestsInSeparateProcesses]
class AiPromptElementNestingTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * Admin user with full AI permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $aiAdmin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->aiAdmin = $this->drupalCreateUser([
      'administer ai',
      'manage ai prompts',
      'administer ai prompt types',
    ]);
    $this->assertNotFalse($this->aiAdmin);
  }

  /**
   * Tests the full prompt-creation flow for a root-level ai_prompt element.
   *
   * Scenario 1 confirms that the standard, no-mismatch case continues to work
   * after the array_parents fix was applied.
   */
  public function testRootLevelPromptCreationFlow(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('/admin/config/ai/test-ai-prompt-element-nesting');
    $this->takeScreenshot('1_1_initial_page');

    $this->getSession()->getPage()->pressButton('scenario_1[prompt][open_add_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('1_2_inline_form_open');
    $this->assertSession()->pageTextContains('New prompt details');

    $this->fillInlinePromptForm(
      'scenario_1[prompt]',
      'Root Level Prompt',
      'Root level prompt text',
    );
    $this->takeScreenshot('1_3_form_filled');

    $this->getSession()->getPage()->pressButton('scenario_1[prompt][save_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('1_4_after_save');

    $selected = $this->getTableSelectValue('scenario_1[prompt][table]');
    $this->assertSame(
      'ai_test_nesting__root_level_prompt',
      $selected,
      'Scenario 1: newly created prompt must be auto-selected after save.',
    );

    $this->submitForm([], 'Submit');
    $this->takeScreenshot('1_5_after_submit');

    $result = $this->assertSession()->waitForElement('css', '#result-scenario-1-prompt pre');
    $this->assertNotNull($result, 'Captured result for scenario_1_prompt must be present after submit.');
    $this->assertStringContainsString('ai_test_nesting__root_level_prompt', $result->getText());
  }

  /**
   * Tests that the cancel button closes the inline form in Scenario 1.
   */
  public function testRootLevelCancelButton(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('/admin/config/ai/test-ai-prompt-element-nesting');

    $this->getSession()->getPage()->pressButton('scenario_1[prompt][open_add_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New prompt details');
    $this->takeScreenshot('2_1_inline_form_open_s1');

    $this->getSession()->getPage()->pressButton('scenario_1[prompt][cancel_add_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('2_2_after_cancel_s1');

    $this->assertSession()->pageTextNotContains('New prompt details');
  }

  /**
   * Tests that validation errors are shown correctly in Scenario 1.
   */
  public function testRootLevelValidationErrors(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('/admin/config/ai/test-ai-prompt-element-nesting');

    $this->getSession()->getPage()->pressButton('scenario_1[prompt][open_add_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField(
      'ai_prompt_subform[scenario_1][prompt][add_prompt][label]',
      'Root Level Prompt',
    );
    $this->getSession()->getPage()->pressButton('scenario_1[prompt][save_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('3_1_validation_errors_s1');

    $this->assertSession()->pageTextContains('Please enter a prompt text.');
  }

  /**
   * Tests the full prompt-creation flow for a subform-nested ai_prompt element.
   *
   * This is the primary regression test. The element's #parents values path is
   * ['scenario_2', 'settings', 'prompt'] but its #array_parents form-tree path
   * is ['scenario_2', 'settings', 'subform', 'prompt'] (one extra level).
   *
   * With the old code every AJAX callback received NULL from
   * NestedArray::getValue($form, #parents) and AjaxRenderer threw a TypeError.
   * With the fix, #array_parents is used for tree traversal.
   */
  public function testSubformNestedPromptCreationFlow(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('/admin/config/ai/test-ai-prompt-element-nesting');
    $this->takeScreenshot('4_1_initial_page');

    // With the old code this AJAX call returned NULL and threw a TypeError.
    $this->getSession()->getPage()->pressButton('scenario_2[settings][prompt][open_add_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('4_2_inline_form_open');
    $this->assertSession()->pageTextContains('New prompt details');

    $this->fillInlinePromptForm(
      'scenario_2[settings][prompt]',
      'Subform Nested Prompt',
      'Subform nested prompt text',
    );
    $this->takeScreenshot('4_3_form_filled');

    // With the old code the save callback also returned NULL.
    $this->getSession()->getPage()->pressButton('scenario_2[settings][prompt][save_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('4_4_after_save');

    $selected = $this->getTableSelectValue('scenario_2[settings][prompt][table]');
    $this->assertSame(
      'ai_test_nesting__subform_nested_prompt',
      $selected,
      'Scenario 2: newly created prompt must be auto-selected after save.',
    );

    $this->submitForm([], 'Submit');
    $this->takeScreenshot('4_5_after_submit');

    $result = $this->assertSession()->waitForElement('css', '#result-scenario-2-prompt pre');
    $this->assertNotNull($result, 'Captured result for scenario_2_prompt must be present after submit.');
    $this->assertStringContainsString('ai_test_nesting__subform_nested_prompt', $result->getText());
  }

  /**
   * Tests validation errors and the cancel button in Scenario 2.
   *
   * Ensures both the save-with-errors AJAX callback and the cancel AJAX
   * callback use the correct #array_parents for form-tree traversal.
   */
  public function testSubformNestedValidationAndCancel(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('/admin/config/ai/test-ai-prompt-element-nesting');

    $this->getSession()->getPage()->pressButton('scenario_2[settings][prompt][open_add_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New prompt details');
    // Provide only a label — machine name is still required.
    $this->getSession()->getPage()->fillField(
      'ai_prompt_subform[scenario_2][settings][prompt][add_prompt][label]',
      'Only Label',
    );
    // Submit without filling anything in.
    $this->getSession()->getPage()->pressButton('scenario_2[settings][prompt][save_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('5_1_validation_errors_s2');

    $this->assertSession()->pageTextContains('Please enter a prompt text.');

    // Cancel and verify the inline form closes.
    $this->getSession()->getPage()->pressButton('scenario_2[settings][prompt][cancel_add_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('5_3_after_cancel_s2');

    $this->assertSession()->pageTextNotContains('New prompt details');
  }

  /**
   * Tests that a duplicate machine name is rejected in Scenario 2.
   *
   * Creates the seed prompt entity directly to avoid needing to trigger
   * "Create new prompt" twice (re-showing the button after save is a known
   * Drupal #states limitation noted with @todo in AiPrompt::processElement()).
   */
  public function testSubformNestedDuplicateMachineNameRejected(): void {
    // Pre-create a prompt. After preSave() the ID becomes
    // 'ai_test_nesting__dup_test'.
    $prompt = AiPrompt::create([
      'type' => 'ai_test_nesting',
      'id' => 'dup_test',
      'label' => 'Duplicate Test Seed',
      'prompt' => 'Seed prompt text',
    ]);
    $prompt->save();

    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('/admin/config/ai/test-ai-prompt-element-nesting');

    $this->getSession()->getPage()->pressButton('scenario_2[settings][prompt][open_add_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->fillInlinePromptForm(
      'scenario_2[settings][prompt]',
      'Dup Test',
      'Duplicate attempt text',
    );

    $this->getSession()->getPage()->pressButton('scenario_2[settings][prompt][save_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('6_1_duplicate_machine_name_rejected');

    $this->assertSession()->pageTextContains(
      'The machine-readable name is already in use. It must be unique.',
    );
  }

  /**
   * Tests the full prompt-creation flow for the SubformState scenario.
   *
   * Scenario 3 mirrors precisely how AiProviderConfigurationTestForm builds
   * Scenario 4 ("actual subform, #tree = TRUE"). The form uses:
   *
   *  $form['scenario_3'] = $this->buildPromptSubform(
   *    $scenario_3_subform,
   *    SubformState::createForSubform($scenario_3_subform, $form, $form_state),
   *  );
   *
   * In this nesting mode #parents and #array_parents both resolve to
   * ['scenario_3', 'prompt'] so there is no tree/values mismatch. The test
   * verifies:
   *   - "Create new prompt" AJAX works inside a SubformState-built element.
   *   - The newly saved prompt is auto-selected in the tableselect.
   *   - The outer form submit reads the value back via SubformState::getValue()
   *     exactly as AiProviderConfigurationTestForm does for its Scenario 4.
   */
  public function testSubformStatePromptCreationFlow(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('/admin/config/ai/test-ai-prompt-element-nesting');
    $this->takeScreenshot('7_1_initial_page');

    $this->getSession()->getPage()->pressButton('scenario_3[prompt][open_add_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('7_2_inline_form_open');
    $this->assertSession()->pageTextContains('New prompt details');

    $this->fillInlinePromptForm(
      'scenario_3[prompt]',
      'Subform State Prompt',
      'SubformState prompt text',
    );
    $this->takeScreenshot('7_3_form_filled');

    $this->getSession()->getPage()->pressButton('scenario_3[prompt][save_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('7_4_after_save');

    $selected = $this->getTableSelectValue('scenario_3[prompt][table]');
    $this->assertSame(
      'ai_test_nesting__subform_state_prompt',
      $selected,
      'Scenario 3: newly created prompt must be auto-selected after save.',
    );

    // Submit the outer form. The handler reads the value via
    // SubformState::getValue('prompt'), matching
    // AiProviderConfigurationTestForm.
    $this->submitForm([], 'Submit');
    $this->takeScreenshot('7_5_after_submit');

    $result = $this->assertSession()->waitForElement('css', '#result-scenario-3-prompt pre');
    $this->assertNotNull($result, 'Captured result for scenario_3_prompt must be present after submit.');
    $this->assertStringContainsString(
      'ai_test_nesting__subform_state_prompt',
      $result->getText(),
      'Scenario 3: SubformState::getValue("prompt") must return the selected prompt ID.',
    );
  }

  /**
   * Tests validation errors and the cancel button for Scenario 3.
   */
  public function testSubformStateValidationAndCancel(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('/admin/config/ai/test-ai-prompt-element-nesting');

    $this->getSession()->getPage()->pressButton('scenario_3[prompt][open_add_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New prompt details');
    $this->getSession()->getPage()->fillField(
      'ai_prompt_subform[scenario_3][prompt][add_prompt][label]',
      'Root Level Prompt',
    );
    // Submit without filling prompt text.
    $this->getSession()->getPage()->pressButton('scenario_3[prompt][save_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('8_1_validation_errors_s3');

    $this->assertSession()->pageTextContains('Please enter a prompt text.');

    // Cancel and verify the form closes.
    $this->getSession()->getPage()->pressButton('scenario_3[prompt][cancel_add_prompt]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('8_2_after_cancel_s3');

    $this->assertSession()->pageTextNotContains('New prompt details');
  }

  /**
   * Fills the inline ai_prompt creation subform.
   *
   * @param string $element_name_prefix
   *   The base element name prefix using bracket notation (e.g.
   *   'scenario_1[prompt]' or 'scenario_2[settings][prompt]').
   * @param string $label
   *   The human-readable prompt label.
   * @param string $prompt_text
   *   The prompt body text.
   */
  protected function fillInlinePromptForm(
    string $element_name_prefix,
    string $label,
    string $prompt_text = '',
  ): void {
    // Split 'scenario_2[settings][prompt]'→['scenario_2', 'settings', 'prompt']
    // then build the ai_prompt_subform field prefix.
    $parts = preg_split('/[\[\]]/', rtrim($element_name_prefix, ']'), -1, PREG_SPLIT_NO_EMPTY);
    $subform_prefix = 'ai_prompt_subform[' . implode('][', $parts) . '][add_prompt]';

    $page = $this->getSession()->getPage();
    $page->fillField($subform_prefix . '[label]', $label);

    if (!empty($prompt_text)) {
      $this->fillMdxEditorField($subform_prefix . '[prompt]', $prompt_text);
    }
  }

  /**
   * Gets the current value of a tableselect field by its HTML name.
   *
   * @param string $field_name
   *   The HTML name attribute of the tableselect.
   *
   * @return string|null
   *   The selected value, or NULL if the field is not found.
   */
  protected function getTableSelectValue(string $field_name): ?string {
    $field = $this->getSession()->getPage()->findField($field_name);
    return $field?->getValue();
  }

}
