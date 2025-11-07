<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Kernel;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the import policy plugin manager.
 *
 * @group entity_share
 * @group entity_share_client
 */
class ImportPolicyTest extends KernelTestBase {
  use StringTranslationTrait;

  /**
   * The import policies manager.
   *
   * @var \Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager
   */
  protected $policiesManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'jsonapi',
    'entity_share_client',
    'entity_share_client_import_policies_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->policiesManager = $this->container->get('plugin.manager.entity_share_client_policy');
  }

  /**
   * Tests that policies can be provided by YAML files.
   */
  public function testDetectedPolicies() {
    $definitions = $this->policiesManager->getDefinitions();

    $this->assertEquals(3, count($definitions), 'There are three policies detected.');

    $expectations = [
      'default' => [
        'label' => $this->t('Default'),
        'id' => 'default',
        'provider' => 'entity_share_client',
      ],
      'create_only' => [
        'label' => $this->t('Create only'),
        'id' => 'create_only',
        'provider' => 'entity_share_client',
      ],
      'test' => [
        'label' => $this->t('Test'),
        'id' => 'test',
        'provider' => 'entity_share_client_import_policies_test',
      ],
    ];
    foreach ($expectations as $policy_id => $expected_policy_structure) {
      foreach ($expected_policy_structure as $key => $value) {
        $this->assertEquals($value, $definitions[$policy_id][$key]);
      }
    }
  }

}
