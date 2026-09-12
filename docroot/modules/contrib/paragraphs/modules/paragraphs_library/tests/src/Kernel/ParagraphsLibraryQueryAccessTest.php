<?php


namespace Drupal\Tests\paragraphs_library\Kernel;

use Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_library\Entity\LibraryItem;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @group paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('paragraphs')]
class ParagraphsLibraryQueryAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paragraphs',
    'paragraphs_library',
    'user',
    'entity_reference_revisions',
    'file',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraphs_library_item');
    $this->installEntitySchema('paragraph');
  }

  /**
   * Tests query alter.
   */
  public function testQueryAccess() {
    $paragraph_type_child = ParagraphsType::create([
      'label' => 'test_child',
      'id' => 'test_child',
    ]);
    $paragraph_type_child->save();

    $paragraph = Paragraph::create([
      'type' => 'test_child',
    ]);
    $paragraph->save();

    $library_item = LibraryItem::create([
      'label' => 'test_library_unpublished',
      'paragraphs' => $paragraph,
      'status' => FALSE,
    ]);
    $library_item->save();

    $paragraph2 = Paragraph::create([
      'type' => 'test_child',
    ]);
    $paragraph2->save();

    $library_item = LibraryItem::create([
      'label' => 'test_library_published',
      'paragraphs' => $paragraph2,
      'status' => TRUE,
    ]);
    $library_item->save();

    $results = \Drupal::entityQuery('paragraphs_library_item')
      ->accessCheck(TRUE)
      ->execute();
    $this->assertCount(1, $results);

    $results = \Drupal::entityQuery('paragraphs_library_item')
      ->accessCheck(TRUE)
      ->condition('label', 'test_', 'STARTS_WITH')
      ->execute();
    $this->assertCount(1, $results);

    $results = \Drupal::entityQuery('paragraphs_library_item')
      ->accessCheck(TRUE)
      ->condition('label', 'test_', 'STARTS_WITH')
      ->allRevisions()
      ->execute();
    $this->assertCount(1, $results);

    $results = \Drupal::database()->select('paragraphs_library_item', 'p')
      ->addTag('paragraphs_library_item_access')
      ->fields('p', ['id'])
      ->execute()->fetchAll();
    $this->assertCount(1, $results);

    $results = \Drupal::database()->select('paragraphs_library_item_field_data', 'p')
      ->addTag('paragraphs_library_item_access')
      ->fields('p', ['id'])
      ->execute()->fetchAll();
    $this->assertCount(1, $results);

    $query = \Drupal::database()->select('paragraphs_library_item_field_data', 'l')
      ->addTag('paragraphs_library_item_access')
      ->fields('p', ['id']);
    $query->innerJoin('paragraphs_item', 'p', 'l.paragraphs__target_id = p.id');
    $this->assertCount(1, $results);

    $selection_plugin = \Drupal::service(SelectionPluginManagerInterface::class)->getInstance(['target_type' => 'paragraphs_library_item']);
    assert($selection_plugin instanceof SelectionInterface);
    $this->assertEquals(1, $selection_plugin->countReferenceableEntities('test'));

    $results = \Drupal::entityQuery('paragraphs_library_item')
      ->accessCheck(FALSE)
      ->execute();
    $this->assertCount(2, $results);

    $results = \Drupal::database()->select('paragraphs_library_item', 'p')
      ->fields('p', ['id'])
      ->execute()->fetchAll();
    $this->assertCount(2, $results);

    $user = $this->createUser(['administer paragraphs library']);
    \Drupal::service(AccountProxyInterface::class)->setAccount($user);

    $results = \Drupal::entityQuery('paragraphs_library_item')
      ->accessCheck(TRUE)
      ->execute();
    $this->assertCount(2, $results);

    $results = \Drupal::database()->select('paragraphs_library_item', 'p')
      ->addTag('paragraphs_library_item_access')
      ->fields('p', ['id'])
      ->execute()->fetchAll();
    $this->assertCount(2, $results);

    $selection_plugin = \Drupal::service(SelectionPluginManagerInterface::class)->getInstance(['target_type' => 'paragraphs_library_item']);
    assert($selection_plugin instanceof SelectionInterface);
    $this->assertEquals(2, $selection_plugin->countReferenceableEntities('test'));
  }

}
