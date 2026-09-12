<?php

namespace Drupal\Tests\page_manager\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\page_manager\Entity\Page;
use Drupal\page_manager\Entity\PageVariant;

/**
 * Tests that access results carry the cacheability of their conditions.
 *
 * @group page_manager
 *
 * @see \Drupal\page_manager\Entity\PageAccess
 * @see \Drupal\page_manager\Entity\PageVariantAccess
 */
class PageAccessCacheabilityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['page_manager', 'system', 'user', 'path_alias'];

  /**
   * The account access is checked for.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->account = new AnonymousUserSession();
  }

  /**
   * Tests that page access varies by the cacheability of its conditions.
   */
  public function testPageAccessInheritsConditionCacheability() {
    $page = Page::create([
      'id' => 'cacheability_test',
      'label' => 'Cacheability test',
      'path' => '/cacheability-test',
      'status' => TRUE,
    ]);
    // The request path condition varies by 'url.path'.
    $page->addAccessCondition([
      'id' => 'request_path',
      'pages' => '/cacheability-test',
      'negate' => FALSE,
    ]);
    $page->save();

    $access = $page->access('view', $this->account, TRUE);
    $this->assertContains('url.path', $access->getCacheContexts());
  }

  /**
   * Tests that variant access varies by the cacheability of its conditions.
   */
  public function testVariantAccessInheritsConditionCacheability() {
    $page = Page::create([
      'id' => 'cacheability_test',
      'label' => 'Cacheability test',
      'path' => '/cacheability-test',
      'status' => TRUE,
    ]);
    $page->save();

    $variant = PageVariant::create([
      'id' => 'cacheability_test_variant',
      'label' => 'Cacheability test variant',
      'variant' => 'http_status_code',
      'page' => 'cacheability_test',
    ]);
    // The request path condition varies by 'url.path'.
    $variant->addSelectionCondition([
      'id' => 'request_path',
      'pages' => '/cacheability-test',
      'negate' => FALSE,
    ]);
    $variant->save();

    $access = $variant->access('view', $this->account, TRUE);
    $this->assertContains('url.path', $access->getCacheContexts());
  }

  /**
   * Tests that a page without conditions gains no extra cacheability.
   */
  public function testPageWithoutConditionsHasNoExtraCacheContexts() {
    $page = Page::create([
      'id' => 'no_conditions',
      'label' => 'No conditions',
      'path' => '/no-conditions',
      'status' => TRUE,
    ]);
    $page->save();

    $access = $page->access('view', $this->account, TRUE);
    $this->assertNotContains('url.path', $access->getCacheContexts());
  }

  /**
   * Tests that a disabled page is still only cached against the page itself.
   */
  public function testDisabledPageIsCachedAgainstThePage() {
    $page = Page::create([
      'id' => 'disabled_page',
      'label' => 'Disabled page',
      'path' => '/disabled-page',
      'status' => FALSE,
    ]);
    $page->addAccessCondition([
      'id' => 'request_path',
      'pages' => '/disabled-page',
      'negate' => FALSE,
    ]);
    $page->save();

    // The conditions are never evaluated for a disabled page, so the result
    // must not claim to vary by them.
    $access = $page->access('view', $this->account, TRUE);
    $this->assertFalse($access->isAllowed());
    $this->assertNotContains('url.path', $access->getCacheContexts());
    $this->assertContains('config:page_manager.page.disabled_page', $access->getCacheTags());
  }

}
