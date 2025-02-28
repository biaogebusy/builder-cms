<?php

namespace Drupal\Tests\lightning_contact_form\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @group lightning
 * @group lightning_core
 * @group lightning_contact_form
 */
class PathAliasTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'path'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);
  }

  /**
   * Tests that existing path aliases are still respected after installation.
   */
  public function testPathAlias() {
    $assert_session = $this->assertSession();
    // Make sure there is nothing at the /contact path.
    $this->drupalGet('contact');
    $assert_session->statusCodeEquals(404);

    // Create a page accessible at /contact.
    $this->drupalCreateNode([
      'type' => 'page',
      'path' => '/contact',
      'title' => 'This is definitely the right contact page',
    ]);

    // Confirm this new page exists at /contact.
    $this->drupalGet('contact');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('This is definitely the right contact page');

    $this->container->get('module_installer')->install(['lightning_contact_form']);

    // Confirm that the existing path alias is still respected.
    $this->getSession()->reload();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('This is definitely the right contact page');
  }

}
