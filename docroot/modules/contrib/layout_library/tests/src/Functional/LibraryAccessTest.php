<?php

namespace Drupal\Tests\layout_library\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests accessing the layout library.
 *
 * @group layout_library
 */
class LibraryAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['layout_library'];

  /**
   * Stores user created with permission.
   */
  protected $layoutAdmin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->layoutAdmin = $this->drupalCreateUser(['configure any layout']);
  }

  /**
   * Tests accessing the library listing.
   */
  public function testLibraryListing() {
    $session = $this->assertSession();
    $this->drupalGet('admin/structure/layouts');
    $session->statusCodeEquals('403');

    $this->drupalLogin($this->layoutAdmin);
    $this->drupalGet('admin/structure/layouts');
    $session->statusCodeEquals('200');
  }

}
