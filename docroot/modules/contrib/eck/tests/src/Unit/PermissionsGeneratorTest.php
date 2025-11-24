<?php

namespace Drupal\Tests\eck\Unit;

use Drupal\eck\PermissionsGenerator;

/**
 * Tests the form element implementation.
 *
 * @group eck
 */
class PermissionsGeneratorTest extends UnitTestBase {

  /**
   * The subject under test.
   *
   * @var \Drupal\eck\PermissionsGenerator
   */
  private $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->sut = $this->createNewSubjectUnderTest();
  }

  /**
   * Creates a PermissionsGenerator to be used in the tests.
   *
   * @return \Drupal\eck\PermissionsGenerator
   *   The created PermissionsGenerator instance.
   */
  private function createNewSubjectUnderTest() {
    $permissionsGenerator = new PermissionsGenerator();
    $permissionsGenerator->setStringTranslation($this->getStringTranslationStub());

    return $permissionsGenerator;
  }

  /**
   * Tests that no permissions are created if no entity types are defined.
   *
   * @test
   */
  public function generatesNoPermissionsIfNoEntityTypesAreDefined() {
    $this->assertSame([], $this->sut->entityPermissions());
  }

  /**
   * Tests permission creation for a single entity type.
   *
   * @test
   */
  public function givenSingleEntityTypeGeneratesCorrectPermissions() {
    $this->addEntityToStorage($this->createEckEntityType('entity_type'));

    $permissions = $this->sut->entityPermissions();
    $this->assertCreatePermission($permissions);
    $this->assertGlobalPermissions($permissions);
    $this->assertOwnerPermissions($permissions);
  }

  /**
   * Tests permission creation for an entity type with an author field.
   *
   * @test
   */
  public function givenSingleEntityTypeWithAuthorFieldGeneratesCorrectPermissions() {
    $this->addEntityToStorage($this->createEckEntityType('entity_type', ['uid' => TRUE]));

    $permissions = $this->sut->entityPermissions();
    $this->assertCreatePermission($permissions);
    $this->assertGlobalPermissions($permissions);
    $this->assertOwnerPermissions($permissions);
  }

  /**
   * Tests permission creation for entity types with mixed settings.
   *
   * @test
   */
  public function givenMultipleEntityTypesWithMixedSettingsGeneratesCorrectPermissions() {
    $this->addEntityToStorage($this->createEckEntityType('entity_type'));
    $this->addEntityToStorage($this->createEckEntityType('another_type', ['uid' => TRUE]));

    $permissions = $this->sut->entityPermissions();
    $this->assertCreatePermission($permissions);
    $this->assertGlobalPermissions($permissions);
    $this->assertOwnerPermissions($permissions);
  }

  /**
   * Asserts that the correct create permission is returned.
   */
  protected function assertCreatePermission($permissions) {
    foreach ($this->entities as $id => $entity) {
      $this->assertArrayHasKey("create {$id} entities", $permissions);
    }
  }

  /**
   * Asserts that the correct global permissions are returned.
   */
  protected function assertGlobalPermissions($permissions) {
    foreach ($this->entities as $id => $entity) {
      $this->assertArrayHasKey("edit any {$id} entities", $permissions);
      $this->assertArrayHasKey("delete any {$id} entities", $permissions);
      $this->assertArrayHasKey("view any {$id} entities", $permissions);
    }
  }

  /**
   * Asserts that the correct owner permissions are returned.
   */
  protected function assertOwnerPermissions($permissions) {
    foreach ($this->entities as $id => $entity) {
      /** @var \Drupal\eck\Entity\EckEntityType $entity */
      if ($entity->hasAuthorField()) {
        $this->assertArrayHasKey("edit own {$id} entities", $permissions);
        $this->assertArrayHasKey("delete own {$id} entities", $permissions);
        $this->assertArrayHasKey("view own {$id} entities", $permissions);
      }
      else {
        $this->assertArrayNotHasKey("edit own {$id} entities", $permissions);
        $this->assertArrayNotHasKey("delete own {$id} entities", $permissions);
        $this->assertArrayNotHasKey("view own {$id} entities", $permissions);
      }
    }
  }

}
