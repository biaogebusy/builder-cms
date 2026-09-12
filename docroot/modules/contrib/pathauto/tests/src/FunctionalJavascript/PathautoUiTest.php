<?php

namespace Drupal\Tests\pathauto\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\Tests\pathauto\Functional\PathautoTestHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test basic pathauto functionality.
 *
 * @group pathauto
 */
#[Group('pathauto')]
#[RunTestsInSeparateProcesses]
class PathautoUiTest extends WebDriverTestBase {

  use PathautoTestHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['pathauto', 'node', 'block'];

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    $this->drupalCreateContentType(['type' => 'article']);

    // Allow other modules to add additional permissions for the admin user.
    $permissions = [
      'administer pathauto',
      'administer url aliases',
      'bulk delete aliases',
      'bulk update aliases',
      'create url aliases',
      'administer nodes',
      'bypass node access',
      'access content overview',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests the validation of the settings form.
   */
  public function testSettingsValidation() {
    $this->drupalGet('/admin/config/search/path/settings');

    $this->assertSession()->fieldExists('max_length');
    $this->assertSession()->elementAttributeContains('css', '#edit-max-length', 'min', '1');

    $this->assertSession()->fieldExists('max_component_length');
    $this->assertSession()->elementAttributeContains('css', '#edit-max-component-length', 'min', '1');
  }

  /**
   * Tests the behavior and workflow of Pathauto patterns.
   */
  public function testPatternsWorkflow() {
    $this->drupalPlaceBlock('local_tasks_block', ['id' => 'local-tasks-block']);
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('page_title_block');

    $this->drupalGet('admin/config/search/path');
    $this->assertSession()->elementContains('css', '#block-local-tasks-block', 'Patterns');
    $this->assertSession()->elementContains('css', '#block-local-tasks-block', 'Settings');
    $this->assertSession()->elementContains('css', '#block-local-tasks-block', 'Bulk generate');
    $this->assertSession()->elementContains('css', '#block-local-tasks-block', 'Delete aliases');

    $this->drupalGet('admin/config/search/path/patterns');
    $this->clickLink('Add Pathauto pattern');

    $session = $this->getSession();
    $session->getPage()->selectFieldOption('type', 'canonical_entities:node');
    $this->assertSession()->assertExpectedAjaxRequest(1);

    $edit = [
      'bundles[page]' => TRUE,
      'label' => 'Page pattern',
      'pattern' => '[node:title]/[user:name]/[term:name]',
    ];
    $this->submitForm($edit, 'Save');

    $this->assertSession()->waitForElementVisible('css', '[name="id"]');
    if (version_compare(\Drupal::VERSION, '10.1', '<')) {
      $edit += [
        'id' => 'page_pattern',
      ];
      $this->submitForm($edit, 'Save');
    }

    $this->assertTrue($this->assertSession()->waitForText('Path pattern is using the following invalid tokens: [user:name], [term:name].'));
    $this->assertSession()->pageTextNotContains('The configuration options have been saved.');

    // We do not need ID anymore, it is already set in previous step and made
    // a label by browser.
    unset($edit['id']);
    $edit['pattern'] = '#[node:title]';
    $this->submitForm($edit, 'Save');
    $this->assertTrue($this->assertSession()->waitForText('The Path pattern is using the following invalid characters: #.'));
    $this->assertSession()->pageTextNotContains('The configuration options have been saved.');

    // Trailing whitespace is auto-corrected on save, so it should succeed.
    $edit['pattern'] = '[node:title] ';
    $this->submitForm($edit, 'Save');
    $this->assertTrue($this->assertSession()->waitForText('Pattern Page pattern saved.'));

    // Verify the pattern was trimmed and a leading slash was added.
    $pattern = \Drupal::entityTypeManager()
      ->getStorage('pathauto_pattern')
      ->load('page_pattern');
    $this->assertSame('/[node:title]', $pattern->getPattern());

    // Capture selection criteria UUIDs to verify they are preserved after
    // re-saving the pattern via the edit form.
    // @see https://www.drupal.org/node/2895873
    $criteria_before = $pattern->get('selection_criteria');
    $uuids_before = array_keys($criteria_before);
    $this->assertNotEmpty($uuids_before);

    \Drupal::service('pathauto.generator')->resetCaches();

    // Create a node with pattern enabled and check if the pattern applies.
    $title = 'Page Pattern enabled';
    $alias = '/page-pattern-enabled';
    $node = $this->createNode(['title' => $title, 'type' => 'page']);
    $this->drupalGet($alias);
    $this->assertTrue($this->assertSession()->waitForText($title));
    $this->assertEntityAlias($node, $alias);

    // Edit workflow, set a new label and weight for the pattern.
    $this->drupalGet('/admin/config/search/path/patterns');
    $session->getPage()->pressButton('Show row weights');
    $this->submitForm(['entities[page_pattern][weight]' => '4'], 'Save');
    $this->assertSession()->waitForText('Page pattern');

    $session->getPage()->find('css', '.dropbutton-toggle > button')->press();
    $this->clickLink('Edit');
    $destination_query = ['query' => ['destination' => Url::fromRoute('entity.pathauto_pattern.collection')->toString()]];
    $address = Url::fromRoute('entity.pathauto_pattern.edit_form', ['pathauto_pattern' => 'page_pattern'], [$destination_query]);
    $this->assertSession()->addressEquals($address);
    $this->assertSession()->fieldValueEquals('pattern', '/[node:title]');
    $this->assertSession()->fieldValueEquals('label', 'Page pattern');
    $this->assertSession()->checkboxChecked('edit-status');
    $this->assertSession()->linkExists('Delete');

    $edit = ['label' => 'Test'];
    $this->drupalGet('/admin/config/search/path/patterns/page_pattern');
    $this->submitForm($edit, 'Save');
    $this->assertTrue($this->assertSession()->waitForText('Pattern Test saved.'));
    // Check that the pattern weight did not change.
    $this->assertSession()->optionExists('edit-entities-page-pattern-weight', '4');

    // Verify that selection criteria UUIDs are preserved after re-save.
    $pattern = PathautoPattern::load('page_pattern');
    $criteria_after = $pattern->get('selection_criteria');
    $uuids_after = array_keys($criteria_after);
    $this->assertEquals($uuids_before, $uuids_after, 'Selection criteria UUIDs are preserved after re-save.');

    $this->drupalGet('/admin/config/search/path/patterns/page_pattern/duplicate');
    $session->getPage()->pressButton('Edit');
    $edit = ['label' => 'Test Duplicate', 'id' => 'page_pattern_test_duplicate'];
    $this->submitForm($edit, 'Save');
    $this->assertTrue($this->assertSession()->waitForText('Pattern Test Duplicate saved.'));

    PathautoPattern::load('page_pattern_test_duplicate')->delete();

    // Disable workflow.
    $this->drupalGet('/admin/config/search/path/patterns');
    $session->getPage()->find('css', '.dropbutton-toggle > button')->press();
    $this->assertSession()->linkNotExists('Enable');
    $this->clickLink('Disable');
    $this->assertSession()->addressEquals('/admin/config/search/path/patterns/page_pattern/disable');
    $this->submitForm([], 'Disable');
    $this->assertTrue($this->assertSession()->waitForText('Disabled pattern Test.'));

    // Load the pattern from storage and check if its disabled.
    $pattern = PathautoPattern::load('page_pattern');
    $this->assertFalse($pattern->status());

    \Drupal::service('pathauto.generator')->resetCaches();

    // Create a node with pattern disabled and check that we have no new alias.
    $title = 'Page Pattern disabled';
    $node = $this->createNode(['title' => $title, 'type' => 'page']);
    $this->assertNoEntityAlias($node);

    // Enable workflow.
    $this->drupalGet('/admin/config/search/path/patterns');
    $this->assertSession()->linkNotExists('Disable');
    $this->clickLink('Enable');
    $address = Url::fromRoute('entity.pathauto_pattern.enable', ['pathauto_pattern' => 'page_pattern'], [$destination_query]);
    $this->assertSession()->addressEquals($address);
    $this->submitForm([], 'Enable');
    $this->assertTrue($this->assertSession()->waitForText('Enabled pattern Test.'));

    // Reload pattern from storage and check if its enabled.
    $pattern = PathautoPattern::load('page_pattern');
    $this->assertTrue($pattern->status());

    // Delete workflow.
    $this->drupalGet('/admin/config/search/path/patterns');
    $session->getPage()->find('css', '.dropbutton-toggle > button')->press();
    $this->clickLink('Delete');
    $this->assertSession()->assertExpectedAjaxRequest(1);
    if (version_compare(\Drupal::VERSION, '10.1', '>=')) {
      $this->assertNotEmpty($this->assertSession()->waitForElementVisible('css', '#drupal-modal'));
      $this->assertSession()->elementContains('css', '#drupal-modal', 'This action cannot be undone.');
      $this->assertSession()->elementExists('css', '.ui-dialog-buttonpane')->pressButton('Delete');
    }
    else {
      $address = Url::fromRoute('entity.pathauto_pattern.delete_form', ['pathauto_pattern' => 'page_pattern'], [$destination_query]);
      $this->assertSession()->addressEquals($address);
      $this->submitForm([], 'Delete');
    }
    $this->assertTrue($this->assertSession()->waitForText('The pathauto pattern Test has been deleted.'));

    $this->assertEmpty(PathautoPattern::load('page_pattern'));
  }

  /**
   * Tests removing bundle conditions via the edit form.
   *
   * Verifies that unchecking all bundles on a pattern that previously had
   * a bundle condition removes that condition entirely, rather than
   * leaving an empty condition behind. This covers the elseif branch
   * in PatternEditForm::buildEntity() that calls
   * removeSelectionCondition().
   */
  public function testRemoveConditions() {
    $session = $this->getSession();

    // Create a pattern with a bundle condition for 'page'.
    $this->drupalGet('admin/config/search/path/patterns/add');
    $session->getPage()->selectFieldOption('type', 'canonical_entities:node');
    $this->assertSession()->assertExpectedAjaxRequest(1);

    $edit = [
      'bundles[page]' => TRUE,
      'label' => 'Bundle removal test',
      'pattern' => '/[node:title]',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->waitForElementVisible('css', '[name="id"]');
    $this->assertTrue($this->assertSession()->waitForText('Pattern Bundle removal test saved.'));

    // Load the pattern and verify the bundle condition exists.
    $pattern = PathautoPattern::load('bundle_removal_test');
    $this->assertNotNull($pattern);
    $conditions = iterator_to_array($pattern->getSelectionConditions());
    $this->assertCount(1, $conditions);
    $condition = reset($conditions);
    $this->assertStringStartsWith('entity_bundle:', $condition->getPluginId());
    $this->assertEquals(['page' => 'page'], $condition->getConfiguration()['bundles']);

    // Edit the pattern and uncheck the 'page' bundle.
    $this->drupalGet('/admin/config/search/path/patterns/bundle_removal_test');
    $edit = [
      'bundles[page]' => FALSE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertTrue($this->assertSession()->waitForText('Pattern Bundle removal test saved.'));

    // Verify the bundle condition was removed.
    $pattern = PathautoPattern::load('bundle_removal_test');
    $this->assertEmpty(
      iterator_to_array($pattern->getSelectionConditions()),
      'Bundle condition should be removed.'
    );
  }

}
