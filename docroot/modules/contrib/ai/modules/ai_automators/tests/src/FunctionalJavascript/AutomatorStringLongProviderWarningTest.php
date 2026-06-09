<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;

/**
 * Tests the automator form on a string_long field with and without a provider.
 *
 * Verifies that when no AI chat provider is installed, the advanced settings
 * show a warning message. When a provider (ai_test/EchoAI) is installed, the
 * provider select field appears instead.
 *
 * @group ai_automators
 * @group 3574611
 */
class AutomatorStringLongProviderWarningTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'file',
    'field',
    'field_ui',
    'user',
    'text',
    'ai_automators',
  ];

  /**
   * {@inheritdoc}
   */
  protected string $screenshotModuleName = 'ai_automators';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a string_long field on the user entity type.
    FieldStorageConfig::create([
      'field_name' => 'field_bio',
      'entity_type' => 'user',
      'type' => 'string_long',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_bio',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Bio',
    ])->save();
  }

  /**
   * Tests the automator form shows a warning when no provider is available.
   */
  public function testAutomatorWarningWithoutProvider(): void {
    $admin = $this->drupalCreateUser([
      'administer user fields',
      'administer account settings',
    ]);
    $this->drupalLogin($admin);

    // Navigate to the field config page for the string_long field on user.
    $this->drupalGet('/admin/config/people/accounts/fields/user.user.field_bio');
    $this->takeScreenshot('1_field_config_page');

    $page = $this->getSession()->getPage();

    // Enable the AI Automator checkbox.
    $page->checkField('automator_enabled');
    $this->takeScreenshot('2_automator_enabled');

    // Select the LLM: Text rule for string_long.
    $page->selectFieldOption('automator_rule', 'llm_string_long');

    // Wait for AJAX to load the automator container.
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('3_rule_selected');

    // Scroll to and open the Advanced Settings details element.
    $advancedDetails = $page->find('css', '[data-drupal-selector="edit-automator-advanced"] > summary');
    $this->assertNotNull($advancedDetails, 'Advanced Settings details element should exist.');
    $this->getSession()->getDriver()->executeScript(
      "document.querySelector('[data-drupal-selector=\"edit-automator-advanced\"]').scrollIntoView({block: 'center'});"
    );
    $advancedDetails->click();
    $this->takeScreenshot('4_advanced_settings_open');

    // Assert the warning message is displayed since no providers are installed.
    $this->assertSession()->pageTextContains('No AI providers are configured for');
    $this->assertSession()->pageTextContains('configure a provider');

    // Scroll down to make the warning message visible in the screenshot.
    $warningMessage = $page->find('css', '[data-drupal-selector="edit-automator-advanced"] .messages--warning');
    $this->assertNotNull($warningMessage, 'Warning message element should exist.');
    $this->getSession()->getDriver()->executeScript(
      "document.querySelector('[data-drupal-selector=\"edit-automator-advanced\"] .messages--warning').scrollIntoView({block: 'center'});"
    );

    // Assert the provider select field is NOT present.
    $providerSelect = $page->findField('automator_ai_provider');
    $this->assertNull($providerSelect, 'The AI Provider select should not be present when no providers are available.');

    $this->takeScreenshot('5_warning_message_visible');
  }

  /**
   * Tests the automator form shows provider select when ai_test is installed.
   */
  public function testAutomatorProviderSelectWithProvider(): void {
    // Install the ai_test module to get the EchoAI provider.
    $this->container->get('module_installer')->install(['ai_test']);

    // Set EchoAI as default chat provider.
    $this->setDefaultProvider('chat', 'echoai', 'gpt-test');

    $admin = $this->drupalCreateUser([
      'administer user fields',
      'administer account settings',
    ]);
    $this->drupalLogin($admin);

    // Navigate to the field config page for the string_long field on user.
    $this->drupalGet('/admin/config/people/accounts/fields/user.user.field_bio');
    $this->takeScreenshot('1_field_config_with_provider');

    $page = $this->getSession()->getPage();

    // Enable the AI Automator checkbox.
    $page->checkField('automator_enabled');
    $this->takeScreenshot('2_automator_enabled_with_provider');

    // Select the LLM: Text rule for string_long.
    $page->selectFieldOption('automator_rule', 'llm_string_long');

    // Wait for AJAX to load the automator container.
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('3_rule_selected_with_provider');

    // Scroll to and open the Advanced Settings details element.
    $advancedDetails = $page->find('css', '[data-drupal-selector="edit-automator-advanced"] > summary');
    $this->assertNotNull($advancedDetails, 'Advanced Settings details element should exist.');
    $this->getSession()->getDriver()->executeScript(
      "document.querySelector('[data-drupal-selector=\"edit-automator-advanced\"]').scrollIntoView({block: 'center'});"
    );
    $advancedDetails->click();
    $this->takeScreenshot('4_advanced_settings_with_provider');

    // Assert the warning message is NOT displayed.
    $this->assertSession()->pageTextNotContains('No AI providers are configured for');

    // Assert the provider select field IS present with the EchoAI option.
    $providerSelect = $page->findField('automator_ai_provider');
    $this->assertNotNull($providerSelect, 'The AI Provider select should be present when providers are available.');

    // Scroll to the provider select to make it visible in the screenshot.
    $this->getSession()->getDriver()->executeScript(
      "document.querySelector('[data-drupal-selector=\"edit-automator-ai-provider\"]').scrollIntoView({block: 'center'});"
    );

    // Verify EchoAI is an available option.
    $options = $providerSelect->findAll('css', 'option');
    $optionValues = [];
    foreach ($options as $option) {
      $optionValues[] = $option->getValue();
    }
    $this->assertContains('echoai', $optionValues, 'EchoAI should be available as a provider option.');

    $this->takeScreenshot('5_provider_select_visible');
  }

}
