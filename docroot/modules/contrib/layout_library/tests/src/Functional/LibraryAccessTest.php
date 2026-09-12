<?php

namespace Drupal\Tests\layout_library\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests accessing the layout library.
 *
 * @group layout_library
 */
#[Group('layout_library')]
#[RunTestsInSeparateProcesses]
class LibraryAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['layout_library'];

  /**
   * Stores user created with permission.
   *
   * @var \Drupal\user\UserInterface
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
