<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Config;

use Drupal\ai\Form\AiSettingsForm;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Tests ai.settings schema coverage.
 *
 * @coversDefaultClass \Drupal\ai\Form\AiSettingsForm
 *
 * @group ai
 */
class AiSettingsSchemaTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
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
   * Operation type IDs used by ai.settings default providers.
   *
   * @var string[]
   */
  protected array $operationTypeIds = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai']);

    $this->settingsForm = AiSettingsForm::create(\Drupal::getContainer());
    foreach ($this->settingsForm->getOperationTypes() as $operation_type) {
      $this->operationTypeIds[] = $operation_type['id'];
    }
    $this->assertNotEmpty($this->operationTypeIds, 'Expected at least one AI operation type.');
  }

  /**
   * Tests schema validation after saving populated settings.
   */
  public function testSchemaWithPopulatedValues(): void {
    $form_values = $this->buildFormValues();
    $this->submitSettingsForm($form_values);

    $this->assertConfigSchemaByName('ai.settings');
  }

  /**
   * Tests schema validation after saving empty/default operation values.
   */
  public function testSchemaWithEmptyOperationValues(): void {
    $selected_operation_type = $this->operationTypeIds[0];

    $form_values = $this->buildFormValues();
    foreach ($this->operationTypeIds as $operation_type) {
      if ($operation_type === $selected_operation_type) {
        continue;
      }
      $form_values['operation__' . $operation_type] = '';
      $form_values['model__' . $operation_type] = '';
    }

    $this->submitSettingsForm($form_values);
    $this->assertConfigSchemaByName('ai.settings');

    $default_providers = $this->config('ai.settings')->get('default_providers');
    $this->assertCount(1, $default_providers);
    $this->assertArrayHasKey($selected_operation_type, $default_providers);
    foreach ($this->operationTypeIds as $operation_type) {
      if ($operation_type !== $selected_operation_type) {
        $this->assertArrayNotHasKey($operation_type, $default_providers);
      }
    }
  }

  /**
   * Tests that default provider schema keys match operation type IDs.
   */
  public function testDefaultProviderSchemaOperationTypeParity(): void {
    $schema_definition = \Drupal::service('config.typed')->getDefinition('ai.settings');
    $schema_operation_type_ids = array_keys($schema_definition['mapping']['default_providers']['mapping'] ?? []);

    $expected = $this->operationTypeIds;
    sort($expected);
    sort($schema_operation_type_ids);

    $this->assertSame(
      $expected,
      $schema_operation_type_ids,
      'The ai.settings default provider schema keys must match all operation types.'
    );
  }

  /**
   * Tests that AI settings form builds with string allowed_hosts config values.
   */
  public function testBuildFormWithStringAllowedHostsValue(): void {
    // Write directly to the config storage to bypass schema validation and
    // simulate a legacy string value that pre-existing sites may have stored.
    $storage = \Drupal::service('config.storage');
    $data = $storage->read('ai.settings') ?: [];
    $data['allowed_hosts'] = '';
    $storage->write('ai.settings', $data);

    $form_state = new FormState();
    $form = $this->settingsForm->buildForm([], $form_state);

    $this->assertSame(
      '',
      $form['advanced_settings']['allowed_host_wrapper']['allowed_hosts']['#default_value']
    );
  }

  /**
   * Builds form values for AI settings submit.
   *
   * @return array
   *   Form values for submitting AI settings.
   */
  protected function buildFormValues(): array {
    $form_values = [
      'vdb_table' => [
        'vdb' => [
          'provider' => [
            'default_vdb_provider' => $this->randomMachineName(),
          ],
        ],
      ],
    ];

    foreach ($this->operationTypeIds as $operation_type) {
      $form_values['operation__' . $operation_type] = 'provider_' . $this->randomMachineName();
      $form_values['model__' . $operation_type] = 'model_' . $this->randomMachineName();
    }

    return $form_values;
  }

  /**
   * Submits AI settings form values.
   *
   * @param array $form_values
   *   Form values to submit.
   */
  protected function submitSettingsForm(array $form_values): void {
    $form_state = new FormState();
    $form_state->setValues($form_values);

    $form = [];
    $this->settingsForm->submitForm($form, $form_state);
  }

}
