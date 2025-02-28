<?php

namespace Drupal\Tests\entity\Functional;

use Drupal\entity_module_test\Entity\EnhancedEntity;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the collection route access check.
 *
 * @group entity
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class CollectionRouteAccessTest extends BrowserTestBase {

  use BlockCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_module_test', 'user', 'entity', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->placeBlock('local_tasks_block');
    $this->placeBlock('system_breadcrumb_block');
  }

  /**
   * Test the collection route access.
   */
  public function testCollectionRouteAccess() {
    $entity = EnhancedEntity::create([
      'name' => 'rev 1',
      'type' => 'default',
    ]);
    $entity->save();

    // User without any relevant permissions.
    $account = $this->drupalCreateUser(['access administration pages']);
    $this->drupalLogin($account);

    $this->drupalGet($entity->toUrl('collection'));
    $this->assertSession()->statusCodeEquals(403);

    // User with "access overview" permissions.
    $account = $this->drupalCreateUser(['access entity_test_enhanced overview']);
    $this->drupalLogin($account);

    $this->drupalGet($entity->toUrl('collection'));
    $this->assertSession()->statusCodeEquals(200);

    // User with full administration permissions.
    $account = $this->drupalCreateUser(['administer entity_test_enhanced']);
    $this->drupalLogin($account);

    $this->drupalGet($entity->toUrl('collection'));
    $this->assertSession()->statusCodeEquals(200);
  }

}
