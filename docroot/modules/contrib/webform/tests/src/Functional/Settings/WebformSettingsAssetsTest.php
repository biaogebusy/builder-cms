<?php

namespace Drupal\Tests\webform\Functional\Settings;

use Drupal\Tests\webform\Functional\WebformBrowserTestBase;
use Drupal\webform\Entity\Webform;

/**
 * Tests for webform assets settings.
 *
 * @group webform
 */
class WebformSettingsAssetsTest extends WebformBrowserTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_form_assets'];

  /**
   * Tests webform assets.
   */
  public function testAssets(): void {
    $assert_session = $this->assertSession();

    $webform_assets = Webform::load('test_form_assets');

    // Check that 404 status is returned to pages without any CSS or JS.
    $this->drupalGet('/webform/javascript/contact/custom.js');
    $assert_session->statusCodeEquals(404);
    $this->drupalGet('/webform/css/contact/custom.css');
    $assert_session->statusCodeEquals(404);

    // Check has CSS (href) and JavaScript (src).
    $this->drupalGet('/webform/test_form_assets');
    $assert_session->responseContains('href="' . base_path() . 'webform/css/test_form_assets/custom.css?');
    $assert_session->responseContains('src="' . base_path() . 'webform/javascript/test_form_assets/custom.js?');

    // Clear CSS (href) and JavaScript (src).
    $webform_assets->setCss('')->setJavaScript('')->save();

    // Check has no CSS (href) and JavaScript (src).
    $this->drupalGet('/webform/test_form_assets');
    $assert_session->responseNotContains('href="' . base_path() . 'webform/css/test_form_assets/custom.css?');
    $assert_session->responseNotContains('src="' . base_path() . 'webform/javascript/test_form_assets/custom.js?');

    // Check that global CSS/JS is not found.
    $this->drupalGet('/webform/assets/global.js');
    $assert_session->statusCodeEquals(404);
    $this->drupalGet('/webform/assets/global.css');
    $assert_session->statusCodeEquals(404);

    // Add global CSS and JS on all webforms.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/admin/structure/webform/config/libraries');
    $edit = [
      'assets[css]' => '/**/',
      'assets[javascript]' => '/**/',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->drupalLogout();

    // Check that global CSS/JS is found.
    $this->drupalGet('/webform/assets/global.js');
    $assert_session->statusCodeEquals(200);
    $this->drupalGet('/webform/assets/global.css');
    $assert_session->statusCodeEquals(200);

    // Check that global CSS/JS is added to the webform libraries.
    $this->drupalGet('/webform/test_form_assets');
    $assert_session->responseContains('href="' . base_path() . 'webform/assets/global.css?');
    $assert_session->responseContains('src="' . base_path() . 'webform/assets/global.js?');
    $assert_session->responseNotContains('href="' . base_path() . 'webform/css/test_form_assets/custom.css?');
    $assert_session->responseNotContains('src="' . base_path() . 'webform/javascript/test_form_assets/custom.js?');
  }

}
