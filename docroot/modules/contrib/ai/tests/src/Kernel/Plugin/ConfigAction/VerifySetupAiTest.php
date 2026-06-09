<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\ConfigAction;

use Drupal\KernelTests\KernelTestBase;

/**
 * This tests the verify setup ai recipe action.
 *
 * @coversDefaultClass \Drupal\ai\Plugin\ConfigAction\VerifySetupAi
 *
 * @group ai
 */
class VerifySetupAiTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'not_setup_provider',
    'ai_test',
    'ai_search',
    'search_api',
    'key',
    'system',
  ];

  /**
   * The VerifySetupAi action plugin.
   *
   * @var \Drupal\ai\Plugin\ConfigAction\VerifySetupAi
   */
  protected $action;

  /**
   * Setup the test.
   */
  protected function setUp(): void {
    parent::setUp();

    // Set up the action.
    $this->action = \Drupal::service('plugin.manager.config_action')->createInstance('verifySetupAi');
  }

  /**
   * Test with a provider that is not setup.
   */
  public function testNoProviderSetup(): void {
    // This should throw an error because the provider is not set up.
    $this->expectException(\InvalidArgumentException::class);
    $this->action->apply('ai.settings', [
      'provider_is_setup' => ['not_setup_provider'],
    ]);
    $this->assertTrue(TRUE, 'The action threw an error as expected when the provider is not set up.');
  }

  /**
   * Test with a provider that is set up.
   */
  public function testProviderSetup(): void {
    // This should not throw an error because the provider is set up.
    $this->action->apply('ai.settings', [
      'provider_is_setup' => ['echoai'],
    ]);
    $this->assertTrue(TRUE, 'The provider is set up and the action did not throw an error.');
  }

  /**
   * Test with a operation type that doesn't have a provider.
   */
  public function testProviderDoesNotExist(): void {
    // This should throw an error because the operation type does not exist.
    $this->expectException(\InvalidArgumentException::class);
    $this->action->apply('ai.settings', [
      'operation_type_has_provider' => ['something_funny'],
    ]);
    $this->assertTrue(TRUE, 'The action threw an error as expected when the provider does not exist.');
  }

  /**
   * Test with a operation type that has a provider.
   */
  public function testProviderExists(): void {
    // This should not throw an error because the operation type has a provider.
    $this->action->apply('ai.settings', [
      'operation_type_has_provider' => ['chat'],
    ]);
    $this->assertTrue(TRUE, 'The operation type has a provider and the action did not throw an error.');
  }

  /**
   * Test with a operation type that has a default model.
   */
  public function testOperationTypeHasDefaultModel(): void {
    // Setup default model for the operation type.
    \Drupal::service('ai.provider')->defaultIfNone('chat', 'echoai', 'chat-super');
    // This should not throw an error.
    $this->action->apply('ai.settings', [
      'operation_type_has_default_model' => ['chat'],
    ]);
    $this->assertTrue(TRUE, 'The operation type has a default model and the action did not throw an error.');
  }

  /**
   * Test with a operation type that does not have a default model.
   */
  public function testOperationTypeDoesNotHaveDefaultModel(): void {
    // This should throw an error.
    $this->expectException(\InvalidArgumentException::class);
    $this->action->apply('ai.settings', [
      'operation_type_has_default_model' => ['text_classification'],
    ]);
    $this->assertTrue(TRUE, 'The action threw an error as expected when the operation type does not have a default model.');
  }

  /**
   * Test with a string instead of an array.
   */
  public function testInvalidValueType(): void {
    // This should throw an error because the value is not an array.
    $this->expectException(\AssertionError::class);
    $this->action->apply('ai.settings', [
      'provider_is_setup' => 'text_classification',
    ]);
    $this->assertTrue(TRUE, 'The action threw an error as expected when the value is not an array.');
    $this->expectException(\AssertionError::class);
    $this->action->apply('ai.settings', [
      'operation_type_has_provider' => 'text_classification',
    ]);
    $this->assertTrue(TRUE, 'The action threw an error as expected when the value is not an array.');
    $this->expectException(\AssertionError::class);
    $this->action->apply('ai.settings', [
      'operation_type_has_default_model' => 'text_classification',
    ]);
    $this->assertTrue(TRUE, 'The action threw an error as expected when the value is not an array.');
  }

  /**
   * Test with an associative array instead of a numeric array.
   */
  public function testInvalidArrayType(): void {
    // This should throw an error because the value is not a numeric array.
    $this->expectException(\AssertionError::class);
    $this->action->apply('ai.settings', [
      'provider_is_setup' => ['text_classification' => 'test'],
    ]);
    $this->assertTrue(TRUE, 'The action threw an error as expected when the value is not a numeric array.');
    $this->expectException(\AssertionError::class);
    $this->action->apply('ai.settings', [
      'operation_type_has_provider' => ['text_classification' => 'test'],
    ]);
    $this->assertTrue(TRUE, 'The action threw an error as expected when the value is not a numeric array.');
    $this->expectException(\AssertionError::class);
    $this->action->apply('ai.settings', [
      'operation_type_has_default_model' => ['text_classification' => 'test'],
    ]);
    $this->assertTrue(TRUE, 'The action threw an error as expected when the value is not a numeric array.');
  }

  /**
   * Test with setting all empty.
   */
  public function testEmptySettings(): void {
    // This should throw an error because no settings are provided.
    $this->expectException(\InvalidArgumentException::class);
    $this->action->apply('ai.settings', []);
    $this->assertTrue(TRUE, 'The action threw an error as expected when no settings are provided.');
  }

  /**
   * Test if a vdb server is set up.
   */
  public function testVdbServerSetup(): void {
    // Flush the caches to ensure the settings are reloaded.
    \Drupal::service('cache.render')->deleteAll();
    \Drupal::service('cache.discovery')->deleteAll();
    \Drupal::service('cache.config')->deleteAll();
    // This should not throw an error because the vdb server is set up.
    $this->action->apply('ai.settings', [
      'vdb_provider_is_setup' => [
        'echo_db',
      ],
    ]);
    $this->assertTrue(TRUE, 'The vdb server is set up and the action did not throw an error.');
  }

  /**
   * Test if a vdb server is not set up.
   */
  public function testVdbServerNotSetup(): void {
    // This should throw an error because the vdb server is not set up.
    $this->expectException(\InvalidArgumentException::class);
    $this->action->apply('ai.settings', [
      'vdb_provider_is_setup' => [
        'echo_db_something_else',
      ],
    ]);
    $this->assertTrue(TRUE, 'The action threw an error as expected when the vdb server is not set up.');
  }

}
