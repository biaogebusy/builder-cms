<?php

namespace Drupal\Tests\entity\Functional;

use Drupal\entity_module_test\Entity\EnhancedEntityWithOwner;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the bulk-form list builder.
 *
 * @group entity
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class BulkFormEntityListBuilderTest extends BrowserTestBase {

  use BlockCreationTrait;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_module_test', 'user', 'entity', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * The base permissions to grant for the test user.
   *
   * @var string[]
   */
  protected $basePermissions = [
    'access entity_test_enhanced_with_owner overview',
    'view any entity_test_enhanced_with_owner',
    'view own unpublished entity_test_enhanced_with_owner',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /* @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->storage = $entity_type_manager->getStorage('entity_test_enhanced_with_owner');

    $this->placeBlock('page_title_block');
    $this->placeBlock('local_tasks_block');

    $account = $this->drupalCreateUser($this->basePermissions);
    $this->drupalLogin($account);
  }

  /**
   * Tests that the bulk form is displayed correctly.
   */
  public function testBulkForm() {
    $entity = $this->storage->create([
      'name' => 'Entity 1',
      'type' => 'default',
    ]);
    $collection_url = $entity->getEntityType()->getLinkTemplate('collection');

    // Without any entities the bulk form should not be shown.
    $this->drupalGet($collection_url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldNotExists('Action');
    $this->assertSession()->buttonNotExists('Apply to selected items');

    // Create an entity and make sure that the bulk form is shown.
    $entity->save();
    $this->drupalGet($collection_url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('Action');
    $this->assertSession()->buttonExists('Apply to selected items');

    $this->submitForm([], 'Apply to selected items');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'No items selected.');
  }

  /**
   * Test the delete action on the bulk form.
   */
  public function testDeleteAction() {
    $entity = $this->storage->create([
      'name' => 'Entity 1',
      'type' => 'default',
      'user_id' => $this->loggedInUser->id(),
    ]);
    $entity->save();
    $id = $entity->id();

    $this->drupalGet($entity->toUrl('collection'));
    $this->assertSession()->fieldValueEquals('Action', 'entity_test_enhanced_with_owner_delete_action');
    $edit = ["entities[$id]" => $id];
    $this->submitForm($edit, 'Apply to selected items');

    $this->assertSession()->elementTextContains('css', '.messages--error', 'No access to execute Delete enhanced entities with owner on the enhanced entity with owner Entity 1.');
    $this->assertInstanceOf(EnhancedEntityWithOwner::class, $this->storage->load($id));

    $account = $this->drupalCreateUser(array_merge(
      $this->basePermissions,
      ['delete any default entity_test_enhanced_with_owner']
    ));
    $this->drupalLogin($account);
    $this->drupalGet($entity->toUrl('collection'));
    $this->submitForm($edit, 'Apply to selected items');

    $this->assertSession()->elementTextContains('css', 'h1', 'Are you sure you want to delete this enhanced entity with owner?');
    $this->submitForm([], 'Delete');
    // The entity is deleted in the web process, but will still be in the static
    // cache of the test process, so we need to clear it manually.
    $this->storage->resetCache([$id]);

    $this->assertSession()->elementTextContains('css', 'h1', 'Enhanced entities with owner');
    $this->assertSession()->elementTextContains('css', '.messages--status', 'Deleted 1 item.');
    $this->assertNull($this->storage->load($id));
  }

  /**
   * Test the publish action on the bulk form.
   */
  public function testPublishAction() {
    /* @var \Drupal\entity_module_test\Entity\EnhancedEntityWithOwner $entity */
    $entity = $this->storage->create([
      'name' => 'Entity 1',
      'type' => 'default',
      'user_id' => $this->loggedInUser->id(),
      'status' => 0,
    ]);
    $entity->save();
    $id = $entity->id();

    $this->drupalGet($entity->toUrl('collection'));
    $edit = [
      'action' => 'entity_test_enhanced_with_owner_publish_action',
      "entities[$id]" => $id,
    ];
    $this->submitForm($edit, 'Apply to selected items');

    $this->assertSession()->elementTextContains('css', '.messages--error', 'No access to execute Publish enhanced entities with owner on the enhanced entity with owner Entity 1.');
    $entity = $this->storage->load($id);
    $this->assertFalse($entity->isPublished());

    $account = $this->drupalCreateUser(array_merge(
      $this->basePermissions,
      ['update any default entity_test_enhanced_with_owner']
    ));
    $entity->setOwner($account)->save();
    $this->drupalLogin($account);
    $this->drupalGet($entity->toUrl('collection'));
    $this->submitForm($edit, 'Apply to selected items');
    // The entity is deleted in the web process, but will still be in the static
    // cache of the test process, so we need to clear it manually.
    $this->storage->resetCache([$id]);

    $this->assertSession()->elementTextContains('css', 'h1', 'Enhanced entities with owner');
    $this->assertSession()->elementTextContains('css', '.messages--status', 'Publish enhanced entities with owner was applied to 1 item.');
    $entity = $this->storage->load($id);
    $this->assertTrue($entity->isPublished());
  }

  /**
   * Test the unpublish action on the bulk form.
   */
  public function testUnpublishAction() {
    /* @var \Drupal\entity_module_test\Entity\EnhancedEntityWithOwner $entity */
    $entity = $this->storage->create([
      'name' => 'Entity 1',
      'type' => 'default',
      'user_id' => $this->loggedInUser->id(),
    ]);
    $entity->save();
    $id = $entity->id();

    $this->drupalGet($entity->toUrl('collection'));
    $edit = [
      'action' => 'entity_test_enhanced_with_owner_unpublish_action',
      "entities[$id]" => $id,
    ];
    $this->submitForm($edit, 'Apply to selected items');

    $this->assertSession()->elementTextContains('css', '.messages--error', 'No access to execute Unpublish enhanced entities with owner on the enhanced entity with owner Entity 1.');
    $entity = $this->storage->load($id);
    $this->assertTrue($entity->isPublished());

    $account = $this->drupalCreateUser(array_merge(
      $this->basePermissions,
      ['update any default entity_test_enhanced_with_owner']
    ));
    $entity->setOwner($account)->save();
    $this->drupalLogin($account);
    $this->drupalGet($entity->toUrl('collection'));
    $this->submitForm($edit, 'Apply to selected items');
    // The entity is deleted in the web process, but will still be in the static
    // cache of the test process, so we need to clear it manually.
    $this->storage->resetCache([$id]);

    $this->assertSession()->elementTextContains('css', 'h1', 'Enhanced entities with owner');
    $this->assertSession()->elementTextContains('css', '.messages--status', 'Unpublish enhanced entities with owner was applied to 1 item.');
    $entity = $this->storage->load($id);
    $this->assertFalse($entity->isPublished());
  }

}
