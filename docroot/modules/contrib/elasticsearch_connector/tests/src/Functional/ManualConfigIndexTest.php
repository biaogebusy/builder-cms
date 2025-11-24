<?php

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Test that we only clear the index for certain types of index config changes.
 *
 * @group elasticsearch_connector
 */
class ManualConfigIndexTest extends BrowserTestBase {
  use IndexConfigFunctionalTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'elasticsearch_connector_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The name of the ElasticSearch index to use for this test.
   *
   * @var string
   *
   * @see tests/modules/elasticsearch_connector_test/config/install/search_api.index.test_elasticsearch_index.yml
   */
  protected string $indexId = 'test_elasticsearch_index';

  /**
   * The name of the ElasticSearch server to use for this test.
   *
   * @var string
   *
   * @see tests/modules/elasticsearch_connector_test/config/install/search_api.server.elasticsearch_server.yml
   */
  protected string $serverId = 'elasticsearch_server';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpIndex();

    $this->drupalLogin($this->drupalCreateUser([
      'administer search_api',
    ]));
  }

  /**
   * Test that the index is cleared when a field is deleted.
   */
  public function testIndexClearedAfterDeletingField(): void {
    $this->assertEquals(5, $this->getNumberOfItemsInIndex());

    $this->drupalGet(Url::fromRoute('entity.search_api_index.fields', [
      'search_api_index' => $this->indexId,
    ]));
    // Click the "Remove" link in the "created" row.
    $this->click('#edit-fields-created-remove');
    $this->submitForm([], 'Save changes');
    $this->assertSession()->statusMessageContains('The changes were successfully saved.');

    $this->assertEquals(0, $this->getNumberOfItemsInIndex(), 'Index cleared after deleting a field.');
  }

  /**
   * Test that the index is cleared after the field mapping is changed.
   */
  public function testIndexClearedAfterFieldMappingChanged(): void {
    $this->assertEquals(5, $this->getNumberOfItemsInIndex());

    $this->drupalGet(Url::fromRoute('entity.search_api_index.fields', [
      'search_api_index' => $this->indexId,
    ]));
    $this->submitForm([
      'fields[category][type]' => 'text',
      'fields[category][boost]' => '1.00',
    ], 'Save changes');
    $this->assertSession()->statusMessageContains('The changes were successfully saved');

    $this->assertEquals(0, $this->getNumberOfItemsInIndex(), 'Index cleared after changing a field type');
  }

  /**
   * Changing field properties shouldn't clear the index.
   */
  public function testIndexIntactAfterFieldPropertiesChanged(): void {
    $this->assertEquals(5, $this->getNumberOfItemsInIndex());

    $this->drupalGet(Url::fromRoute('entity.search_api_index.fields', [
      'search_api_index' => $this->indexId,
    ]));
    $this->submitForm([
      'fields[body][boost]' => '1.10',
    ], 'Save changes');
    $this->assertSession()->statusMessageContains('The changes were successfully saved.');

    $this->assertEquals(5, $this->getNumberOfItemsInIndex());
  }

  /**
   * Adding a field to the index configuration shouldn't clear the index.
   */
  public function testIndexIntactAfterFieldsAdded(): void {
    $this->assertEquals(5, $this->getNumberOfItemsInIndex());

    // Go to the "Add fields to index" page, and click the "Add" button next to
    // the "ID" field for "Test entity - mul changed revisions and data table".
    $this->drupalGet(Url::fromRoute('entity.search_api_index.add_fields', [
      'search_api_index' => $this->indexId,
    ]));
    $this->submitForm([], 'entity:entity_test_mulrev_changed/langcode');
    $this->assertSession()->statusMessageContains('Field Language was added to the index.');

    // Click "Done" to go back to the list of fields at the route
    // 'entity.search_api_index.fields', and click "Save changes".
    $this->clickLink('Done');
    $this->submitForm([], 'Save changes');
    $this->assertSession()->statusMessageContains('The changes were successfully saved.');

    $this->assertEquals(5, $this->getNumberOfItemsInIndex(), 'Items in index after adding a field.');
  }

  /**
   * Saving the index configuration pages without changes shouldn't clear index.
   */
  public function testIndexIntactAfterNoChange(): void {
    // Check pre-conditions: there should be 5 items in the index.
    $this->assertEquals(5, $this->getNumberOfItemsInIndex(), 'Items in index before test.');

    // Save the Index's main edit page with no changes. Afterwards, there should
    // still be 5 items in the index.
    $this->drupalGet(Url::fromRoute('entity.search_api_index.edit_form', [
      'search_api_index' => $this->indexId,
    ]));
    $this->submitForm([], 'Save');
    $this->assertSession()->statusMessageContains('The index was successfully saved.');
    $this->assertEquals(5, $this->getNumberOfItemsInIndex(), 'Items in index after saving main edit form with no changes.');

    // Save the Index's fields page with no changes. Afterwards, there should
    // still be 5 items in the index.
    $this->drupalGet(Url::fromRoute('entity.search_api_index.fields', [
      'search_api_index' => $this->indexId,
    ]));
    $this->submitForm([], 'Save changes');
    $this->assertSession()->statusMessageContains('The changes were successfully saved');
    $this->assertEquals(5, $this->getNumberOfItemsInIndex(), 'Items in index after saving field edit form with no changes.');

    // Save the Index's processors page with no changes. Afterwards, there
    // should still be 5 items in the index.
    $this->drupalGet(Url::fromRoute('entity.search_api_index.processors', [
      'search_api_index' => $this->indexId,
    ]));
    $this->submitForm([], 'Save');
    $this->assertSession()->statusMessageContains('No values were changed.');
    $this->assertEquals(5, $this->getNumberOfItemsInIndex(), 'Items in index after saving processors form with no changes.');
  }

}
