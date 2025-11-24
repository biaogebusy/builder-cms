<?php

namespace Drupal\elasticsearch_connector_test\Drush\Commands;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\entity_test\Entity\EntityTestMulRevChanged;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile for elasticsearch_connector_test.
 */
final class ElasticsearchConnectorTestCommands extends DrushCommands {
  use AutowireTrait;

  /**
   * An entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Service to manage modules in a Drupal installation.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a ElasticsearchConnectorTestCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   An entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Service to manage modules in a Drupal installation.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Add Search API test content.
   *
   * Note this is a copy of...
   * \Drupal\Tests\search_api\Functional\ExampleContentTrait::setUpExampleStructure()
   * ...and...
   * \Drupal\Tests\search_api\Functional\ExampleContentTrait::insertExampleContent()
   * ...because use-ing that trait in this class, or in a utility class,
   * results
   * in a "Trait ... not found" PHP Fatal error.
   */
  #[Command(name: 'elasticsearch_connector_test:add-test-content')]
  #[Usage(name: 'elasticsearch_connector_test:add-test-content', description: 'Adds test Search API content.')]
  public function addSearchApiTestContent(): void {
    if (!$this->moduleHandler->moduleExists('search_api_test_example_content')) {
      $this->logger()?->error('The search_api_test_example_content module must be installed to install test content.');
      return;
    }

    // Copied from \Drupal\Tests\search_api\Functional\ExampleContentTrait::setUpExampleStructure().
    $itemArgs = ['item', NULL, 'entity_test_mulrev_changed'];
    DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '11.2.0',
      fn () => \call_user_func_array('\Drupal\entity_test\EntityTestHelper::createBundle', $itemArgs),
      fn () => \call_user_func_array('entity_test_create_bundle', $itemArgs),
    );
    $articleArgs = ['article', NULL, 'entity_test_mulrev_changed'];
    DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '11.2.0',
      fn () => \call_user_func_array('\Drupal\entity_test\EntityTestHelper::createBundle', $articleArgs),
      fn () => \call_user_func_array('entity_test_create_bundle', $articleArgs),
    );

    // Copied from \Drupal\Tests\search_api\Functional\ExampleContentTrait::insertExampleContent().
    $smiley = json_decode('"\u1F601"');
    $this->addTestEntity(1, [
      'name' => 'foo bar baz foobaz föö smile' . $smiley,
      'body' => 'test test case Case casE',
      'type' => 'item',
      // cspell:disable-next-line
      'keywords' => ['Orange', 'orange', 'örange', 'Orange', $smiley],
      'category' => 'item_category',
    ]);
    $this->addTestEntity(2, [
      'name' => 'foo test foobuz',
      'body' => 'bar test casE',
      'type' => 'item',
      'keywords' => ['orange', 'apple', 'grape'],
      'category' => 'item_category',
    ]);
    $this->addTestEntity(3, [
      'name' => 'bar',
      'body' => 'test foobar Case',
      'type' => 'item',
    ]);
    $this->addTestEntity(4, [
      'name' => 'foo baz',
      'body' => 'test test test',
      'type' => 'article',
      'keywords' => ['apple', 'strawberry', 'grape'],
      'category' => 'article_category',
      'width' => '1.0',
    ]);
    $this->addTestEntity(5, [
      'name' => 'bar baz',
      'body' => 'foo',
      'type' => 'article',
      'keywords' => ['orange', 'strawberry', 'grape', 'banana'],
      'category' => 'article_category',
      'width' => '2.0',
    ]);

    $this->logger()?->success(dt('Test content has been added.'));
  }

  /**
   * Delete Search API test content.
   */
  #[Command(name: 'elasticsearch_connector_test:delete-test-content')]
  #[Usage(name: 'elasticsearch_connector_test:delete-test-content', description: 'Deletes test Search API content.')]
  public function deleteSearchApiTestContent(): void {
    if (!$this->moduleHandler->moduleExists('search_api_test_example_content')) {
      $this->logger()?->error('The search_api_test_example_content module must be installed to delete test content.');
      return;
    }

    $entity_type = 'entity_test_mulrev_changed';
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entities = $storage->loadMultiple();
    $storage->delete($entities);

    $this->logger()?->success(dt('Test content has been deleted.'));
  }

  /**
   * Creates and saves a test entity with the given values.
   *
   * @param int $id
   *   The entity's ID.
   * @param array $values
   *   The entity's property values.
   *
   * @return \Drupal\entity_test\Entity\EntityTestMulRevChanged
   *   The created entity.
   */
  protected function addTestEntity($id, array $values): EntityTestMulRevChanged {
    $entity_type = 'entity_test_mulrev_changed';
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $values['id'] = $id;
    $entities[$id] = $storage->create($values);
    $entities[$id]->save();
    return $entities[$id];
  }

}
