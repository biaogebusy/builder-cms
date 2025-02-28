<?php

namespace Drupal\Tests\eck\Unit;

use Doctrine\Common\Annotations\SimpleAnnotationReader;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\Annotation\ConfigEntityType;
use Drupal\eck\Entity\EckEntity;
use Drupal\eck\Entity\EckEntityType;

/**
 * Tests eck entities.
 *
 * @group eck
 */
class EckEntityTest extends UnitTestBase {

  /**
   * Provides test base field definition data.
   *
   * @return array
   *   The test data.
   */
  public function baseFieldDefinitionTestDataProvider() {
    return [
      'default' => [
        'config' => [],
        'expectedFieldIds' => [
          'id',
          'uuid',
          'langcode',
          'type',
        ],
      ],
      'with title field' => [
        'config' => ['title' => TRUE],
        'expectedFieldIds' => [
          'id',
          'uuid',
          'langcode',
          'type',
          'title',
        ],
      ],
      'with uid field' => [
        'config' => ['uid' => TRUE],
        'expectedFieldIds' => [
          'id',
          'uuid',
          'langcode',
          'type',
          'uid',
        ],
      ],
      'with created field' => [
        'config' => ['created' => TRUE],
        'expectedFieldIds' => [
          'id',
          'uuid',
          'langcode',
          'type',
          'created',
        ],
      ],
      'with changed field' => [
        'config' => ['changed' => TRUE],
        'expectedFieldIds' => [
          'id',
          'uuid',
          'langcode',
          'type',
          'changed',
        ],
      ],
    ];
  }

  /**
   * Tests base field definitions.
   *
   * @param array $config
   *   The configuration.
   * @param array $expectedBaseFieldDefinitionIds
   *   The expected base field definition ids.
   *
   * @dataProvider baseFieldDefinitionTestDataProvider
   *
   * @throws \ReflectionException
   */
  public function testBaseFieldDefinitions(array $config, array $expectedBaseFieldDefinitionIds) {
    $configs = [
      'eck.eck_entity_type.eck_entity_type' => $config,
    ];
    $this->registerServiceWithContainerMock('config.factory', $this->getConfigFactoryStub($configs));

    $annotationReader = new SimpleAnnotationReader();
    $annotationReader->addNamespace((new \ReflectionClass(Translation::class))->getNamespaceName());
    $annotationReader->addNamespace((new \ReflectionClass(ConfigEntityType::class))->getNamespaceName());

    $definition = $annotationReader->getClassAnnotation(new \ReflectionClass(EckEntityType::class), ConfigEntityType::class);

    $this->assertArrayKeysEqual($expectedBaseFieldDefinitionIds, EckEntity::baseFieldDefinitions($definition->get()));
  }

}
