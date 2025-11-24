<?php

namespace Drupal\Tests\menu_breadcrumb\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\system\Functional\Menu\AssertBreadcrumbTrait;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Base class for Menu Breadcrumb functional tests.
 *
 * @coversDefaultClass \Drupal\menu_breadcrumb\MenuBasedBreadcrumbBuilder
 *
 * @group menu_breadcrumb
 */
class MenuBreadcrumbFunctionalTestBase extends BrowserTestBase {

  use AssertBreadcrumbTrait;
  use ContentTypeCreationTrait;

  /**
   * The breadcrumb builder service.
   *
   * @var \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface
   */
  protected $breadcrumbBuilder;

  /**
   * The ID of the vocabulary for testing.
   *
   * @var string
   */
  protected $vocabularyId = 'mb_vocab';

  /**
   * The ID of a content type bundle for testing.
   *
   * @var string
   */
  protected $contentTypeId = 'mb_content';

  /**
   * The ID of a field for relating entities and terms.
   *
   * @var string
   */
  protected $fieldId = 'mb_term';

  /**
   * The menu to use.
   *
   * @var string
   */
  protected $menuId = 'main';

  /**
   * Node storage.
   *
   * @var \Drupal\Core\Entity\RevisionableStorageInterface
   */
  protected $nodeStorage;

  /**
   * Term storage.
   *
   * @var \Drupal\Core\Entity\RevisionableStorageInterface
   */
  protected $termStorage;

  /**
   * Field config.
   *
   * @var \Drupal\field\FieldConfigInterface
   */
  protected $fieldConfig;

  /**
   * A route to test with.
   *
   * @var \Symfony\Component\Routing\Route
   */
  protected $testRoute;

  /**
   * An administrative user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A regular user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'olivero';

  /**
   * {@inheritdoc}
   */
  protected function setup() : void {
    parent::setUp();

    $this->breadcrumbBuilder = $this->container->get('menu_breadcrumb.breadcrumb.default');
    $this->termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $this->nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

    $this->setupEntityStructure();
    $this->setupConfiguration();
    $this->setupContent();
  }

  /**
   * Set up content types and vocabularies.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setupEntityStructure() : void {
    $this->createContentType([
      'type' => $this->contentTypeId,
      'name' => ucfirst($this->contentTypeId),
    ]);

    $vocabulary = Vocabulary::create([
      'name' => $this->vocabularyId,
      'vid' => $this->vocabularyId,
    ]);
    $vocabulary->save();
  }

  /**
   * Set up theme and breadcrumb blocks.
   */
  protected function setupConfiguration() : void {
    $this->container->get('theme_installer')->install(['claro']);
    $this->config('system.theme')->set('admin', 'claro')->save();
    $this->config('system.site')->set('page.front', '/node')->save();
    $this->drupalPlaceBlock('system_menu_block:tools', [
      'region' => 'content',
      'theme' => $this->config('system.theme')->get('admin'),
    ]);
    $this->drupalPlaceBlock('system_menu_block:tools', [
      'region' => 'content',
      'theme' => 'olivero',
    ]);
  }

  /**
   * Set up content & users.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setupContent() : void {
    $perms = array_keys(\Drupal::service('user.permissions')->getPermissions());
    $this->adminUser = $this->drupalCreateUser($perms);
    $this->webUser = $this->drupalCreateUser([]);
  }

  /**
   * Helper to create menu links with parents.
   *
   * @param array $item
   *   Array with title, uri, parent.
   *
   * @return \Drupal\menu_link_content\Entity\MenuLinkContent
   *   The created menu link content item.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createMenuLink($item) : MenuLinkContent {
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $item['parent'] */
    $menu_item = [
      'title' => $item['title'],
      'menu_name' => 'main',
      'link' => ['uri' => $item['uri']],
      'expanded' => TRUE,
    ];
    if (isset($item['parent'])) {
      $menu_item['parent'] = $item['parent'];
    }
    $menu_link = MenuLinkContent::create($menu_item);
    $menu_link->save();
    return $menu_link;
  }

}
