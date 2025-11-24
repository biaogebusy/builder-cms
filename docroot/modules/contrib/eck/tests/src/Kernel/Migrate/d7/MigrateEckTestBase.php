<?php

namespace Drupal\Tests\eck\Kernel\Migrate\d7;

use Drupal\eck\Entity\EckEntity;
use Drupal\eck\Entity\EckEntityBundle;
use Drupal\eck\Entity\EckEntityType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Base class for ECK migration tests.
 */
abstract class MigrateEckTestBase extends MigrateDrupal7TestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = \Drupal::entityTypeManager();
  }

  /**
   * Gets the path to the fixture file.
   */
  protected function getFixtureFilePath() {
    return __DIR__ . '/../../../../fixtures/drupal7.php';
  }

  /**
   * Executes all field migrations.
   */
  protected function migrateFields() {
    $this->executeMigration('d7_field');
    $this->migrateContentTypes();
    $this->executeMigrations(['d7_field_instance']);
  }

  /**
   * Asserts an Eck config entity type.
   *
   * @param array $type
   *   An array of eck type information.
   *   - id: The entity type id.
   *   - label: The entity label.
   *   - langcode: The entity language code.
   */
  public function assertEckEntityType(array $type) {
    $entity = eckEntityType::load($type['id']);
    $this->assertInstanceOf(eckEntityType::class, $entity);
    $this->assertSame($type['label'], $entity->label());
    $this->assertSame($type['langcode'], $entity->language()->getId());
  }

  /**
   * Asserts an Eck bundle config entity.
   *
   * @param array $bundle
   *   An array of eck bundle information.
   *   - id: The entity type id.
   *   - label: The entity label.
   *   - description: The entity description.
   *   - langcode: The entity language code.
   */
  public function assertEckBundle(array $bundle) {
    $entity = eckEntityBundle::load($bundle['type']);
    $this->assertInstanceOf(eckEntityBundle::class, $entity);
    $this->assertSame($bundle['name'], $entity->name);
    $this->assertSame($bundle['description'], $entity->description);
    $this->assertSame($bundle['langcode'], $entity->language()->getId());
  }

  /**
   * Asserts an Eck entity.
   *
   * @param array $eck
   *   An array of eck information.
   *   - id: The entity id.
   *   - label: The entity label.
   *   - langcode: The entity language code.
   *   - fields: An array of field names and field values.
   */
  public function assertEck(array $eck) {
    $message = "Failure for eck entity type '" . $eck['type'] . "' with id of '" . $eck['id'] . "'";
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager
      ->getStorage($eck['type'])
      ->load($eck['id']);
    $this->assertInstanceOf(EckEntity::class, $entity, $message);
    $this->assertSame($eck['label'], $entity->label(), $message);
    $this->assertSame($eck['bundle'], $entity->bundle(), $message);
    $this->assertSame($eck['langcode'], $entity->language()
      ->getId(), $message);
    foreach ($eck['fields'] as $name => $value) {
      $this->assertSame($value, $entity->get($name)->getValue(), $message);
    }

    // Verify translations.
    if (!empty($eck['translations'])) {
      foreach ($eck['translations'] as $language => $translation_data) {
        $this->assertTrue($entity->hasTranslation($language));

        $translation = $entity->getTranslation($language);
        foreach ($translation_data['fields'] as $name => $value) {
          $this->assertSame($value, $translation->get($name)->getValue(), $message);
        }
      }
    }
  }

  /**
   * Asserts various aspects of a field_storage_config entity.
   *
   * @param string $id
   *   The entity ID in the form ENTITY_TYPE.FIELD_NAME.
   * @param string $expected_type
   *   The expected field type.
   * @param bool $expected_translatable
   *   Whether or not the field is expected to be translatable.
   * @param int $expected_cardinality
   *   The expected cardinality of the field.
   */
  protected function assertFieldStorage($id, $expected_type, $expected_translatable, $expected_cardinality) {
    list($expected_entity_type, $expected_name) = explode('.', $id);

    /** @var \Drupal\field\FieldStorageConfigInterface $field */
    $field = FieldStorageConfig::load($id);
    $this->assertInstanceOf(FieldStorageConfigInterface::class, $field);
    $this->assertEquals($expected_name, $field->getName());
    $this->assertEquals($expected_type, $field->getType());
    $this->assertEquals($expected_translatable, $field->isTranslatable());
    $this->assertEquals($expected_entity_type, $field->getTargetEntityTypeId());

    if ($expected_cardinality === 1) {
      $this->assertFalse($field->isMultiple());
    }
    else {
      $this->assertTrue($field->isMultiple());
    }
    $this->assertEquals($expected_cardinality, $field->getCardinality());
  }

  /**
   * Asserts various aspects of a field config entity.
   *
   * @param string $id
   *   The entity ID in the form ENTITY_TYPE.BUNDLE.FIELD_NAME.
   * @param string $expected_label
   *   The expected field label.
   * @param string $expected_field_type
   *   The expected field type.
   * @param bool $is_required
   *   Whether or not the field is required.
   * @param bool $expected_translatable
   *   Whether or not the field is expected to be translatable.
   */
  protected function assertFieldInstance($id, $expected_label, $expected_field_type, $is_required, $expected_translatable) {
    list($expected_entity_type, $expected_bundle, $expected_name) = explode('.', $id);

    /** @var \Drupal\field\FieldConfigInterface $field */
    $field = FieldConfig::load($id);
    $this->assertInstanceOf(FieldConfigInterface::class, $field);
    $this->assertEquals($expected_label, $field->label());
    $this->assertEquals($expected_field_type, $field->getType());
    $this->assertEquals($expected_entity_type, $field->getTargetEntityTypeId());
    $this->assertEquals($expected_bundle, $field->getTargetBundle());
    $this->assertEquals($expected_name, $field->getName());
    $this->assertEquals($is_required, $field->isRequired());
    $this->assertEquals($expected_entity_type . '.' . $expected_name, $field->getFieldStorageDefinition()
      ->id());
    $this->assertEquals($expected_translatable, $field->isTranslatable());
  }

}
