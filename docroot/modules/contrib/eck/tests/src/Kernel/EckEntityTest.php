<?php

namespace Drupal\Tests\eck\Kernel;

use Drupal\eck\Entity\EckEntity;
use Drupal\eck\Entity\EckEntityBundle;
use Drupal\eck\Entity\EckEntityType;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the ECK entity class.
 *
 * @group eck
 * @see \Drupal\eck\Entity\EckEntity
 */
class EckEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'eck', 'field'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('eck');
  }

  /**
   * Tests some of the ECK entity methods.
   */
  public function testEckMethods() {
    // Entity without base fields.
    $eck_type_1 = $this->createEckEntityType('no_fields');
    $entity_1 = $this->createEckEntity($eck_type_1);
    $this->assertNull($entity_1->getChangedTime());
    $this->assertNull($entity_1->getCreatedTime());

    // Entity with base fields.
    $eck_type_2 = $this->createEckEntityType('with_fields', ['created', 'changed']);
    $entity_2 = $this->createEckEntity($eck_type_2);

    $this->assertNotNull($entity_2->getChangedTime());
    $this->assertNotNull($entity_2->getCreatedTime());
  }

  /**
   * Creates ECK entity.
   */
  protected function createEckEntity(EckEntityType $entity_type, $values = []) {
    $values = [
      'entity_type' => $entity_type->id(),
      'type' => $entity_type->id(),
    ] + $values;
    $entity = EckEntity::create($values);
    return $entity;
  }

  /**
   * Creates ECK entity type.
   *
   * @return \Drupal\eck\Entity\EckEntityType
   */
  protected function createEckEntityType($id, $base_fields = []) {
    $entity_type = EckEntityType::create([
      'id' => $id,
      'label' => $this->randomString(),
    ]);
    $entity_type->save();

    // Create bundle with the same ID.
    EckEntityBundle::create([
      'id' => $id,
      'type' => $this->randomString(),
    ]);

    // Add requested base fields.
    $allowed_base_fields = ['created', 'changed', 'uid', 'title', 'status'];
    $enabled_fields = array_intersect($base_fields, $allowed_base_fields);
    $config = \Drupal::configFactory()->getEditable('eck.eck_entity_type.' . $entity_type->id());
    foreach ($enabled_fields as $field_name) {
      $config->set($field_name, TRUE);
    }
    $config->save();

    // Clear entity definitions cache to find definition of our new entity type.
    \Drupal::entityTypeManager()->clearCachedDefinitions();

    return $entity_type;
  }

}
