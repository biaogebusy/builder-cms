<?php

namespace Drupal\Tests\entity\Functional;

use Drupal\entity_module_test\Entity\EnhancedEntity;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the entity duplicate UI.
 *
 * @group entity
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class EntityDuplicateTest extends BrowserTestBase {

  use BlockCreationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The entity_test_enhanced storage.
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
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->placeBlock('local_tasks_block');
    $this->placeBlock('page_title_block');
    $this->placeBlock('system_breadcrumb_block');

    $this->account = $this->drupalCreateUser([
      'administer entity_test_enhanced',
    ]);
    $this->drupalLogin($this->account);

    $this->storage = $this->container->get('entity_type.manager')->getStorage('entity_test_enhanced');
  }

  /**
   * Tests the duplicate form.
   */
  public function testForm() {
    $entity = EnhancedEntity::create([
      'name' => 'Test',
      'type' => 'default',
    ]);
    $entity->save();

    $this->drupalGet($entity->toUrl('duplicate-form'));
    $this->assertSession()->pageTextContains('Duplicate Test');
    $this->submitForm(['name[0][value]' => 'Test2'], 'Save');
    $this->assertSession()->pageTextContains('Saved the Test2 enhanced entity.');

    $this->storage->resetCache();
    $entity = EnhancedEntity::load('1');
    $this->assertEquals('Test', $entity->label());

    $duplicated_entity = EnhancedEntity::load('2');
    $this->assertEquals('Test2', $duplicated_entity->label());
  }

}
