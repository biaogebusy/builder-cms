<?php

namespace Drupal\Tests\ai_content_suggestions\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Test so calling the API endpoints works via the base class.
 *
 * @group ai_content_suggestions
 */
class TestApiEndpointTest extends KernelTestBase {

  /**
   * Modules to enable before running the tests.
   *
   * @var array
   */
  protected static $modules = ['system', 'user', 'key', 'ai', 'ai_content_suggestions', 'ai_test'];

  /**
   * Setup the test environment.
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup so that the echoai is used as the default provider.
    $this->container->get('config.factory')
      ->getEditable('ai_content_suggestions.settings')
      ->set('plugins', [
        'summarise' => 'echoai__gpt-test',
      ])
      ->save();

    $this->installEntitySchema('ai_mock_provider_result');
  }

  /**
   * Tests so the summary can be called.
   *
   * This test uses the Summarize.yml for the request/response.
   */
  public function testApiCanBeCalled() {
    // Load the summary plugin.
    $plugin = \Drupal::service('plugin.manager.ai_content_suggestions')->createInstance('summarise');
    // Run the sendChat function.
    $prompt = "Create a detailed summary of the following text in less than 130 words using the same language as the following text:

Monkeys are primates that likes to eat bananas.";
    $response = $plugin->sendChat($prompt);
    // Assert that we got the right response.
    $this->assertNotEmpty($response);
    $this->assertStringContainsString('This is my Monkey summary.', $response);
  }

}
