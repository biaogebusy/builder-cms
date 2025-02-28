<?php

namespace Drupal\Tests\replication\Kernel\Normalizer;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\entity_test\Entity\EntityTestMulRev;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the link serialization format.
 *
 * @group replication
 */
class LinkItemNormalizerTest extends NormalizerTestBase {

  use UserCreationTrait;

  protected $entityClass = 'Drupal\entity_test\Entity\EntityTest';

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  protected function setUp() {
    parent::setUp();

    $this->user = $this->createUser();
    $this->setCurrentUser($this->user);

    // Create a Link field for testing.
    FieldStorageConfig::create([
      'field_name' => 'field_test_link',
      'entity_type' => 'entity_test_mulrev',
      'type' => 'link',
      'cardinality' => 2,
      'translatable' => FALSE,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mulrev',
      'field_name' => 'field_test_link',
      'bundle' => 'entity_test_mulrev',
      'label' => 'Test link-field',
      'widget' => [
        'type' => 'link',
        'weight' => 0,
      ],
    ])->save();
  }

  /**
   * Tests field link normalization.
   */
  public function testLinkFieldNormalization() {
    // Create two entities that will be used as references fot the link field.
    $referenced_entity1 = EntityTestMulRev::create(
      [
        'name' => $this->randomMachineName(),
        'user_id' => 1,
      ]
    );
    $referenced_entity1->save();
    $referenced_entity2 = EntityTestMulRev::create(
      [
        'name' => $this->randomMachineName(),
        'user_id' => 1,
      ]
    );
    $referenced_entity2->save();

    // Create a test entity to serialize.
    $this->values = [
      'name' => $this->randomMachineName(),
      'user_id' => 1,
      'field_test_text' => [
        'value' => $this->randomMachineName(),
        'format' => 'full_html',
      ],
      'field_test_link' => [
        [
          'uri' => 'entity:entity_test_mulrev/manage/' . $referenced_entity1->id(),
          'options' => [],
        ],
        [
          'uri' => 'internal:/entity_test_mulrev/manage/' . $referenced_entity2->id(),
          'options' => [],
        ],
      ],
    ];
    $this->entity = EntityTestMulRev::create($this->values);
    $this->entity->save();

    list($i, $hash) = explode('-', $this->entity->_rev->value);
    $expected = [
      '@context' => [
        '_id' => '@id',
        '@language' => 'en'
      ],
      '@type' => 'entity_test_mulrev',
      'en' => [
        '@context' => [
          '@language' => 'en',
        ],
        'langcode' => [
          ['value' => 'en'],
        ],
        'name' => [
          ['value' => $this->values['name']],
        ],
        'type' => [
          ['value' => 'entity_test_mulrev'],
        ],
        'created' => [
          $this->formatExpectedTimestampItemValues($this->entity->created->value),
        ],
        'default_langcode' => [
          ['value' => TRUE],
        ],
        'user_id' => [
          [
            'entity_type_id' => 'user',
            'target_uuid' => $this->user->uuid(),
            'username' => $this->user->getAccountName(),
          ],
        ],
        '_rev' => [
          ['value' => $this->entity->_rev->value],
        ],
        'non_rev_field' => [],
        'field_test_text' => [
          [
            'value' => $this->values['field_test_text']['value'],
            'format' => $this->values['field_test_text']['format'],
          ],
        ],
        'field_test_link' => [
          [
            'uri' => 'entity:entity_test_mulrev/manage/' . $referenced_entity1->uuid(),
            'title' => NULL,
            'options' => [],
            'type' => 'entity_test_mulrev',
            '_entity_uuid' => $referenced_entity1->uuid(),
            '_entity_type' => $referenced_entity1->getEntityTypeId(),
            $referenced_entity1->getEntityType()->getKey('bundle') => $referenced_entity1->bundle(),
          ],
          [
            'uri' => 'internal:/entity_test_mulrev/manage/' . $referenced_entity2->uuid(),
            'title' => NULL,
            'options' => [],
            '_entity_uuid' => $referenced_entity2->uuid(),
            '_entity_type' => $referenced_entity2->getEntityTypeId(),
            $referenced_entity2->getEntityType()->getKey('bundle') => $referenced_entity2->bundle(),
          ],
        ],
      ],
      '_id' => $this->entity->uuid(),
      '_rev' => $this->entity->_rev->value,
      '_revisions' => [
        'start' => 1,
        'ids' => [$hash],
      ],
    ];

    // Get the minor version only from the \Drupal::VERSION string.
    $minor_version = substr(\Drupal::VERSION, 0, 3);

    if (version_compare($minor_version, '8.4', '>=')) {
      $expected['en']['revision_translation_affected'] = [['value' => TRUE]];
    }

    if (version_compare($minor_version, '8.5', '>=')) {
      $expected['en']['non_mul_field'] = [];
      $expected['en']['revision_default'] = [['value' => TRUE]];
      $expected['en']['field_test_text'][0]['processed'] = '';
    }

    // Test normalize.
    $normalized = $this->serializer->normalize($this->entity);
    foreach (array_keys($expected) as $key) {
      $this->assertEquals($expected[$key], $normalized[$key], "Field $key is normalized correctly.");
    }
    $this->assertEquals(array_diff_key($normalized, $expected), [], 'No unexpected data is added to the normalized array.');

    // Test denormalize.
    $context = ['workspace' => $this->container->get('workspace.manager')->getActiveWorkspace()];
    $denormalized = $this->serializer->denormalize($normalized, $this->entityClass, 'json', $context);
    $this->assertTrue($denormalized instanceof $this->entityClass, new FormattableMarkup('Denormalized entity is an instance of @class', ['@class' => $this->entityClass]));
    $this->assertSame($denormalized->getEntityTypeId(), $this->entity->getEntityTypeId(), 'Expected entity type found.');
    $this->assertSame($denormalized->bundle(), $this->entity->bundle(), 'Expected entity bundle found.');
    $this->assertSame($denormalized->uuid(), $this->entity->uuid(), 'Expected entity UUID found.');
    $expected_link_field_values = [
      [
        'uri' => 'entity:entity_test_mulrev/manage/' . $referenced_entity1->id(),
        'title' => NULL,
        'options' => [],
      ],
      [
        'uri' => 'internal:/entity_test_mulrev/manage/' . $referenced_entity2->id(),
        'title' => NULL,
        'options' => [],
      ],
    ];
    foreach ($denormalized->get('field_test_link')->getValue() as $key => $item) {
      $this->assertEquals($expected_link_field_values[$key], $item, "Field $key is normalized correctly.");
    }
  }

}
