<?php

namespace Drupal\Tests\captcha\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\captcha\Constants\CaptchaConstants;
use Drupal\captcha\Entity\CaptchaPoint;

/**
 * Tests CAPTCHA admin settings.
 *
 * @group captcha
 */
class CaptchaAdminTest extends CaptchaWebTestBase {

  use StringTranslationTrait;

  /**
   * A user without the "skip CAPTCHA" permission.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $userWithoutSkipCaptcha;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->userWithoutSkipCaptcha = $this->drupalCreateUser([
      'access content',
      'administer site configuration',
      'administer CAPTCHA settings',
    ]);
  }

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'test_page_test',
  ];

  /**
   * Test access to the admin pages.
   */
  public function testAdminAccess() {
    $this->drupalLogin($this->normalUser);
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH);
    $this->assertSession()->pageTextContains($this->t('Access denied'));

    $this->drupalLogin($this->adminUser);
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH);
    $this->assertSession()->pageTextNotContains($this->t('Access denied'));
  }

  /**
   * Test the CAPTCHA point setting getter/setter.
   */
  public function testCaptchaPointSettingGetterAndSetter() {
    $comment_form_id = self::COMMENT_FORM_ID;
    captcha_set_form_id_setting($comment_form_id, 'test');
    /** @var \Drupal\captcha\Entity\CaptchaPoint $result */
    $result = captcha_get_form_id_setting($comment_form_id);
    $this->assertNotNull($result, 'CAPTCHA exists');
    $this->assertEquals($result->getCaptchaType(), 'test', 'CAPTCHA type: default');
    $result = captcha_get_form_id_setting($comment_form_id, TRUE);
    $this->assertNotNull($result, 'CAPTCHA exists');
    $this->assertEquals($result, 'test', 'Setting and symbolic getting CAPTCHA point: "test"');

    // Set to 'default'.
    captcha_set_form_id_setting($comment_form_id, CaptchaConstants::CAPTCHA_TYPE_DEFAULT);
    $this->config('captcha.settings')
      ->set('default_challenge', 'foo/bar')
      ->save();
    $result = captcha_get_form_id_setting($comment_form_id);
    $this->assertNotNull($result, 'CAPTCHA exists');
    $this->assertEquals($result->getCaptchaType(), 'foo/bar', 'Setting and getting CAPTCHA point: default');
    $result = captcha_get_form_id_setting($comment_form_id, TRUE);
    $this->assertNotNull($result, 'Setting and symbolic getting CAPTCHA point: "default"');
    $this->assertEquals($result, 'foo/bar', 'Setting and symbolic getting CAPTCHA point: default');

    // Set to 'baz/boo'.
    captcha_set_form_id_setting($comment_form_id, 'baz/boo');
    $result = captcha_get_form_id_setting($comment_form_id);
    $this->assertNotNull($result, 'CAPTCHA exists');
    $this->assertEquals($result->getCaptchaType(), 'baz/boo', 'Setting and getting CAPTCHA point: baz/boo');
    $result = captcha_get_form_id_setting($comment_form_id, TRUE);
    $this->assertEquals($result, 'baz/boo', 'Setting and symbolic getting CAPTCHA point: "baz/boo"');

    // Set to NULL (which should delete the CAPTCHA point setting entry).
    captcha_set_form_id_setting($comment_form_id, NULL);
    $result = captcha_get_form_id_setting($comment_form_id);
    $this->assertNotNull($result, 'CAPTCHA exists');
    $this->assertEquals($result->getCaptchaType(), 'foo/bar', 'Setting and getting CAPTCHA point: NULL');
    $result = captcha_get_form_id_setting($comment_form_id, TRUE);
    $this->assertNotNull($result, 'CAPTCHA exists');

    // Set with object.
    $captcha_type = 'baba/fofo';
    captcha_set_form_id_setting($comment_form_id, $captcha_type);

    $result = captcha_get_form_id_setting($comment_form_id);
    $this->assertNotNull($result, 'Setting and getting CAPTCHA point: baba/fofo');
    // $this->assertEqual($result->module, 'baba', 'Setting and getting
    // CAPTCHA point: baba/fofo', 'CAPTCHA');.
    $this->assertEquals($result->getCaptchaType(), 'baba/fofo', 'Setting and getting CAPTCHA point: baba/fofo');
    $result = captcha_get_form_id_setting($comment_form_id, TRUE);
    $this->assertEquals($result, 'baba/fofo', 'Setting and symbolic getting CAPTCHA point: "baba/fofo"');
  }

  /**
   * Helper function for checking CAPTCHA setting of a form.
   *
   * @param string $form_id
   *   The form_id of the form to investigate.
   * @param string $challenge_type
   *   What the challenge type should be:
   *   NULL, 'default' or something like 'captcha/Math'.
   */
  protected function assertCaptchaSetting($form_id, $challenge_type) {
    $result = captcha_get_form_id_setting(self::COMMENT_FORM_ID, TRUE);
    $this->assertEquals($result, $challenge_type,
      $this->t('Check CAPTCHA setting for form: expected: @expected, received: @received.',
        [
          '@expected' => var_export($challenge_type, TRUE),
          '@received' => var_export($result, TRUE),
        ]));
  }

  /**
   * Testing of the CAPTCHA administration links.
   */
  public function testCaptchaAdminLinks() {
    $this->drupalLogin($this->adminUser);

    // Enable CAPTCHA administration links.
    $edit = [
      'administration_mode' => TRUE,
    ];

    $this->drupalGet(self::CAPTCHA_ADMIN_PATH);
    $this->submitForm($edit, 'Save configuration');

    // Create a node with comments enabled.
    $node = $this->drupalCreateNode();

    // Go to node page.
    $this->drupalGet('node/' . $node->id());

    // Click the add new comment link.
    $this->clickLink($this->t('Add new comment'));
    $add_comment_url = $this->getUrl();

    // Remove fragment part from comment URL to avoid
    // problems with later asserts.
    $add_comment_url = strtok($add_comment_url, "#");

    // Click the CAPTCHA admin link to enable a challenge.
    $this->clickLink($this->t('Place a CAPTCHA here for untrusted users.'));

    // Enable Math CAPTCHA.
    $edit = ['captchaType' => CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE];
    $this->drupalGet($this->getUrl());
    $this->submitForm($edit, 'Save');

    // Check if returned to original comment form.
    $this->assertSession()->addressEquals($add_comment_url);

    // Check if CAPTCHA was successfully enabled
    // (on CAPTCHA admin links fieldset).
    $this->assertSession()->pageTextContains($this->t('CAPTCHA: challenge "@type" enabled', ['@type' => $edit['captchaType']]));

    // Check if CAPTCHA was successfully enabled (through API).
    $this->assertCaptchaSetting(self::COMMENT_FORM_ID, CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE);

    // Edit challenge type through CAPTCHA admin links.
    $this->clickLink($this->t('change'));

    // Enable Math CAPTCHA.
    $edit = ['captchaType' => CaptchaConstants::CAPTCHA_TYPE_DEFAULT];
    $this->drupalGet($this->getUrl());
    $this->submitForm($edit, 'Save');

    // Check if returned to original comment form.
    $this->assertEquals($add_comment_url, $this->getUrl(),
      'After editing challenge type CAPTCHA admin links: should return to original form.');

    // Check if CAPTCHA was successfully changed
    // (on CAPTCHA admin links fieldset).
    // This is actually the same as the previous setting because
    // the captcha/Math is the default for the default challenge.
    // @todo Make sure the edit is a real change.
    $this->assertSession()->pageTextContains($this->t('CAPTCHA: challenge "@type" enabled', ['@type' => $edit['captchaType']]));
    // Check if CAPTCHA was successfully edited (through API).
    $this->assertCaptchaSetting(self::COMMENT_FORM_ID, CaptchaConstants::CAPTCHA_TYPE_DEFAULT);

    // Disable challenge through CAPTCHA admin links.
    $this->drupalGet(Url::fromRoute('entity.captcha_point.disable', ['captcha_point' => self::COMMENT_FORM_ID]));
    $this->submitForm([], 'Disable');

    // Check if returned to captcha point list.
    global $base_url;
    $this->assertEquals($base_url . '/admin/config/people/captcha/captcha-points', $this->getUrl(),
      'After disabling challenge in CAPTCHA admin: should return to captcha point list.');

    // Check if CAPTCHA was successfully disabled
    // (on CAPTCHA admin links fieldset).
    $this->assertSession()->responseContains($this->t('Captcha point %form_id has been disabled.', ['%form_id' => self::COMMENT_FORM_ID]));
  }

  /**
   * Test untrusted user posting.
   */
  public function testUntrustedUserPosting() {
    // Set CAPTCHA on comment form.
    captcha_set_form_id_setting(self::COMMENT_FORM_ID, CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE);

    // Create a node with comments enabled.
    $node = $this->drupalCreateNode();

    // Log in as normal (untrusted) user.
    $this->drupalLogin($this->normalUser);

    // Go to node page and click the "add comment" link.
    $this->drupalGet('node/' . $node->id());
    $this->clickLink($this->t('Add new comment'));
    $add_comment_url = $this->getUrl();

    // Check if CAPTCHA is visible on form.
    $this->assertCaptchaPresence(TRUE);
    // Try to post a comment with wrong answer.
    $edit = $this->getCommentFormValues();
    $edit['captcha_response'] = 'xx';
    $this->drupalGet($add_comment_url);
    $this->submitForm($edit, 'Preview');
    $this->assertSession()->pageTextContains(self::CAPTCHA_WRONG_RESPONSE_ERROR_MESSAGE);
  }

  /**
   * Test XSS vulnerability on CAPTCHA description.
   */
  public function testXssOnCaptchaDescription() {
    // Set CAPTCHA on user register form.
    captcha_set_form_id_setting('user_register', CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE);

    // Put JavaScript snippet in CAPTCHA description.
    $this->drupalLogin($this->adminUser);
    $xss = '<script type="text/javascript">alert("xss")</script>';
    $edit = ['description' => $xss];
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH);
    $this->submitForm($edit, 'Save configuration');

    // Visit user register form and check if JavaScript snippet is there.
    $this->drupalLogout();
    $this->drupalGet('user/register');
    $this->assertSession()->responseNotContains($xss);
  }

  /**
   * Test the CAPTCHA placement clearing.
   */
  public function testCaptchaPlacementCacheClearing() {
    // Set CAPTCHA on user register form.
    captcha_set_form_id_setting('user_register_form', CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE);
    // Visit user register form to fill the CAPTCHA placement cache.
    $this->drupalGet('user/register');
    // Check if there is CAPTCHA placement cache.
    $placement_map = $this->container->get('cache.default')
      ->get('captcha_placement_map_cache');
    $this->assertNotNull($placement_map, 'CAPTCHA placement cache should be set.');
    // Clear the cache.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH);
    $this->submitForm([], 'Clear the CAPTCHA placement cache');

    // Check that the placement cache is unset.
    $placement_map = $this->container->get('cache.default')
      ->get('captcha_placement_map_cache');
    $this->assertFalse($placement_map, 'CAPTCHA placement cache should be unset after cache clear.');
  }

  /**
   * Helper function to get CAPTCHA point setting straight from the database.
   *
   * @param string $form_id
   *   Form machine ID.
   *
   * @return \Drupal\captcha\Entity\CaptchaPoint
   *   CaptchaPoint with mysql query result.
   */
  protected function getCaptchaPointSettingFromDatabase($form_id) {
    $ids = \Drupal::entityQuery('captcha_point')
      ->condition('formId', $form_id)
      ->execute();
    return $ids ? CaptchaPoint::load(reset($ids)) : NULL;
  }

  /**
   * Method for testing the CAPTCHA point administration.
   */
  public function testCaptchaPointAdministration() {
    // Generate CAPTCHA point data:
    // Drupal form ID should consist of lowercase alphanumerics and underscore).
    $captcha_point_form_id = 'form_' . strtolower($this->randomMachineName(32));
    // The Math CAPTCHA by the CAPTCHA module is always available,
    // so let's use it.
    $captcha_point_module = 'captcha';
    $captcha_point_type = 'Math';

    // Log in as admin.
    $this->drupalLogin($this->adminUser);
    $label = 'TEST';

    // Try and set CAPTCHA point without the #required label. Should fail.
    $form_values = [
      'formId' => $captcha_point_form_id,
      'captchaType' => $captcha_point_module . '/' . $captcha_point_type,
    ];
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha-points/add');
    $this->submitForm($form_values, 'Save');
    $this->assertSession()->pageTextContains($this->t('Form ID field is required.'));

    // Set CAPTCHA point through admin/user/captcha/captcha/captcha_point.
    $form_values['label'] = $label;
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha-points/add');
    $this->submitForm($form_values, 'Save');
    $this->assertSession()->responseContains($this->t('Captcha Point for %label form was created.', ['%label' => $captcha_point_form_id]));

    // Check in database.
    /** @var \Drupal\captcha\Entity\CaptchaPoint result */
    $result = $this->getCaptchaPointSettingFromDatabase($captcha_point_form_id);
    $this->assertEquals($result->captchaType, $captcha_point_module . '/' . $captcha_point_type,
      'Enabled CAPTCHA point should have module and type set');

    // Disable CAPTCHA point again.
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha-points/' . $captcha_point_form_id . '/disable');
    $this->submitForm([], 'Disable');
    $this->assertSession()->responseContains($this->t('Captcha point %label has been disabled.', ['%label' => $label]));

    // Check in database.
    $result = $this->getCaptchaPointSettingFromDatabase($captcha_point_form_id);
    $this->assertInstanceOf(CaptchaPoint::class, $result, 'Disabled CAPTCHA point should be in database');
    $this->assertFalse($result->status());

    // Set CAPTCHA point via admin/user/captcha/captcha/captcha_point/$form_id.
    $form_values = [
      'captchaType' => $captcha_point_module . '/' . $captcha_point_type,
    ];
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha-points/' . $captcha_point_form_id);
    $this->submitForm($form_values, 'Save');
    $this->assertSession()->responseContains($this->t('Captcha Point for %form_id form was updated.', ['%form_id' => $captcha_point_form_id]));

    // Check in database.
    $result = $this->getCaptchaPointSettingFromDatabase($captcha_point_form_id);
    $this->assertEquals($result->captchaType, $captcha_point_module . '/' . $captcha_point_type,
      'Enabled CAPTCHA point should have module and type set');

    // Delete CAPTCHA point.
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha-points/' . $captcha_point_form_id . '/delete');
    $this->submitForm([], 'Delete');
    $this->assertSession()->responseContains($this->t('Captcha point %label has been deleted.', ['%label' => $label]));

    $result = $this->getCaptchaPointSettingFromDatabase($captcha_point_form_id);
    $this->assertNull($result, 'Deleted CAPTCHA point should not be in database');
  }

  /**
   * Method for testing the CAPTCHA point administration.
   */
  public function testCaptchaPointAdministrationByNonAdmin() {
    // First add a CAPTCHA point (as admin).
    $captcha_point_form_id = 'form_' . strtolower($this->randomMachineName(32));
    $captcha_point_module = 'captcha';
    $captcha_point_type = 'Math';
    $label = 'TEST_2';

    $this->drupalLogin($this->adminUser);

    $form_values = [
      'label' => $label,
      'formId' => $captcha_point_form_id,
      'captchaType' => $captcha_point_module . '/' . $captcha_point_type,
    ];
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha-points/add');
    $this->submitForm($form_values, 'Save');
    $this->assertSession()->responseContains($this->t('Captcha Point for %form_id form was created.', ['%form_id' => $captcha_point_form_id]));

    // Switch from admin to non-admin.
    $this->drupalLogin($this->normalUser);

    // Try to set CAPTCHA point
    // through admin/user/captcha/captcha/captcha_point.
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha-points');
    $this->assertSession()->pageTextContains($this->t('You are not authorized to access this page.'));

    // Try to disable the CAPTCHA point.
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha-points/' . $captcha_point_form_id . '/disable');
    $this->assertSession()->pageTextContains($this->t('You are not authorized to access this page.'));

    // Try to delete the CAPTCHA point.
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha-points/' . $captcha_point_form_id . '/delete');
    $this->assertSession()->pageTextContains($this->t('You are not authorized to access this page.'));

    // Switch from nonadmin to admin again.
    $this->drupalLogin($this->adminUser);

    // Check if original CAPTCHA point still exists in database.
    $result = $this->getCaptchaPointSettingFromDatabase($captcha_point_form_id);
    $this->assertEquals($result->captchaType, $captcha_point_module . '/' . $captcha_point_type, 'Enabled CAPTCHA point should have module and type set');

    // Delete captcha point.
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha-points/' . $captcha_point_form_id . '/delete');
    $this->submitForm([], 'Delete');
    $this->assertSession()->responseContains($this->t('Captcha point %label has been deleted.', ['%label' => $label]));
  }

  /**
   * Tests the admin captcha examples form.
   */
  public function testCaptchaAdminExamplesForm() {
    $this->drupalLogin($this->adminUser);
    $session = $this->assertSession();
    $this->drupalGet('/admin/config/people/captcha/examples');
    $session->statusCodeEquals(200);
    $session->pageTextContains('CAPTCHA examples');
    // Check if math challenge details exists:
    $session->elementExists('css', '#edit-captcha-captcha-0');
    $session->elementTextEquals('css', 'details#edit-captcha-captcha-0 > summary', 'Challenge Math by module captcha');
    // Check if math captcha exists:
    $session->elementExists('css', 'fieldset.captcha.captcha.captcha-type-challenge--math');
    $session->elementExists('css', 'fieldset.captcha.captcha.captcha-type-challenge--math > div.captcha__element');
  }

  /**
   * Tests the captcha administration mode (admin informations).
   */
  public function testCaptchaAdministrationMode() {
    $this->drupalLogin($this->adminUser);
    $session = $this->assertSession();
    // Enable administration mode:
    $this->config('captcha.settings')->set('administration_mode', TRUE)->save();
    // Create Captcha point on a non admin test page:
    CaptchaPoint::create([
      'formId' => 'test_page_form',
      'label' => 'CaptchaPointOnNonAdminPage',
      'captchaType' => CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE,
    ])->save();
    // Create Captcha point on a admin test page:
    CaptchaPoint::create([
      'formId' => 'system_performance_settings',
      'label' => 'CaptchaPointOnAdminPage',
      'captchaType' => CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE,
    ])->save();
    // Go to the test page and check if the admin information get displayed:
    $this->drupalGet('/test-field-xpath');
    $session->pageTextContains('Users without the "skip CAPTCHA" permission will see a CAPTCHA here');
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper');
    // Check summary text:
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper > summary');
    $session->elementTextContains('css', 'details.captcha-admin-links.form-wrapper > summary', 'CAPTCHA: challenge "captcha/Math" enabled');
    // Check if link to settings page exists:
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper > a[href*="captcha"]');
    // Check if link to assoicated captcha point exists:
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper > div#edit-challenge');
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper > div#edit-challenge > a[href*="/admin/config/people/captcha/captcha-points/test_page_form"]');

    // Go to the admin form and see if there is no captcha at all, as it should
    // be simply skipped:
    $this->drupalGet('/admin/config/development/performance');
    $session->elementNotExists('css', 'fieldset.captcha');
    $session->elementNotExists('css', 'fieldset.captcha > div.captcha__element');
    $session->pageTextNotContains('This question is for testing whether or not you are a human visitor and to prevent automated spam submissions');

    // Login as a user without the "skip CAPTCHA" permission and check
    // everything once again:
    $this->drupalLogout();
    $this->drupalLogin($this->userWithoutSkipCaptcha);
    $this->drupalGet('/test-field-xpath');

    // Go to the test page and check if the admin information won't get
    // displayed:
    $session->pageTextNotContains('Users without the "skip CAPTCHA" permission will see a CAPTCHA here');
    $session->elementNotExists('css', 'details.captcha-admin-links.form-wrapper');
    // See if instead the captcha appears:
    $session->elementExists('css', 'fieldset.captcha.captcha-type-challenge--math');

    // The same behaviour should happen on the admin page:
    $session->pageTextNotContains('Users without the "skip CAPTCHA" permission will see a CAPTCHA here');
    $session->elementNotExists('css', 'details.captcha-admin-links.form-wrapper');
    // See if instead the captcha appears:
    $session->elementExists('css', 'fieldset.captcha.captcha-type-challenge--math');

    // Logout and check the behaviour on the non admin page:
    $this->drupalLogout();
    $this->drupalGet('/test-field-xpath');

    // Go to the test page and check if the admin information won't get
    // displayed:
    $session->pageTextNotContains('Users without the "skip CAPTCHA" permission will see a CAPTCHA here');
    $session->elementNotExists('css', 'details.captcha-admin-links.form-wrapper');
    // See if instead the captcha appears:
    $session->elementExists('css', 'fieldset.captcha.captcha-type-challenge--math');
  }

  /**
   * Tests the captcha administration mode (admin informations).
   */
  public function testCaptchaAdministrationModeOnAdminRoutes() {
    $this->drupalLogin($this->adminUser);
    $session = $this->assertSession();
    // Enable administration mode:
    $this->config('captcha.settings')->set('administration_mode', TRUE)->save();
    $this->config('captcha.settings')->set('administration_mode_on_admin_routes', TRUE)->save();
    // Create Captcha point on a non admin test page:
    CaptchaPoint::create([
      'formId' => 'test_page_form',
      'label' => 'CaptchaPointOnNonAdminPage',
      'captchaType' => CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE,
    ])->save();
    // Create Captcha point on a admin test page:
    CaptchaPoint::create([
      'formId' => 'system_performance_settings',
      'label' => 'CaptchaPointOnAdminPage',
      'captchaType' => CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE,
    ])->save();
    // Go to the test page and check if the admin information get displayed:
    $this->drupalGet('/test-field-xpath');
    $session->pageTextContains('Users without the "skip CAPTCHA" permission will see a CAPTCHA here');
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper');
    // Check summary text:
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper > summary');
    $session->elementTextContains('css', 'details.captcha-admin-links.form-wrapper > summary', 'CAPTCHA: challenge "captcha/Math" enabled');
    // Check if link to settings page exists:
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper > a[href*="/admin/config/people/captcha"]');
    // Check if link to assoicated captcha point exists:
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper > div#edit-challenge');
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper > div#edit-challenge > a[href*="/admin/config/people/captcha/captcha-points/test_page_form"]');

    // Go to the admin form and see if also there the admin information get
    // displayed:
    $this->drupalGet('/admin/config/development/performance');
    $session->pageTextContains('Users without the "skip CAPTCHA" permission will see a CAPTCHA here');
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper');
    // Check summary text:
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper > summary');
    $session->elementTextContains('css', '#system-performance-settings > details.captcha-admin-links.form-wrapper > summary', 'CAPTCHA: challenge "captcha/Math" enabled');
    // Check if link to settings page exists:
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper > a[href*="/admin/config/people/captcha"]');
    // Check if link to assoicated captcha point exists:
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper > div#edit-challenge');
    $session->elementExists('css', 'details.captcha-admin-links.form-wrapper > div#edit-challenge > a[href*="/admin/config/people/captcha/captcha-points/system_performance_settings"]');

    // Login as a user without the "skip CAPTCHA" permission and check
    // everything once again:
    $this->drupalLogout();
    $this->drupalLogin($this->userWithoutSkipCaptcha);
    $this->drupalGet('/test-field-xpath');

    // Go to the test page and check if the admin information won't get
    // displayed:
    $session->pageTextNotContains('Users without the "skip CAPTCHA" permission will see a CAPTCHA here');
    $session->elementNotExists('css', 'details.captcha-admin-links.form-wrapper');
    // See if instead the captcha appears:
    $session->elementExists('css', 'fieldset.captcha.captcha-type-challenge--math');

    // The same behaviour should happen on the admin page:
    $session->pageTextNotContains('Users without the "skip CAPTCHA" permission will see a CAPTCHA here');
    $session->elementNotExists('css', 'details.captcha-admin-links.form-wrapper');
    // See if instead the captcha appears:
    $session->elementExists('css', 'fieldset.captcha.captcha-type-challenge--math');

    // Logout and check the behaviour on the non admin page:
    $this->drupalLogout();
    $this->drupalGet('/test-field-xpath');

    // Go to the test page and check if the admin information won't get
    // displayed:
    $session->pageTextNotContains('Users without the "skip CAPTCHA" permission will see a CAPTCHA here');
    $session->elementNotExists('css', 'details.captcha-admin-links.form-wrapper');
    // See if instead the captcha appears:
    $session->elementExists('css', 'fieldset.captcha.captcha-type-challenge--math');
  }

  /**
   * Tests the captcha enable globally setting.
   */
  public function testCaptchaEnableGlobally() {
    // Disable login captcha to be able to log in:
    $this->disableLoginCaptchaPoint();
    $this->drupalLogin($this->adminUser);
    $session = $this->assertSession();
    // Set math challenge as default:
    $this->setDefaultChallenge('captcha/Math');

    // Enable globally:
    $this->config('captcha.settings')->set('enable_globally', TRUE)->save();

    // Go to the test page and check if there is no captcha displayed, as the
    // admin has the "skip CAPTCHA" permission:
    $this->drupalGet('/test-field-xpath');
    $session->elementNotExists('css', 'fieldset.captcha.captcha-type-challenge--math');
    $session->pageTextNotContains('This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');

    // Go to the admin form and see if there no captcha displayed:
    $this->drupalGet('/admin/config/development/performance');
    $session->elementNotExists('css', 'fieldset.captcha.captcha-type-challenge--math');
    $session->pageTextNotContains('This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');

    // Login as a user without the "skip CAPTCHA" permission and check
    // everything once again:
    $this->drupalLogout();
    $this->drupalLogin($this->userWithoutSkipCaptcha);
    $this->drupalGet('/test-field-xpath');

    // Go to the test page and check if the captcha gets displayed:
    $this->drupalGet('/test-field-xpath');
    $session->elementExists('css', 'fieldset.captcha.captcha-type-challenge--math');
    $session->pageTextContains('This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');

    // Go to the admin form and see if there no captcha is displayed:
    $this->drupalGet('/admin/config/development/performance');
    $session->elementNotExists('css', 'fieldset.captcha.captcha-type-challenge--math');
    $session->pageTextNotContains('This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');

    // Logout and check the behaviour on the non admin page:
    method_exists($this, 'drupalResetSession') ? $this->drupalResetSession() : $this->drupalLogout();
    $this->drupalGet('/test-field-xpath');

    // Go to the test page and check if the captcha gets displayed:
    $this->drupalGet('/test-field-xpath');
    $session->elementExists('css', 'fieldset.captcha.captcha-type-challenge--math');
    $session->pageTextContains('This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');
  }

  /**
   * Tests the captcha enable globally setting.
   */
  public function testCaptchaEnableGloballyOnAdminRoutes() {
    // Disable login captcha to be able to log in:
    $this->disableLoginCaptchaPoint();
    $this->drupalLogin($this->adminUser);
    $session = $this->assertSession();
    // Set math challenge as default:
    $this->setDefaultChallenge('captcha/Math');

    // Enable globally:
    $this->config('captcha.settings')->set('enable_globally', TRUE)->save();
    $this->config('captcha.settings')->set('enable_globally_on_admin_routes', TRUE)->save();

    // Go to the test page and check if there is no captcha displayed, as the
    // admin has the "skip CAPTCHA" permission:
    $this->drupalGet('/test-field-xpath');
    $session->elementNotExists('css', 'fieldset.captcha.captcha-type-challenge--math');
    $session->pageTextNotContains('This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');

    // Go to the admin form and see if there no captcha displayed:
    $this->drupalGet('/admin/config/development/performance');
    $session->elementNotExists('css', 'fieldset.captcha.captcha-type-challenge--math');
    $session->pageTextNotContains('This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');

    // Login as a user without the "skip CAPTCHA" permission and check
    // everything once again:
    $this->drupalLogout();
    $this->drupalLogin($this->userWithoutSkipCaptcha);
    $this->drupalGet('/test-field-xpath');

    // Go to the test page and check if the captcha gets displayed:
    $this->drupalGet('/test-field-xpath');
    $session->elementExists('css', 'fieldset.captcha.captcha-type-challenge--math');
    $session->pageTextContains('This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');

    // Go to the admin form and see if there is also a captcha displayed:
    $this->drupalGet('/admin/config/development/performance');
    $session->elementExists('css', 'fieldset.captcha.captcha-type-challenge--math');
    $session->pageTextContains('This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');
  }

}
