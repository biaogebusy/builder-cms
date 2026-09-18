<?php

namespace Drupal\Tests\image_captcha\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * This class provides methods specifically for testing the installation.
 *
 * @group image_captcha
 */
class ImageCaptchaInstallationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'test_page_test',
  ];

  /**
   * A user with authenticated permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * A user with admin permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('system.site')->set('page.front', '/test-page')->save();
    $this->user = $this->drupalCreateUser([]);
    $this->adminUser = $this->drupalCreateUser([]);
    $this->adminUser->addRole($this->createAdminRole('admin', 'admin'));
    $this->adminUser->save();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests if installing the module, won't break the site.
   */
  public function testInstallation() {
    // Remove when dropping support for Drupal 10.3 and below.
    $installed = version_compare(\Drupal::VERSION, '10.3', '>=') ? 'installed' : 'enabled';

    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // As simply adding the module to the $modules array only installs required
    // modules one by one, we also need to test installing both captcha and
    // image captcha at once:
    $this->drupalGet('/admin/modules');
    $page->checkField('edit-modules-image-captcha-enable');
    $page->pressButton('edit-submit');
    // Should show dependency confirmation page:
    $session->statusCodeEquals(200);
    $session->pageTextContains('Some required modules must be ' . $installed);
    // Continue to install with dependencies:
    $page->pressButton('edit-submit');
    $session->statusCodeEquals(200);
    // Verify both modules were installed:
    $session->pageTextContains(
      '2 modules have been ' . $installed . ': Image CAPTCHA, CAPTCHA'
    );
    // Go to front page and see if the site isn't broken:
    $this->drupalGet('<front>');
    // Ensure the status code is success:
    $session->statusCodeEquals(200);
    // Ensure the correct test page is loaded as front page:
    $session->pageTextContains('Test page text.');
  }

  /**
   * Tests if uninstalling the module, won't break the site.
   */
  public function testUninstallation() {
    // Remove when dropping support for Drupal 10.3 and below.
    $installed = version_compare(\Drupal::VERSION, '10.3', '>=') ? 'installed' : 'enabled';

    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Installation process:
    $this->drupalGet('/admin/modules');
    $page->checkField('edit-modules-image-captcha-enable');
    $page->pressButton('edit-submit');
    // Should show dependency confirmation page:
    $session->statusCodeEquals(200);
    $session->pageTextContains('Some required modules must be ' . $installed);
    // Continue to install with dependencies:
    $page->pressButton('edit-submit');
    $session->statusCodeEquals(200);
    $session->pageTextContains(
      '2 modules have been ' . $installed . ': Image CAPTCHA, CAPTCHA'
    );
    // Go to uninstallation page an uninstall image_captcha:
    $this->drupalGet('/admin/modules/uninstall');
    $session->statusCodeEquals(200);
    $page->checkField('edit-uninstall-image-captcha');
    $page->pressButton('edit-submit');
    $session->statusCodeEquals(200);
    // Confirm uninstall:
    $page->pressButton('edit-submit');
    $session->statusCodeEquals(200);
    $session->pageTextContains('The selected modules have been uninstalled.');
    // Retest the frontpage:
    $this->drupalGet('<front>');
    // Ensure the status code is success:
    $session->statusCodeEquals(200);
    // Ensure the correct test page is loaded as front page:
    $session->pageTextContains('Test page text.');
  }

}
