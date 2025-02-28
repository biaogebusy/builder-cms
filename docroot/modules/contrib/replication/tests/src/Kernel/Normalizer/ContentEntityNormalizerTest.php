<?php

namespace Drupal\Tests\replication\Kernel\Normalizer;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\entity_test\Entity\EntityTestMulRev;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the content serialization format.
 *
 * @group replication
 */
class ContentEntityNormalizerTest extends NormalizerTestBase {

  protected $entityClass = 'Drupal\entity_test\Entity\EntityTest';

  protected function setUp() {
    parent::setUp();

    // Create a test entity to serialize.
    $this->values = [
      'name' => $this->randomMachineName(),
      'user_id' => 1,
      'field_test_text' => [
        'value' => $this->randomMachineName(),
        'format' => 'full_html',
      ],
    ];
    ConfigurableLanguage::createFromLangcode('ro')->save();

    $entity = EntityTestMulRev::create($this->values);
    $entity->save();

    // Save again.
    $entity->save();

    // Add Romanian translation.
    $romanian = $entity->addTranslation('ro');
    $romanian->name->value = $entity->name->value . '_ro';
    $romanian->field_test_text->value = $this->values['field_test_text']['value'] . '_ro';
    $romanian->field_test_text->format = 'full_html';
    $romanian->save();

    // Save again the original entity.
    $entity = EntityTestMulRev::load($entity->id());
    $entity->save();

    // Load again romanian translation and save a new revision.
    $romanian = $entity->getTranslation('ro');
    $romanian->save();

    $this->entity = EntityTestMulRev::load($entity->id());
    $this->romanian = $entity->getTranslation('ro');
  }

  public function testNormalizer() {
    $revs = EntityTestMulRev::load($this->entity->id())->_rev->revisions;
    // Test normalize.
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
          ['target_id' => $this->values['user_id']],
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
      ],
      'ro' => [
        '@context' => [
          '@language' => 'ro',
        ],
        'langcode' => [
          ['value' => 'ro'],
        ],
        'name' => [
          ['value' => $this->values['name'] . '_ro'],
        ],
        'type' => [
          ['value' => 'entity_test_mulrev'],
        ],
        'created' => [
          $this->formatExpectedTimestampItemValues($this->romanian->created->value),
        ],
        'default_langcode' => [
          ['value' => FALSE],
        ],
        'user_id' => [
          ['target_id' => $this->values['user_id']],
        ],
        '_rev' => [
          ['value' => $this->romanian->_rev->value],
        ],
        'non_rev_field' => [],
        'field_test_text' => [
          [
            'value' => $this->values['field_test_text']['value'] . '_ro',
            'format' => $this->values['field_test_text']['format'],
          ],
        ],
      ],
      '_id' => $this->entity->uuid(),
      '_rev' => $this->entity->_rev->value,
      '_revisions' => [
        'start' => count($revs),
        'ids' => $revs,
      ],
    ];

    // Get the minor version only from the \Drupal::VERSION string.
    $minor_version = substr(\Drupal::VERSION, 0, 3);

    if (version_compare($minor_version, '8.4', '>=')) {
      $expected['en']['revision_translation_affected'] = [['value' => TRUE]];
      $expected['ro']['revision_translation_affected'] = [['value' => TRUE]];
    }

    if (version_compare($minor_version, '8.5', '>=')) {
      $expected['en']['non_mul_field'] = [];
      $expected['en']['revision_default'] = [['value' => TRUE]];
      $expected['en']['field_test_text'][0]['processed'] = '';
      $expected['ro']['non_mul_field'] = [];
      $expected['ro']['revision_default'] = [['value' => TRUE]];
      $expected['ro']['field_test_text'][0]['processed'] = '';
    }

    $normalized = $this->serializer->normalize($this->entity);

    foreach (array_keys($expected) as $key) {
      $this->assertEquals($expected[$key], $normalized[$key], "Field $key is normalized correctly.");
    }
    $this->assertEquals(array_diff_key($normalized, $expected), [], 'No unexpected data is added to the normalized array.');

    // Test normalization when is set the revs query parameter.
    $revs = $this->entity->_rev->revisions;
    $expected['_revisions'] = [
      'ids' => $revs,
      'start' => count($revs),
    ];

    $normalized = $this->serializer->normalize($this->entity, NULL, ['query' => ['revs' => TRUE]]);

    foreach (array_keys($expected) as $key) {
      $this->assertEquals($expected[$key], $normalized[$key], "Field $key is normalized correctly.");
    }
    $this->assertTrue($expected['_revisions']['start'] === $normalized['_revisions']['start'], "Correct data type for the start field.");
    $this->assertEquals(array_diff_key($normalized, $expected), [], 'No unexpected data is added to the normalized array.');

    // @todo {@link https://www.drupal.org/node/2600460 Test context switches.}

    // Test serialize.
    $normalized = $this->serializer->normalize($this->entity);
    $expected = json_encode($normalized);
    // Paranoid test because JSON serialization is tested elsewhere.
    $actual = $this->serializer->serialize($this->entity, 'json');
    $this->assertSame($actual, $expected, 'Entity serializes correctly to JSON.');

    // Test denormalize.
    $denormalized = $this->serializer->denormalize($normalized, $this->entityClass, 'json');
    $this->assertTrue($denormalized instanceof $this->entityClass, new FormattableMarkup('Denormalized entity is an instance of @class', ['@class' => $this->entityClass]));
    $this->assertSame($denormalized->getEntityTypeId(), $this->entity->getEntityTypeId(), 'Expected entity type found.');
    $this->assertSame($denormalized->bundle(), $this->entity->bundle(), 'Expected entity bundle found.');
    $this->assertSame($denormalized->uuid(), $this->entity->uuid(), 'Expected entity UUID found.');

    // @todo {@link https://www.drupal.org/node/2600460 Test context switches.}
  }

}
