<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Config;

use Drupal\Core\Config\Schema\SchemaIncompleteException;
use Drupal\ai\Form\AiSettingsForm;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Tests ai.settings schema when third-party modules extend operation types.
 *
 * @coversDefaultClass \Drupal\ai\Form\AiSettingsForm
 *
 * @group ai
 */
class AiSettingsSchemaExtensionTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'ai_settings_schema_test',
    'key',
    'system',
  ];

  /**
   * The AI settings form.
   *
   * @var \Drupal\ai\Form\AiSettingsForm
   */
  protected AiSettingsForm $settingsForm;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai']);
    \Drupal::service('cache.default')->delete('ai_operation_types');

    $this->settingsForm = AiSettingsForm::create(\Drupal::getContainer());
  }

  /**
   * Tests config with an undeclared operation type fails schema validation.
   *
   * This is the negative case: a third-party module adds an operation type via
   * hook_ai_operation_types_alter but forgets to extend ai.settings schema via
   * hook_config_schema_info_alter. Any config saved for that undeclared key
   * must be rejected by the ConfigSchemaChecker at save time.
   */
  public function testSchemaInvalidWithMissingThirdPartySchemaDefinition(): void {
    // The ConfigSchemaChecker development service throws on save when a key
    // has no schema definition. Assert that saving config for an undeclared
    // operation type (text_to_video is not in ai.schema.yml and is not
    // registered by any enabled test module) is rejected immediately.
    $this->expectException(SchemaIncompleteException::class);
    $this->expectExceptionMessageMatches('/text_to_video missing schema/');

    $this->config('ai.settings')
      ->set('default_providers.text_to_video', [
        'provider_id' => 'some_provider',
        'model_id' => 'some_model',
      ])
      ->save();
  }

  /**
   * Tests schema validation for a third-party operation type.
   */
  public function testSchemaWithThirdPartyOperationType(): void {
    $operation_type_ids = array_column($this->settingsForm->getOperationTypes(), 'id');
    $this->assertContains('text_to_code', $operation_type_ids);

    $schema_definition = \Drupal::service('config.typed')->getDefinition('ai.settings');
    $this->assertSame(
      'ai_settings_schema_test.default_operation_provider',
      $schema_definition['mapping']['default_providers']['mapping']['text_to_code']['type'] ?? NULL
    );

    $form_state = new FormState();
    $form = $this->settingsForm->buildForm([], $form_state);

    $this->assertArrayHasKey('text_to_code', $form['installed_capabilities']['table']);
    $this->assertArrayHasKey(
      'echoai',
      $form['installed_capabilities']['table']['text_to_code']['provider']['operation__text_to_code']['#options']
    );

    $submit_state = new FormState();
    $submit_state->setValues([
      'vdb_table' => [
        'vdb' => [
          'provider' => [
            'default_vdb_provider' => '',
          ],
        ],
      ],
      'operation__text_to_code' => 'echoai',
      'model__text_to_code' => 'gpt-test',
    ]);

    $submit_form = [];
    $this->settingsForm->submitForm($submit_form, $submit_state);

    $this->assertConfigSchemaByName('ai.settings');
    $this->assertSame([
      'text_to_code' => [
        'provider_id' => 'echoai',
        'model_id' => 'gpt-test',
      ],
    ], $this->config('ai.settings')->get('default_providers'));
  }

}
