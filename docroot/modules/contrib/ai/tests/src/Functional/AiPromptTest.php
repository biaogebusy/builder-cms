<?php

namespace Drupal\Tests\ai\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Contains AI Prompt UI setup functional tests.
 *
 * @group ai_prompt
 */
class AiPromptTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_content_suggestions',
    'ai_prompt_test',
    'taxonomy',
    'user',
    'system',
    'toolbar',
    'menu_ui',
    'block',
  ];

  /**
   * Config that are excluded from schema checking in this particular test.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = [
    'ai_content_suggestions.prompts',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer ai prompt types',
      'manage ai prompts',
      'access toolbar',
    ]);
    $this->drupalLogin($this->adminUser);
    $this->drupalPlaceBlock('local_actions_block');
  }

  /**
   * Test the prompt manager install path.
   */
  public function testPromptManagerInstall(): void {
    $this->drupalGet('admin/config/ai/prompts');
    $this->assertSession()->pageTextNotContains('Suggest five synonyms for the word: {word}');

    // Run the fake update. This installs a new prompt and type as well as
    // tests preservation of a previously user modified prompt.
    ai_prompt_test_update_test();

    $this->drupalGet('admin/config/ai/prompts');
    $this->assertSession()->pageTextContains('Suggest five synonyms for the word: {word}');

    // Check if modified one was preserved.
    $this->assertSession()->pageTextContains('Hello prompt');

    // Check if the modified prompt is the selected one.
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('ai_prompt_test.settings');
    $this->assertSame('my_prompt_type__my_prompt_modified', $config->get('my_prompt_value'));
  }

  /**
   * Tests the prompt manager UI.
   */
  public function testPromptManagerUi(): void {
    $this->drupalGet('admin/config/ai/prompts/prompt-types');
    $this->clickLink('Add AI Prompt Type');

    // Add another to both token and variables.
    $this->click('input[name="add_variable"]');
    $this->click('input[name="add_token"]');

    // Fill in the form.
    $values = [
      'label' => 'Test prompt label',
      'id' => 'test_prompt',
      'variables[0][name]' => 'variable0',
      'variables[0][help_text]' => 'Variable 0 help text',
      'variables[0][required]' => TRUE,
      'variables[1][name]' => 'variable1',
      'variables[1][help_text]' => 'Variable 1 help text',
      'variables[1][required]' => FALSE,
      'tokens[0][name]' => 'token0',
      'tokens[0][help_text]' => 'Token 0 help text',
      'tokens[0][required]' => TRUE,
      'tokens[1][name]' => 'token1',
      'tokens[1][help_text]' => 'Token 1 help text',
      'tokens[1][required]' => FALSE,
    ];
    $this->submitForm($values, 'Save');

    // Check its saved.
    $this->assertSession()->pageTextContains('The Test prompt label AI Prompt Type has been created.');

    // Check the edit page contains the saved values.
    $this->drupalGet('admin/config/ai/prompts/prompt-types/test_prompt');
    foreach ($values as $name => $value) {
      $this->assertSession()->fieldValueEquals($name, $value);
    }

    // Check extra rows exist for variables to be able to add new.
    $this->assertSession()->fieldExists('variables[2][name]');
    $this->assertSession()->fieldExists('tokens[2][name]');
  }

}
