<?php

namespace Drupal\Tests\ai_assistant_api\FunctionalJavascriptTests\Form;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Functional test for Ajax loading the VDB providers vectors.
 *
 * @coversDefaultClass \Drupal\ai_assistant_api\Form\AiAssistantForm
 *
 * @group ai_assistant_api
 */
class AiAssistantFormTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'ai_assistant_api',
    'file',
    'user',
    'field',
    'system',
  ];

  /**
   * Themes to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'claro';

  /**
   * Test the Ajax form interaction.
   */
  public function testAjaxForm() {
    try {
      // Login as admin.
      $this->drupalLogin($this->createUser(['administer ai_assistant']));

      // Visit the page where the form is displayed.
      $this->drupalGet('/admin/config/ai/ai-assistant/add');

      // Assert the select form elements are present.
      $this->assertSession()->fieldExists('llm_ai_provider');

      // Choose the OpenAI provider from the select field.
      $this->getSession()->getPage()->selectFieldOption('llm_ai_provider', 'echoai');

      // Wait for the Ajax request to complete.
      $this->assertSession()->assertWaitOnAjaxRequest();

      // After Ajax completes, check if the model field is present.
      $this->assertSession()->fieldExists('llm_ai_model');

      // Choose GPT 3.5 from the select field.
      $this->getSession()->getPage()->selectFieldOption('llm_ai_model', 'gpt-test');

      // Wait for the Ajax request to complete.
      $this->assertSession()->assertWaitOnAjaxRequest();

      // The configuration of max tokens should be present.
      $this->assertSession()->fieldExists('llm_ajax_prefix_configuration_max_tokens');
    }
    catch (\Exception $e) {
      $webroot = getcwd();

      // Take a screenshot if any exception occurs.
      if ($webroot) {
        $dir = $webroot . '/sites/default/files/simpletest/';
        // Create if does not exist.
        if (!file_exists($dir)) {
          mkdir($dir, 0777, TRUE);
        }
        $this->createScreenshot($dir . 'testAjaxForm.png');
      }

      throw $e;
    }
  }

}
