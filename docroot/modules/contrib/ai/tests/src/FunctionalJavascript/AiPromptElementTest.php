<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascript;

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Contains Footnotes Dialog JS alternative test.
 *
 * @group ai_prompt
 * @group 3586459
 */
#[RunTestsInSeparateProcesses]
class AiPromptElementTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_content_suggestions',
    'ai_prompt_test',
    'ai_test',
    'file',
    'taxonomy',
    'user',
    'system',
    'toolbar',
    'menu_ui',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * Config that are excluded from schema checking in this particular test.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = [
    'ai_content_suggestions.prompts',
  ];

  /**
   * AI Admin.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $aiAdmin;

  /**
   * Manage AI Prompts only.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $manageAiPrompts;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->aiAdmin = $this->drupalCreateUser([
      'administer ai',
      'manage ai prompts',
      'administer ai prompt types',
    ]);
    $this->manageAiPrompts = $this->drupalCreateUser([
      'manage ai prompts',
    ]);
    if (str_starts_with(phpversion(), '8.1')) {
      $this->markTestSkipped('Skipping test in previous major 10.4, see https://www.drupal.org/project/ai/issues/3509235#comment-16073686.');
    }
  }

  /**
   * Tests the AI Prompt Element.
   */
  public function testAiPromptElementUi(): void {

    // In ai_prompt_test, we install pre AI Prompt element plain text. Run the
    // install manually to upgrade.
    $this->container->get('module_handler')
      ->loadInclude('ai_content_suggestions', 'install');
    ai_content_suggestions_update_10003();
    ai_content_suggestions_update_10004();

    // Load the content suggestions form.
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('admin/config/ai/suggestions');
    $this->takeScreenshot('1_1_suggestions_form_loaded');
    $this->assertSession()->elementExists('css', 'input[name="taxonomy_suggest[taxonomy_suggest_enabled]"]');
    $this->submitForm([
      'taxonomy_suggest[taxonomy_suggest_enabled]' => TRUE,
    ], 'Save configuration');

    // Save the full form to validate the default prompt is saved as expected.
    $this->submitForm([], 'Save configuration');
    $this->takeScreenshot('1_2_after_save_default_config');
    $config = $this->config('ai_content_suggestions.prompts');
    $this->assertSame('suggest_tags__suggest_tags_default', $config->get('taxonomy_suggest_open'));

    // Validate that the default is also installed.
    $this->assertSession()->pageTextContains('Default prompt for suggest tags');
    $this->assertSession()->pageTextContains('Suggest no more than five words to classify the following text using the same language as the input text. The words must be nouns or adjectives in a comma delimited list');

    // Click to create a new prompt.
    $this->getSession()->getPage()->pressButton('Create new prompt');
    $this->assertSession()->waitForText('New prompt details');
    $this->takeScreenshot('1_3_new_prompt_modal_open');
    $this->getSession()->getPage()->fillField('ai_prompt_subform[plugins][taxonomy_suggest][taxonomy_suggest_prompt_open][add_prompt][label]', 'Test 1');
    $this->getSession()->getPage()->find('css', 'button[data-drupal-selector="edit-id-machine-name-admin-link"]')->click();
    $this->getSession()->getPage()->fillField('ai_prompt_subform[plugins][taxonomy_suggest][taxonomy_suggest_prompt_open][add_prompt][id]', '');
    $this->getSession()->getPage()->pressButton('Save prompt');

    // Expect to see validation errors.
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('1_4_validation_errors');
    $this->assertSession()->pageTextContains('Machine name is required.');
    $this->assertSession()->pageTextContains('Please enter a prompt text.');

    // Fill in the rest.
    $this->getSession()->getPage()->find('css', 'button[data-drupal-selector="edit-id-machine-name-admin-link"]')->click();
    $this->getSession()->getPage()->fillField('ai_prompt_subform[plugins][taxonomy_suggest][taxonomy_suggest_prompt_open][add_prompt][id]', 'test_1');
    $this->fillMdxEditorField('ai_prompt_subform[plugins][taxonomy_suggest][taxonomy_suggest_prompt_open][add_prompt][prompt]', 'Test 1 prompt text');
    $this->getSession()->getPage()->pressButton('Save prompt');

    // Check that the prompt is created and automatically selected.
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('1_5_prompt_test1_created');
    $selected = $this->getSession()->getPage()->findField('plugins[taxonomy_suggest][taxonomy_suggest_prompt_open][table]')->getValue();
    $this->assertSame('suggest_tags__test_1', $selected);

    // Save the full form to validate config is saved as expected.
    $this->submitForm([], 'Save configuration');
    $config = $this->config('ai_content_suggestions.prompts');
    $this->assertSame('suggest_tags__test_1', $config->get('taxonomy_suggest_open'));

    // Test create prompt in second element.
    $this->getSession()->getPage()->pressButton('plugins[taxonomy_suggest][taxonomy_suggest_prompt_from_voc][open_add_prompt]');
    $this->assertSession()->waitForText('New prompt details');
    $this->getSession()->getPage()->fillField('ai_prompt_subform[plugins][taxonomy_suggest][taxonomy_suggest_prompt_from_voc][add_prompt][label]', 'Test 1 vocab');
    $this->fillMdxEditorField('ai_prompt_subform[plugins][taxonomy_suggest][taxonomy_suggest_prompt_from_voc][add_prompt][prompt]', 'Test 1 vocab prompt text');
    $this->getSession()->getPage()->pressButton('plugins[taxonomy_suggest][taxonomy_suggest_prompt_from_voc][save_prompt]');

    // Check that the prompt is created and automatically selected.
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('1_6_vocab_prompt_created');
    $selected = $this->getSession()->getPage()->findField('plugins[taxonomy_suggest][taxonomy_suggest_prompt_from_voc][table]')->getValue();
    $this->assertSame('suggest_vocabulary__test_1_vocab', $selected);

    // Check machine name unique required.
    $this->getSession()->getPage()->pressButton('Create new prompt');
    $this->assertSession()->waitForText('New prompt details');
    $this->getSession()->getPage()->fillField('ai_prompt_subform[plugins][taxonomy_suggest][taxonomy_suggest_prompt_open][add_prompt][label]', 'Test 1');
    $this->getSession()->getPage()->find('css', 'button[data-drupal-selector="edit-id-machine-name-admin-link"]')->click();
    $this->getSession()->getPage()->fillField('ai_prompt_subform[plugins][taxonomy_suggest][taxonomy_suggest_prompt_open][add_prompt][id]', 'test_1');
    $this->fillMdxEditorField('ai_prompt_subform[plugins][taxonomy_suggest][taxonomy_suggest_prompt_open][add_prompt][prompt]', 'Test 1 prompt text 2');
    $this->getSession()->getPage()->pressButton('Save prompt');

    // Expect to see validation error.
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('1_7_duplicate_machine_name_error');
    $this->assertSession()->pageTextContains('The machine-readable name is already in use. It must be unique.');

    // Test cancel button.
    $this->drupalGet('admin/config/ai/suggestions');
    $this->getSession()->getPage()->pressButton('plugins[taxonomy_suggest][taxonomy_suggest_prompt_from_voc][open_add_prompt]');
    $this->assertSession()->waitForText('New prompt details');
    $this->getSession()->getPage()->pressButton('plugins[taxonomy_suggest][taxonomy_suggest_prompt_from_voc][cancel_add_prompt]');
    $this->takeScreenshot('1_8_after_cancel');

    // Check that the prompt is editable via the Admin > Config > AI area.
    $this->drupalGet('admin/config/ai/prompts');
    $this->takeScreenshot('1_9_prompts_listing');
    $this->assertSession()->pageTextContains('suggest_tags__suggest_tags_default');
    $this->assertSession()->pageTextContains('suggest_vocabulary__suggest_vocabulary_default');
    $this->assertSession()->pageTextContains('suggest_tags__test_1');
    $this->assertSession()->pageTextContains('suggest_vocabulary__test_1_vocab');

    // Edit the default.
    $this->drupalGet('admin/config/ai/prompts/suggest_tags__suggest_tags_default');
    $this->takeScreenshot('1_10_edit_prompt_form');
    $this->assertSession()->fieldValueEquals('label', 'Default prompt for suggest tags');
    $this->assertSession()->fieldValueEquals('prompt', 'Suggest no more than five words to classify the following text using the same language as the input text. The words must be nouns or adjectives in a comma delimited list');
    $source_text_area = $this->assertSession()->waitForElementVisible('css', '.mdxeditor-rich-text-editor [contenteditable="true"]');
    $this->assertNotEmpty($source_text_area);
    $source_text_area->click();
    $this->getSession()->executeScript("
      const editor = document.querySelector('.mdxeditor-rich-text-editor [contenteditable=\"true\"]');
      // Clear existing content and type
      editor.focus();
      document.execCommand('selectAll', false, null);
      // Simulate the physical key sequence
      const backspaceEvent = new KeyboardEvent('keydown', {
        key: 'Backspace',
        code: 'Backspace',
        keyCode: 8,
        which: 8,
        bubbles: true
      });
      editor.dispatchEvent(backspaceEvent);
      document.execCommand('delete', false, null);
      editor.focus();
      document.execCommand('insertText', false, 'Updated prompt');
      // Dispatch an input event to force React/Lexical to sync
      editor.dispatchEvent(new Event('input', { bubbles: true }));
      editor.dispatchEvent(new Event('change', { bubbles: true }));
      editor.blur();
    ");
    $this->getSession()->getPage()->fillField('label', 'Updated label');
    // Save the form.
    $this->submitForm([], 'Save');
    $this->takeScreenshot('1_11_prompt_updated');
    $this->assertSession()->pageTextContains('The AI Prompt has been updated.');
    $this->assertSession()->pageTextContains('Updated label');
    $this->assertSession()->pageTextContains('Updated prompt');

    // Delete the default.
    $this->drupalGet('admin/config/ai/prompts/suggest_tags__suggest_tags_default/delete');
    $this->takeScreenshot('1_12_delete_confirmation');
    $this->assertSession()->pageTextContains('This action cannot be undone.');
    $this->submitForm([], 'Delete');
    $this->takeScreenshot('1_13_after_delete');
    $this->assertSession()->pageTextContains('The AI Prompt Updated label has been deleted.');
    $this->assertSession()->pageTextContains('suggest_vocabulary__suggest_vocabulary_default');
  }

  /**
   * Tests the AI Prompt Element access.
   */
  public function testAiPromptElementAccess(): void {
    // Get a form with no access yet and expect to only be able to select
    // from AI Prompts.
    $this->drupalLogin($this->manageAiPrompts);

    // Now get the same form and expect to be able to also create a new AI
    // Prompt.
    $this->drupalGet('admin/config/ai/suggestions');
    $this->takeScreenshot('2_1_suggestions_form_limited_access');
    $this->assertSession()->elementExists('css', 'input[name="taxonomy_suggest[taxonomy_suggest_enabled]"]');
    $this->submitForm([
      'taxonomy_suggest[taxonomy_suggest_enabled]' => TRUE,
    ], 'Save configuration');

    // Click to create a new prompt.
    $this->getSession()->getPage()->pressButton('Create new prompt');
    $this->assertSession()->waitForText('New prompt details');
    $this->takeScreenshot('2_2_new_prompt_modal_open');
    $this->getSession()->getPage()->fillField('ai_prompt_subform[plugins][taxonomy_suggest][taxonomy_suggest_prompt_open][add_prompt][label]', 'Test 2');
    $this->fillMdxEditorField('ai_prompt_subform[plugins][taxonomy_suggest][taxonomy_suggest_prompt_open][add_prompt][prompt]', 'Test 2 prompt text');
    $this->getSession()->getPage()->pressButton('Save prompt');

    // Check that the prompt is created and automatically selected.
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('2_3_prompt_test2_created');
    $selected = $this->getSession()->getPage()->findField('plugins[taxonomy_suggest][taxonomy_suggest_prompt_open][table]')->getValue();
    $this->assertSame('suggest_tags__test_2', $selected);

    // Ensure access denied on creating prompt type.
    $this->drupalGet('admin/config/ai/prompts/prompt-types');
    $this->takeScreenshot('2_4_access_denied_prompt_types');
    $this->assertSession()->pageTextContains('You are not authorized to access this page.');
  }

  /**
   * Test the AI prompt types with adding and removing tokens.
   */
  public function testAiPromptElementTokens(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('admin/config/ai/prompts/prompt-types/add');
    $this->takeScreenshot('3_1_add_prompt_type_form');
    $this->getSession()->getPage()->fillField('edit-label', 'Test Type');
    $this->getSession()->getPage()->pressButton('Tokens');
    // Add one token.
    $this->getSession()->getPage()->fillField('tokens[0][name]', 'token:1');
    $this->getSession()->getPage()->fillField('tokens[0][help_text]', 'Token 1 Description');
    $this->getSession()->getPage()->checkField('tokens[0][required]');
    // Add another token.
    $this->getSession()->getPage()->pressButton('add_token');
    // Wait for the AJAX to complete.
    $this->assertSession()->waitForField('tokens[1][name]', 5000);
    $this->takeScreenshot('3_2_second_token_row_added');
    $this->getSession()->getPage()->fillField('tokens[1][name]', 'token:2');
    $this->getSession()->getPage()->fillField('tokens[1][help_text]', 'Token 2 Description');
    // Save the form.
    $this->getSession()->getPage()->pressButton('Save');
    // Reload the edit page.
    $this->drupalGet('admin/config/ai/prompts/prompt-types/test_type');
    $this->takeScreenshot('3_3_edit_prompt_type_with_tokens');
    // Remove the first token.
    $this->getSession()->getPage()->pressButton('edit-tokens-0-remove', 0);
    // Wait for the AJAX to complete.
    $this->assertSession()->waitForElementRemoved('css', 'input[name="tokens[0][name]"][value="token:1"]', 5000);
    // Make sure that token 1 is removed and token 2 is now in the first row.
    $this->takeScreenshot('3_4_token1_removed_unsaved');
    $this->assertSession()->fieldValueEquals('tokens[0][name]', 'token:2');
    $this->assertSession()->fieldValueEquals('tokens[0][help_text]', 'Token 2 Description');
    $this->assertSession()->checkboxNotChecked('tokens[0][required]');
    // Do not save, to make sure removal doesn't work without saving.
    // Visit the listings page.
    $this->drupalGet('admin/config/ai/prompts/prompt-types');
    // Check that both token names are there.
    $this->takeScreenshot('3_5_listing_both_tokens_present');
    $this->assertSession()->pageTextContains('token:1');
    $this->assertSession()->pageTextContains('token:2');
    // Now visit the edit page again.
    $this->drupalGet('admin/config/ai/prompts/prompt-types/test_type');
    $this->getSession()->wait(5000, 'document.readyState === "complete"');
    // Remove the first token.
    $this->getSession()->getPage()->pressButton('edit-tokens-0-remove', 0);
    // Wait for the AJAX to complete.
    $this->assertSession()->waitForElementRemoved('css', 'input[name="tokens[0][name]"][value="token:1"]', 5000);
    // Make sure that token 1 is removed and token 2 is now in the first row.
    $this->takeScreenshot('3_6_token1_removed_before_save');
    $this->assertSession()->fieldValueEquals('tokens[0][name]', 'token:2');
    $this->assertSession()->fieldValueEquals('tokens[0][help_text]', 'Token 2 Description');
    $this->assertSession()->checkboxNotChecked('tokens[0][required]');
    // Save the form.
    $this->getSession()->getPage()->pressButton('Save');
    // Visit the listings page.
    $this->drupalGet('admin/config/ai/prompts/prompt-types');
    // Wait for page to fully load and ensure DOM is stable.
    $this->getSession()->wait(5000, 'document.readyState === "complete"');
    $this->assertSession()->waitForText('token:2', 5000);
    $this->takeScreenshot('3_7_listing_only_token2_after_save');
    // Check that only token 2 name is there.
    $this->assertSession()->pageTextNotContains('token:1');
    $this->assertSession()->pageTextContains('token:2');
  }

}
