<?php

namespace Drupal\Tests\entity\Functional\Menu;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that entity local actions are generated correctly.
 *
 * @group entity
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class EntityLocalActionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'entity', 'entity_module_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_actions_block');

    $account = $this->drupalCreateUser(['administer entity_test_enhanced']);
    $this->drupalLogin($account);
  }

  /**
   * Tests the local action on the collection is provided correctly.
   */
  public function testCollectionLocalAction() {
    $this->drupalGet('/entity_test_enhanced');
    $this->assertSession()->linkByHrefExists('/entity_test_enhanced/add?destination=/entity_test_enhanced');
    $this->assertSession()->linkExists('Add enhanced entity');
  }

}
