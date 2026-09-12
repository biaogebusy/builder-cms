<?php

namespace Drupal\Tests\search_api_db_defaults\Functional;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the correct installation of the default configs.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class IntegrationTest extends BrowserTestBase {

  use CommentTestTrait;
  use EntityReferenceFieldCreationTrait;
  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
  ];

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * A non-admin user used for this test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $authenticatedUser;

  /**
   * An admin user used for this test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create user with content access permission to see if the view is
    // accessible, and an admin to do the setup.
    $this->authenticatedUser = $this->drupalCreateUser();
    $this->adminUser = $this->drupalCreateUser([], NULL, TRUE);

    // In newer versions of Drupal, the "standard" profile does not include any
    // node types anymore. To be able to install this module we therefore have
    // to add them manually.
    // @see https://www.drupal.org/node/3587118
    if (!NodeType::load('article')) {
      // Easiest way is probably to use the "default_content_base" test recipe
      // provided by Core.
      $recipe_dir = $this->root . '/core/tests/fixtures/recipes/default_content_base';
      $this->applyRecipe($recipe_dir);
      // Remove some nonsense that comes with that.
      FieldStorageConfig::load('taxonomy_term.field_serialized_stuff')
        ->delete();

      // The "comment" field still needs to be manually added, as well as the
      // "search_result" entity view mode.
      $this->addDefaultCommentField('node', 'article');
      EntityViewMode::create([
        'id' => 'node.search_result',
        'targetEntityType' => 'node',
      ])->save();
      // Also, we do not want Content Moderation or Workspaces, as they just
      // complicate the test.
      \Drupal::getContainer()->get('module_installer')
        ->uninstall(['content_moderation', 'workspaces']);
    }
  }

  /**
   * Tests whether the default search was correctly installed.
   */
  public function testInstallAndDefaultSetupWorking() {
    $this->drupalLogin($this->adminUser);

    // Uninstall the Core search module if it was enabled.
    // @todo Remove once we depend on Drupal 11.4+.
    if (\Drupal::moduleHandler()->moduleExists('search')) {
      $edit_uninstall = [
        'uninstall[search]' => TRUE,
      ];
      $this->drupalGet('admin/modules/uninstall');
      $this->submitForm($edit_uninstall, 'Uninstall');
      $this->submitForm([], 'Uninstall');
    }

    // Install the search_api_db_defaults module.
    $edit_enable = [
      'modules[search_api_db_defaults][enable]' => TRUE,
    ];
    $this->drupalGet('admin/modules');
    $this->submitForm($edit_enable, 'Install');

    $expected_page_title = 'Some required modules must be installed';
    $expected_success_message = '3 modules have been installed: Database Search Defaults, Database Search, Search API';
    $this->assertSession()->pageTextContains($expected_page_title);

    $this->submitForm([], 'Continue');

    $this->assertSession()->pageTextContains($expected_success_message);

    $this->rebuildContainer();

    $this->drupalGet('admin/config/search/search-api/server/default_server/edit');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('The server was successfully saved.');

    $server = Server::load('default_server');
    $this->assertInstanceOf(Server::class, $server, 'Server can be loaded');

    $index = Index::load('default_index');
    $this->assertInstanceOf(Index::class, $index, 'Index can be loaded');

    $view = View::load('search_content');
    $this->assertInstanceOf(View::class, $view, 'View can be loaded');

    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet('search/content');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalLogin($this->adminUser);

    $title = 'Test node title';
    $edit = [
      'title[0][value]' => $title,
      'body[0][value]' => 'This is test content for the Search API to index.',
    ];
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');

    $this->drupalLogout();
    $this->drupalGet('search/content');
    $this->assertSession()->pageTextContains('Enter some keywords to search.');
    $this->assertSession()->pageTextNotContains($title);
    $this->assertSession()->responseNotContains('Error message');
    // @todo This suddenly stopped working due to #2568889. Figure out the new
    //   optimal configuration for that form and then test for its behavior.
    //   See #3313067.
    // $this->submitForm([], 'Search');
    // $this->assertSession()->pageTextNotContains($title);
    // $this->assertSession()->responseNotContains('Error message');
    $this->submitForm(['keys' => 'test'], 'Search');
    $this->assertSession()->pageTextContains($title);
    $this->assertSession()->responseNotContains('Error message');
    $this->assertSession()->pageTextNotContains('Enter some keywords.');
    $this->assertSession()->pageTextNotContains('Your search yielded no results.');

    // Uninstall the module.
    $this->drupalLogin($this->adminUser);
    $edit_disable = [
      'uninstall[search_api_db_defaults]' => TRUE,
    ];
    $this->drupalGet('admin/modules/uninstall');
    $this->submitForm($edit_disable, 'Uninstall');
    $this->submitForm([], 'Uninstall');
    $this->rebuildContainer();
    $this->assertFalse($this->container->get('module_handler')->moduleExists('search_api_db_defaults'), 'Search API DB Defaults module uninstalled.');

    // Check if the server is found in the Search API admin UI.
    $this->drupalGet('admin/config/search/search-api/server/default_server');
    $this->assertSession()->statusCodeEquals(200);

    // Check if the index is found in the Search API admin UI.
    $this->drupalGet('admin/config/search/search-api/index/default_index');
    $this->assertSession()->statusCodeEquals(200);

    // Check that saving any of the index's config forms works fine.
    foreach (['edit', 'fields', 'processors'] as $tab) {
      $submit = $tab == 'fields' ? 'Save changes' : 'Save';
      $this->drupalGet("admin/config/search/search-api/index/default_index/$tab");
      $this->submitForm([], $submit);
      $this->assertSession()->statusCodeEquals(200);
    }

    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet('search/content');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalLogin($this->adminUser);

    // Enable the module again. This should fail because the either the index
    // or the server or the view was found.
    $this->drupalGet('admin/modules');
    $this->submitForm($edit_enable, 'Install');
    $this->assertSession()->pageTextContains('It looks like the default setup provided by this module already exists on your site. Cannot re-install module.');

    // Delete all the entities that we would fail on if they exist.
    $entities_to_remove = [
      'search_api_index' => 'default_index',
      'search_api_server' => 'default_server',
      'view' => 'search_content',
    ];
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::service('entity_type.manager');
    foreach ($entities_to_remove as $entity_type => $entity_id) {
      /** @var \Drupal\Core\Entity\EntityStorageInterface $entity_storage */
      $entity_storage = $entity_type_manager->getStorage($entity_type);
      $entity_storage->resetCache();
      $entities = $entity_storage->loadByProperties(['id' => $entity_id]);

      if (!empty($entities[$entity_id])) {
        $entities[$entity_id]->delete();
      }
    }

    // Delete the article content type.
    $this->drupalGet('node/1/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/structure/types/manage/article');
    $this->clickLink('Delete');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Delete');

    // Try to install search_api_db_defaults module and test if it failed
    // because there was no content type "article".
    $this->drupalGet('admin/modules');
    $this->submitForm($edit_enable, 'Install');
    $success_text = new FormattableMarkup('Content type @content_type not found. Database Search Defaults module could not be installed.', ['@content_type' => 'article']);
    $this->assertSession()->pageTextContains($success_text);
  }

}
