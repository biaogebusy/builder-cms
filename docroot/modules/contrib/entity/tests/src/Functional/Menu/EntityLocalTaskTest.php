<?php

namespace Drupal\Tests\entity\Functional\Menu;

use Drupal\entity_module_test\Entity\EnhancedEntity;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that entity local tasks are generated correctly.
 *
 * @group entity
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class EntityLocalTaskTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'entity', 'entity_module_test'];

  /**
   * The view path of the entity used in the test.
   *
   * @var string
   */
  protected $viewPath;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $entity = EnhancedEntity::create([
      'type' => 'default',
      'name' => 'Enhanced Entity test'
    ]);
    $entity->save();
    $this->viewPath = $entity->toUrl()->toString();

    $this->drupalPlaceBlock('local_tasks_block');

    $account = $this->drupalCreateUser(['administer entity_test_enhanced']);
    $this->drupalLogin($account);
  }

  /**
   * Tests the local tasks of the entity are provided correctly.
   */
  public function testCollectionLocalAction() {
    $this->drupalGet($this->viewPath);
    $this->assertLocalTasks();

    $this->clickLink('Edit');
    $this->assertLocalTasks();

    $this->clickLink('Duplicate');
    $this->assertLocalTasks();

    $this->clickLink('Revisions');
    $this->assertLocalTasks();
  }

  /**
   * Asserts that the entity's local tasks are visible.
   */
  protected function assertLocalTasks() {
    $this->assertSession()->linkByHrefExists($this->viewPath);
    $this->assertSession()->linkExists('View');

    $this->assertSession()->linkByHrefExists("$this->viewPath/edit");
    $this->assertSession()->linkExists('Edit');

    $this->assertSession()->linkByHrefExists("$this->viewPath/duplicate");
    $this->assertSession()->linkExists('Duplicate');

    $this->assertSession()->linkByHrefExists("$this->viewPath/revisions");
    $this->assertSession()->linkExists('Revisions');
  }

}
