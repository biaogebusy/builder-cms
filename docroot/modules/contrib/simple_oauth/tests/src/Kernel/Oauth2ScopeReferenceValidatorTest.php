<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\simple_oauth\Entity\Oauth2Scope as Oauth2ScopeEntity;

/**
 * Tests validation constraints for Oauth2ScopeReferenceValidator.
 *
 * @group simple_oauth
 */
class Oauth2ScopeReferenceValidatorTest extends KernelTestBase {

  /**
   * The typed data manager to use.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected $typedData;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'image',
    'options',
    'serialization',
    'system',
    'simple_oauth',
    'simple_oauth_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oauth2_scope');
    $this->installEntitySchema('entity_test');
    $this->installConfig(['simple_oauth']);
    $this->installEntitySchema('user');
    $this->installConfig(['user']);
    $this->typedData = $this->container->get('typed_data_manager');

    Oauth2ScopeEntity::create([
      'name' => 'dynamic_scope',
    ])->save();
    Oauth2ScopeEntity::create([
      'name' => 'dynamic_scope:child',
    ])->save();
  }

  /**
   * Test reference to non-existing OAuth2 scope.
   */
  public function testOauth2ScopeReferenceNonExisting(): void {
    $entity = EntityTest::create();
    $entity->save();

    $definition = BaseFieldDefinition::create('oauth2_scope_reference')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $scope_id = 'non_existing_scope';
    $typed_data = $this->typedData->create($definition, [$scope_id]);
    $violations = $typed_data->validate();
    $violation = $violations[0];
    $this->assertEquals(t("The referenced OAuth2 scope '%id' does not exist.", ['%id' => $scope_id]), $violation->getMessage(), 'The message for invalid value is correct.');
    $this->assertEquals($typed_data, $violation->getRoot(), 'Violation root is correct.');
  }

  /**
   * Test validation constraint.
   */
  public function testValidation(): void {
    $entity = EntityTest::create();
    $entity->save();

    $definition = BaseFieldDefinition::create('oauth2_scope_reference')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $typed_data = $this->typedData->create($definition, [
      'dynamic_scope',
      'dynamic_scope_child',
    ]);
    $violations = $typed_data->validate();
    $this->assertEquals(0, $violations->count(), 'Validation passed for correct value.');
  }

}
