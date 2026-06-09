<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Schema;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the ai.provider_config config schema type.
 *
 * The ai.provider_config schema type provides a standardized way to configure
 * AI providers across modules. This test verifies that the schema definition
 * is structurally valid and that config data conforming to it passes schema
 * validation — including for the 'configuration' key, which holds arbitrary
 * provider-specific data.
 *
 * @group ai
 */
class AiProviderConfigSchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai'];

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected TypedConfigManagerInterface $typedConfigManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->typedConfigManager = $this->container->get('config.typed');
  }

  /**
   * Tests that provider config with non-null configuration validates.
   *
   * The 'configuration' key holds arbitrary provider-specific data. The schema
   * must be able to process this without throwing a \LogicException due to an
   * invalid mapping definition.
   */
  public function testProviderConfigWithConfiguration(): void {
    $this->assertTrue(
      $this->typedConfigManager->hasConfigSchema('ai.provider_config'),
      'The ai.provider_config schema type should be defined.'
    );

    $data = [
      'use_default' => FALSE,
      'provider' => 'openai',
      'model' => 'gpt-4',
      'config' => [
        'temperature' => 0.7,
        'max_tokens' => 1024,
      ],
    ];

    // createFromNameAndData() builds the typed config tree, which
    // instantiates Mapping objects for each mapping-typed key. If the
    // 'configuration' key's schema definition is invalid (e.g. using
    // "type: mapping" with "mapping: type: ignore" instead of
    // "type: ignore"), this call throws a \LogicException in
    // Mapping::__construct().
    $typed = $this->typedConfigManager->createFromNameAndData(
      'ai.provider_config',
      $data
    );
    $violations = $typed->validate();

    // The ProviderModelDependency constraint should pass since both
    // provider_id and model_id are set.
    $this->assertCount(0, $violations, implode("\n", array_map(
      static fn($v) => $v->getMessage(),
      iterator_to_array($violations)
    )));
  }

  /**
   * Tests that provider config with an empty configuration array validates.
   *
   * This matches the typical default config file pattern where configuration
   * is set to an empty map ({}).
   */
  public function testProviderConfigWithEmptyConfiguration(): void {
    $data = [
      'use_default' => FALSE,
      'provider' => 'openai',
      'model' => 'gpt-4',
      'config' => [],
    ];

    $typed = $this->typedConfigManager->createFromNameAndData(
      'ai.provider_config',
      $data
    );
    $violations = $typed->validate();
    $this->assertCount(0, $violations, implode("\n", array_map(
      static fn($v) => $v->getMessage(),
      iterator_to_array($violations)
    )));
  }

  /**
   * Tests that provider config with NULL configuration validates.
   *
   * The 'configuration' key should accept NULL since it is marked as
   * nullable in the schema.
   */
  public function testProviderConfigWithNullConfiguration(): void {
    $data = [
      'use_default' => FALSE,
      'provider' => 'openai',
      'model' => 'gpt-4',
      'config' => NULL,
    ];

    $typed = $this->typedConfigManager->createFromNameAndData(
      'ai.provider_config',
      $data
    );
    $violations = $typed->validate();
    $this->assertCount(0, $violations, implode("\n", array_map(
      static fn($v) => $v->getMessage(),
      iterator_to_array($violations)
    )));
  }

}
