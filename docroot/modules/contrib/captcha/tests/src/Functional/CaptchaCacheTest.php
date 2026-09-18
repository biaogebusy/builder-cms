<?php

namespace Drupal\Tests\captcha\Functional;

use Drupal\Core\Database\Database;
use Drupal\captcha\Constants\CaptchaConstants;
use Drupal\image_captcha\Constants\ImageCaptchaConstants;

/**
 * Tests CAPTCHA caching on various pages.
 *
 * @group captcha
 */
class CaptchaCacheTest extends CaptchaWebTestBase {

  /**
   * Modules to install for this Test class.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'image_captcha',
    'captcha_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('user_login_block', ['id' => 'login']);
  }

  /**
   * Test the cache tags.
   */
  public function testCacheTags() {
    // Check caching without captcha as anonymous user.
    $this->drupalGet('');
    $this->assertEquals($this->getSession()->getResponseHeader('x-drupal-cache'), 'MISS');
    $this->drupalGet('');
    $this->assertEquals($this->getSession()->getResponseHeader('x-drupal-cache'), 'HIT');

    // Enable captcha on login block and test caching.
    captcha_set_form_id_setting('user_login_form', CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE);
    $this->drupalGet('');
    $sid = $this->getCaptchaSidFromForm();
    $header = $this->getSession()->getResponseHeader('x-drupal-cache');
    // @see https://www.drupal.org/node/2958442
    // This is to support "phpunit (previous minor)"
    $pre_headers_changed = $header === NULL;
    if ($pre_headers_changed) {
      $this->assertNull($this->getSession()->getResponseHeader('x-drupal-cache'), 'Cache is disabled');
    }
    else {
      $this->assertEquals($this->getSession()->getResponseHeader('x-drupal-cache'), 'UNCACHEABLE (response policy)', 'Cache is disabled');
    }
    $this->drupalGet('');
    $this->assertNotEquals($sid, $this->getCaptchaSidFromForm());

    // Switch challenge to captcha/Test, check the captcha isn't cached.
    captcha_set_form_id_setting('user_login_form', 'captcha/Test');
    $this->drupalGet('');
    $sid = $this->getCaptchaSidFromForm();
    if ($pre_headers_changed) {
      $this->assertNull($this->getSession()->getResponseHeader('x-drupal-cache'), 'Cache is disabled');
    }
    else {
      $this->assertEquals($this->getSession()->getResponseHeader('x-drupal-cache'), 'UNCACHEABLE (response policy)', 'Cache is disabled');
    }
    $this->drupalGet('');
    $this->assertNotEquals($sid, $this->getCaptchaSidFromForm());

    // Switch challenge to image_captcha/Image, check the captcha isn't cached.
    captcha_set_form_id_setting('user_login_form', ImageCaptchaConstants::IMAGE_CAPTCHA_CAPTCHA_TYPE);
    $this->drupalGet('');
    $image_path = $this->getSession()->getPage()->find('css', '.captcha img')->getAttribute('src');
    if ($pre_headers_changed) {
      $this->assertNull($this->getSession()->getResponseHeader('x-drupal-cache'), 'Cache is disabled');
    }
    else {
      $this->assertEquals($this->getSession()->getResponseHeader('x-drupal-cache'), 'UNCACHEABLE (response policy)', 'Cache is disabled');
    }
    // Check that we get a new image when vising the page again.
    $this->drupalGet('');
    $this->assertNotEquals($image_path, $this->getSession()->getPage()->find('css', '.captcha img')->getAttribute('src'));
    // Check image caching, remove the base path since drupalGet() expects the
    // internal path.
    // @todo Fix with issue #3285734. It currently breaks D10 DrupalCi.
    // $this->drupalGet(substr($image_path, strlen($base_path)));
    // $this->assertSession()->statusCodeEquals(200);
    // Request image twice to make sure no errors happen (due to page caching).
    // $this->drupalGet(substr($image_path, strlen($base_path)));
    // $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests a cacheable captcha type.
   */
  public function testCacheableCaptcha() {
    $web_assert = $this->assertSession();

    // Enable captcha on login block with a cacheable captcha.
    $type = 'captcha_test/TestCacheable';
    captcha_set_form_id_setting('user_login_form', $type);

    // Warm up the caches.
    $this->drupalGet('');

    // Let's check if the page is cached.
    $this->drupalGet('');
    static::assertSame('HIT', $this->getSession()->getResponseHeader('X-Drupal-Cache'), 'Cache enabled');

    $edit = [
      'name' => $this->normalUser->getDisplayName(),
      'pass' => $this->normalUser->pass_raw,
      'captcha_response' => 'Test 123',
    ];
    $this->submitForm($edit, 'Log in');
    $web_assert->addressEquals('user/' . $this->normalUser->id());

    // Simulate a cron run that deletes the {captcha_session} data.
    $connection = Database::getConnection();
    $connection->delete('captcha_sessions')->execute();

    // Log out and reload the form. Because the captcha is cacheable, the form
    // is retrieved from the render cache, and contains the same CSID as
    // previously.
    $this->drupalLogout();
    $this->drupalGet('');
    static::assertSame('HIT', $this->getSession()->getResponseHeader('X-Drupal-Cache'), 'Cache enabled');

    $edit = [
      'name' => $this->normalUser->getDisplayName(),
      'pass' => $this->normalUser->pass_raw,
      'captcha_response' => 'Test 123',
    ];
    $this->submitForm($edit, 'Log in');
    $web_assert->addressEquals('user/' . $this->normalUser->id());
  }

}
