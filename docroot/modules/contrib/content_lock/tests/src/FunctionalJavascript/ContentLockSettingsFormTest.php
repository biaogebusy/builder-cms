<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\FunctionalJavascript;

use Drupal\Core\Site\Settings;
use Drupal\entity_test\Entity\EntityTestMulChanged;
use Drupal\Tests\content_lock\Tools\LogoutTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Content Lock settings form.
 *
 * @group content_lock
 */
#[RunTestsInSeparateProcesses]
class ContentLockSettingsFormTest extends ContentLockJavascriptTestBase {
  use LogoutTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * Tests settings form.
   *
   * @see \Drupal\content_lock\Form\ContentLockSettingsForm
   */
  public function testContentLockSettingsForm(): void {
    $page = $this->getSession()->getPage();

    $this->drupalCreateContentType(['type' => 'article']);
    $this->drupalCreateContentType(['type' => 'event']);

    $admin = $this->drupalCreateUser([
      'administer content lock',
      'administer content types',
      'administer modules',
    ]);

    $this->drupalLogin($admin);
    $this->drupalGet('admin/config/content/content_lock');

    // Test JS state management in content_lock_settings.js.
    $this->assertFalse($this->assertSession()->elementExists('css', '#edit-node-bundles-')->isVisible());
    $this->click('#edit-entity-types-node');
    $this->assertTrue($this->assertSession()->elementExists('css', '#edit-node-bundles-')->isVisible());
    $this->assertFalse($this->assertSession()->elementExists('css', '#edit-node-bundles-article')->isVisible());
    $this->assertSession()->checkboxChecked('edit-node-bundles-');
    $this->click('#edit-node-bundles-');
    $this->assertTrue($this->assertSession()->elementExists('css', '#edit-node-bundles-article')->isVisible());
    $this->click('#edit-node-bundles-article');

    $page->pressButton('Save configuration');
    $this->assertSession()->waitForText('The configuration options have been saved.');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Ensure that only configured entity types makes it into the configuration.
    $this->assertSame(['node'], array_keys($this->config('content_lock.settings')->get('types')));

    // Setting up an entity type to be lockable should create an action.
    $action = \Drupal::entityTypeManager()->getStorage('action')->loadByProperties(['plugin' => 'entity:break_lock:node']);
    $this->assertNotEmpty($action);

    // Prepare config data to import.
    $active = \Drupal::service('config.storage');
    $sync = \Drupal::service('config.storage.sync');
    $this->copyConfig($active, $sync);
    $target_dir = Settings::get('config_sync_directory');
    // Delete the action from the sync directory.
    unlink("$target_dir/" . reset($action)->getConfigDependencyName() . '.yml');

    $this->click('#edit-entity-types-node');
    $page->pressButton('Save configuration');
    $this->assertSession()->waitForText('The configuration options have been saved.');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->refreshVariables();

    // Removing an entity type should delete the action.
    $action = \Drupal::entityTypeManager()->getStorage('action')->loadByProperties(['plugin' => 'entity:break_lock:node']);
    $this->assertEmpty($action);

    // Import the content of the sync directory and ensure that the action is
    // is not created by the event listener.
    $this->configImporter()->import();
    $this->refreshVariables();
    $action = \Drupal::entityTypeManager()->getStorage('action')->loadByProperties(['plugin' => 'entity:break_lock:node']);
    $this->assertEmpty($action);
    $this->drupalGet('admin/config/content/content_lock');
    $this->assertSession()->checkboxChecked('edit-entity-types-node');
    $this->assertSession()->fieldExists('edit-node-bundles-')->uncheck();
    $this->assertSession()->fieldExists('edit-node-bundles-article')->check();
    $this->assertSession()->fieldExists('edit-node-bundles-event')->check();
    $this->assertSession()->fieldExists('edit-entity-types-entity-test-mul-changed')->check();
    $page->pressButton('Save configuration');
    $this->assertSession()->waitForText('The configuration options have been saved.');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->refreshVariables();

    $this->assertSame([
      'article' => 'article',
      'event' => 'event',
    ], $this->config('content_lock.settings')->get('types.node'));
    $this->drupalGet('admin/structure/types/manage/article/delete');
    $this->assertSession()->buttonExists('Delete')->click();
    $this->assertSession()->waitForText('The content type article has been deleted.');
    $this->assertSession()->pageTextContains('The content type article has been deleted.');
    $this->assertSame(['event' => 'event'], $this->config('content_lock.settings')->get('types.node'));

    foreach (EntityTestMulChanged::loadMultiple() as $entity) {
      $entity->delete();
    }
    $this->assertSame(['*' => '*'], $this->config('content_lock.settings')->get('types.entity_test_mul_changed'));
    $this->assertSame(['entity_test_mul_changed', 'node'], array_keys($this->config('content_lock.settings')->get('types')));
    $this->drupalGet('admin/modules/uninstall');
    $this->assertSession()->fieldExists('edit-uninstall-entity-test')->check();
    $this->assertSession()->buttonExists('Uninstall')->click();
    $this->assertSession()->waitForText('Confirm uninstall');
    $this->assertSession()->buttonExists('Uninstall')->click();
    $this->assertSession()->waitForText('The selected modules have been uninstalled.');
    $this->assertSame(['node'], array_keys($this->config('content_lock.settings')->get('types')));
  }

}
