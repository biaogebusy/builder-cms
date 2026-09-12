<?php

namespace Drupal\Tests\entity_browser\Kernel\Controllers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_browser\Controllers\EntityBrowserController;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the entity_browser.edit_form route controller.
 *
 * @group entity_browser
 */
#[Group('entity_browser')]
#[RunTestsInSeparateProcesses]
class EntityBrowserEditFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'text',
    'taxonomy',
    'entity_browser',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['filter']);

    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();
  }

  /**
   * The edit route works for entity types with no 'edit' form class.
   *
   * Taxonomy terms only declare a 'default' form handler. Previously the
   * controller unconditionally requested the 'edit' form and threw
   * InvalidPluginDefinitionException for terms, users, files, and any other
   * entity type without an explicit 'edit' handler.
   *
   * @see https://www.drupal.org/project/entity_browser/issues/2868196
   */
  public function testEditFormFallsBackToDefaultFormClass() {
    $term = Term::create([
      'vid' => 'tags',
      'name' => 'Walrus',
    ]);
    $term->save();

    $controller = new EntityBrowserController();
    $response = $controller->entityBrowserEdit($term, new Request());

    $this->assertInstanceOf(AjaxResponse::class, $response);
    $command_names = array_column($response->getCommands(), 'command');
    $this->assertContains('openDialog', $command_names);
  }

}
