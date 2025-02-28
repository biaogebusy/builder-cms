<?php

namespace Drupal\Tests\lightning_contact_form\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the site-wide contact form.
 *
 * @group lightning
 * @group lightning_core
 * @group lightning_contact_form
 */
class SiteContactFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lightning_contact_form'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the site-wide contact form.
   */
  public function test() {
    $assert_session = $this->assertSession();

    $this->drupalGet('/contact/sitewide');
    $assert_session->statusCodeEquals(200);

    $assert_session->fieldExists('Your name');
    $assert_session->fieldExists('Your email address');
    $assert_session->fieldExists('Subject');
    $assert_session->fieldExists('Message');

    // The name and e-mail fields should not be present for authenticated users.
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);
    $this->drupalGet('/contact/sitewide');
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldNotExists('Your name');
    $assert_session->fieldNotExists('Your email address');
    $assert_session->fieldExists('Subject');
    $assert_session->fieldExists('Message');
    $this->drupalLogout();
  }

}
