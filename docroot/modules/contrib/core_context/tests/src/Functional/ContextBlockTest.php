<?php

namespace Drupal\Tests\core_context\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;

/**
 * @group core_context
 */
class ContextBlockTest extends BrowserTestBase {

  use AssertPageCacheContextsAndTagsTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'core_context',
    'core_context_test',
    'field',
    'media',
    'media_test_source',
    'node',
    'taxonomy',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('context_block', [
      'context_mapping' => [
        'value' => '@core_context:value',
        'letter' => '@core_context:letter',
      ],
    ]);
  }

  /**
   * Reloads an entity from storage.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to reload.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The reloaded entity.
   */
  private function reload(EntityInterface $entity) {
    return $this->container->get('entity_type.manager')
      ->getStorage($entity->getEntityTypeId())
      ->load($entity->id());
  }

  /**
   * Tests viewing context values attached to a fieldable entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to view.
   */
  private function doEntityTest(FieldableEntityInterface $entity) {
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    $storage = FieldStorageConfig::create([
      'entity_type' => $entity_type_id,
      'field_name' => 'context',
      'type' => 'context',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $storage->save();

    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => $bundle,
    ])->save();

    $this->container->get('entity_display.repository')
      ->getViewDisplay($entity_type_id, $bundle, 'full')
      ->setThirdPartySetting('core_context', 'contexts', [
        'letter' => [
          'type' => 'string',
          'label' => 'NATO phonetic letter of the day',
          'description' => 'One of the words in the NATO phonetic alphabet',
          'value' => 'Romeo',
        ],
      ])
      ->save();

    $entity = $this->reload($entity);

    $entity->get('context')->appendItem([
      'id' => 'value',
      'type' => 'integer',
      'label' => 'Age of Methuselah',
      'description' => 'This is how old Methuselah was when he died.',
      'value' => 969,
    ]);
    $this->assertCount(0, $entity->validate());
    $this->assertSame(SAVED_UPDATED, $entity->save());

    // Reload the entity to be certain that the context field actually has
    // data in it.
    $entity = $this->reload($entity);

    /** @var \Drupal\core_context\Plugin\Field\FieldType\ContextItem $item */
    $item = $entity->get('context');
    $this->assertFalse($item->isEmpty(), 'Context field is empty.');
    $this->assertSame('value', $item->id);
    $this->assertSame('integer', $item->type);
    $this->assertSame('Age of Methuselah', $item->label);
    $this->assertSame('This is how old Methuselah was when he died.', $item->description);
    $this->assertSame(969, $item->value);

    $this->drupalLogin($this->rootUser);

    $this->drupalGet($entity->toUrl());
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($entity->label());
    $assert_session->pageTextContains('The context value is 969, brought to you by the letter Romeo.');

    $this->assertCacheContext('route');
  }

  /**
   * Tests viewing context values stored on a media item.
   */
  public function testMedia() {
    $this->config('media.settings')
      ->set('standalone_url', TRUE)
      ->save();

    // Changing standalone_url necessitates a router rebuild.
    $this->container->get('router.builder')->rebuild();

    $media_type = $this->createMediaType('test')->id();

    $media = Media::create([
      'bundle' => $media_type,
      'field_media_test' => $this->randomString(),
    ]);
    $media->save();
    $this->doEntityTest($media);
  }

  /**
   * Tests viewing context values stored on a node.
   */
  public function testNode() {
    $node_type = $this->drupalCreateContentType()->id();

    $node = $this->drupalCreateNode([
      'type' => $node_type,
    ]);
    $this->doEntityTest($node);
  }

  /**
   * Tests viewing context values stored on a taxonomy term.
   */
  public function testTaxonomyTerm() {
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();

    $term = Term::create([
      'vid' => 'tags',
      'name' => $this->randomString(),
    ]);
    $term->save();
    $this->doEntityTest($term);
  }

  /**
   * Tests viewing context values stored on a user account.
   */
  public function testUserAccount() {
    $account = $this->drupalCreateUser();
    $this->doEntityTest($account);
  }

}
