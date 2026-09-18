<?php

namespace Drupal\Tests\image_captcha\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * This class provides methods specifically for testing basic functionalities.
 *
 * @group image_captcha
 */
class ImageCaptchaBasicFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'test_page_test',
    'captcha',
    'image_captcha',
    'image_captcha_test',
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
   * Tests if the image captcha settings page is accessible.
   */
  public function testImageCaptchaSettingsPage() {
    $session = $this->assertSession();
    $this->drupalGet('admin/config/people/captcha/image_captcha');
    $session->statusCodeEquals(200);
    $session->pageTextContains('Example');
    $session->pageTextContains('Color and image settings');
  }

  /**
   * Tests the image form element and it's structure.
   */
  public function testImageFormElement() {
    $session = $this->assertSession();

    $this->drupalLogin($this->adminUser);

    $this->drupalGet('/captcha-test/image-test');
    $session->statusCodeEquals(200);

    $session->elementExists('css', '#image-captcha-test-test');

    $session->elementExists('css', '#image-captcha-test-test > fieldset[data-drupal-selector="edit-image-captcha"]');

    // Check the first captcha form element and see if it is complete:
    // Check captcha description:
    $session->elementExists('css', '#image-captcha-test-test > fieldset[data-drupal-selector="edit-image-captcha"] > div.captcha__description');
    $session->elementTextContains('css', '#image-captcha-test-test > fieldset[data-drupal-selector="edit-image-captcha"] > div.captcha__description', 'This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');
    $session->elementExists('css', '#image-captcha-test-test > fieldset[data-drupal-selector="edit-image-captcha"] > div.captcha__element > div.form-item-captcha-response');
    // Check Question label:
    $session->elementExists('css', '#image-captcha-test-test > fieldset[data-drupal-selector="edit-image-captcha"] > div.captcha__element > div.form-item-captcha-response > label.form-required');
    $session->elementTextContains('css', '#image-captcha-test-test > fieldset[data-drupal-selector="edit-image-captcha"] > div.captcha__element > div.form-item-captcha-response > label.form-required', 'What code is in the image?');
    // Check other text elements:
    $session->elementExists('css', '#image-captcha-test-test > fieldset[data-drupal-selector="edit-image-captcha"] > div.captcha__element > div.form-item-captcha-response > input.form-text');
    $session->elementExists('css', '#image-captcha-test-test > fieldset[data-drupal-selector="edit-image-captcha"] > div.captcha__element > div.form-item-captcha-response > div#edit-captcha-response--description');
    $session->elementTextContains('css', '#image-captcha-test-test > fieldset[data-drupal-selector="edit-image-captcha"] > div.captcha__element > div.form-item-captcha-response > div#edit-captcha-response--description', 'Enter the characters shown in the image.');
    // Check image exists:
    $session->elementExists('css', '#image-captcha-test-test > fieldset[data-drupal-selector="edit-image-captcha"] > div.captcha__element > div#edit-captcha-image-wrapper');
    $session->elementExists('css', '#edit-captcha-image-wrapper > img[data-drupal-selector="edit-captcha-image"]');
    $session->elementExists('css', '#edit-captcha-image-wrapper > img[src*="/image-captcha-generate"]');
    // Check refresh button:
    $session->elementExists('css', '#edit-captcha-image-wrapper > div.reload-captcha-wrapper');
    $session->elementExists('css', '#edit-captcha-image-wrapper > div.reload-captcha-wrapper > a.reload-captcha');
  }

}
