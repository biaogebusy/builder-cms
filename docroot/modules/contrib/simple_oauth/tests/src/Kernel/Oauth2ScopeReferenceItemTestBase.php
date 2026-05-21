<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Test base class for OAuth2 scope reference field type.
 *
 * @group simple_oauth
 */
class Oauth2ScopeReferenceItemTestBase extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'consumers',
    'serialization',
    'system',
    'simple_oauth',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oauth2_scope');
    $this->installConfig(['simple_oauth']);
    $this->installEntitySchema('user');
    $this->installConfig(['user']);

    FieldStorageConfig::create([
      'field_name' => 'field_oauth2_scope_reference',
      'entity_type' => 'entity_test',
      'type' => 'oauth2_scope_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_oauth2_scope_reference',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Assert assigning and loading scopes.
   *
   * @param array $scope_ids
   *   Array with scope ids.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function assertOauth2ScopeReferenceItems(array $scope_ids): void {
    $entity = EntityTest::create([
      'field_oauth2_scope_reference' => $scope_ids,
    ]);
    $entity->save();

    $entity = EntityTest::load($entity->id());
    $this->assertInstanceOf(FieldItemListInterface::class, $entity->field_oauth2_scope_reference);
    $this->assertInstanceOf(FieldItemInterface::class, $entity->field_oauth2_scope_reference[0]);
    $this->assertFalse($entity->field_oauth2_scope_reference->isEmpty());

    foreach ($scope_ids as $delta => $scope_id) {
      $this->assertInstanceOf(Oauth2ScopeInterface::class, $entity->field_oauth2_scope_reference[$delta]->getScope());
      $this->assertInstanceOf(Oauth2ScopeInterface::class, $entity->get('field_oauth2_scope_reference')->getScopes()[$delta]);
      $this->assertEquals($scope_id, $entity->field_oauth2_scope_reference[$delta]->scope_id);
      $this->assertEquals($scope_id, $entity->get('field_oauth2_scope_reference')->getScopes()[$delta]->id());
    }

    // Test all the possible ways of assigning a scope id.
    $entity->field_oauth2_scope_reference = [['scope_id' => reset($scope_ids)]];
    $this->assertEquals($scope_ids[0], $entity->field_oauth2_scope_reference->first()->scope_id);

    $entity->set('field_oauth2_scope_reference', $scope_ids);
    $this->assertEquals($scope_ids[0], $entity->get('field_oauth2_scope_reference')->first()->scope_id);
  }

}
