<?php

namespace Drupal\Tests\captcha\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\captcha\Constants\CaptchaConstants;

/**
 * Tests CAPTCHA main test case sensitivity.
 *
 * @group captcha
 */
class CaptchaTest extends CaptchaWebTestBase {

  use StringTranslationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'captcha_long_form_id_test',
    'captcha_test',
  ];

  /**
   * Testing the protection of the user log in form.
   */
  public function testCaptchaOnLoginForm() {
    // Create user and test log in without CAPTCHA.
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);
    // Log out again.
    $this->drupalLogout();

    // Set a CAPTCHA on login form.
    /** @var \Drupal\captcha\Entity\CaptchaPoint $captcha_point */
    $captcha_point = \Drupal::entityTypeManager()
      ->getStorage('captcha_point')
      ->load('user_login_form');
    $captcha_point->setCaptchaType(CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE);
    $captcha_point->enable()->save();

    // Check if there is a CAPTCHA on the login form (look for the title).
    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);

    // Try to log in, which should fail.
    $edit = [
      'name' => $user->getDisplayName(),
      'pass' => $user->pass_raw,
      'captcha_response' => '?',
    ];
    $this->submitForm($edit, 'Log in', self::LOGIN_HTML_FORM_ID);
    // Check for error message.
    $this->assertSession()->pageTextContains(self::CAPTCHA_WRONG_RESPONSE_ERROR_MESSAGE);

    // And make sure that user is not logged in:
    // check for name and password fields on ?q=user.
    $this->drupalGet('user');
    $this->assertSession()->fieldExists('name');
    $this->assertSession()->fieldExists('pass');
  }

  /**
   * Testing the response error menssage.
   */
  public function testCaptchaResponseErrorMessage() {
    // Customize the response error message.
    $this->drupalLogin($this->adminUser);
    $customized_menssage = 'The answer you entered is wrong.';
    $edit = [
      'wrong_captcha_response_message' => $customized_menssage,
    ];
    $this->drupalGet("admin/config/people/captcha");
    $this->submitForm($edit, 'Save configuration');

    // Set a CAPTCHA on login form.
    /** @var \Drupal\captcha\Entity\CaptchaPoint $captcha_point */
    $captcha_point = \Drupal::entityTypeManager()
      ->getStorage('captcha_point')
      ->load('user_login_form');
    $captcha_point->setCaptchaType(CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE);
    $captcha_point->enable()->save();

    // Check if the menssage is default.
    $this->drupalLogout();
    $this->drupalGet('user');
    // Try to log in, which should fail.
    $edit = [
      'name' => $this->adminUser->getDisplayName(),
      'pass' => $this->adminUser->pass_raw,
      'captcha_response' => '?',
    ];
    $this->submitForm($edit, 'Log in', self::LOGIN_HTML_FORM_ID);
    $this->assertSession()->pageTextContains($customized_menssage);

  }

  /**
   * Assert function for testing if comment posting works as it should.
   *
   * Creates node with comment writing enabled, tries to post comment
   * with given CAPTCHA response (caller should enable the desired
   * challenge on page node comment forms) and checks if
   * the result is as expected.
   *
   * @param string $captcha_response
   *   The response on the CAPTCHA.
   * @param bool $should_pass
   *   Describing if the posting should pass or should be blocked.
   * @param string $message
   *   To prefix to nested asserts.
   */
  protected function assertCommentPosting($captcha_response, $should_pass, $message) {
    // Make sure comments on pages can be saved directly without preview.
    $this->container->get('state')
      ->set('comment_preview_page', DRUPAL_OPTIONAL);

    // Create a node with comments enabled.
    $node = $this->drupalCreateNode();

    // Post comment on node.
    $edit = $this->getCommentFormValues();
    $comment_subject = $edit['subject[0][value]'];
    $comment_body = $edit['comment_body[0][value]'];
    $edit['captcha_response'] = $captcha_response;
    $this->drupalGet('comment/reply/node/' . $node->id() . '/comment');
    $this->submitForm($edit, 'Save', 'comment-form');

    if ($should_pass) {
      // There should be no error message.
      $this->assertCaptchaResponseAccepted();
      // Get node page and check that comment shows up.
      $this->drupalGet('node/' . $node->id());
      $this->assertSession()->pageTextContains($comment_subject);
      $this->assertSession()->pageTextContains($comment_body);
    }
    else {
      // Check for error message.
      $this->assertSession()->pageTextContains(self::CAPTCHA_WRONG_RESPONSE_ERROR_MESSAGE);
      // Get node page and check that comment is not present.
      $this->drupalGet('node/' . $node->id());
      $this->assertSession()->pageTextNotContains($comment_subject);
      $this->assertSession()->pageTextNotContains($comment_body);
    }
  }

  /**
   * Testing the case sensitive/insensitive validation.
   */
  public function testCaseInsensitiveValidation() {
    $config = $this->config('captcha.settings');
    // Set Test CAPTCHA on comment form.
    captcha_set_form_id_setting(self::COMMENT_FORM_ID, 'captcha/Test');

    // Log in as normal user.
    $this->drupalLogin($this->normalUser);

    // Test case sensitive posting.
    $config->set('default_validation', CaptchaConstants::CAPTCHA_DEFAULT_VALIDATION_CASE_SENSITIVE);
    $config->save();

    $this->assertCommentPosting('Test 123', TRUE, 'Case sensitive validation of right casing.');
    $this->assertCommentPosting('test 123', FALSE, 'Case sensitive validation of wrong casing.');
    $this->assertCommentPosting('TEST 123', FALSE, 'Case sensitive validation of wrong casing.');

    // Test case insensitive posting (the default).
    $config->set('default_validation', CaptchaConstants::CAPTCHA_DEFAULT_VALIDATION_CASE_INSENSITIVE);
    $config->save();

    $this->assertCommentPosting('Test 123', TRUE, 'Case insensitive validation of right casing.');
    $this->assertCommentPosting('test 123', TRUE, 'Case insensitive validation of wrong casing.');
    $this->assertCommentPosting('TEST 123', TRUE, 'Case insensitive validation of wrong casing.');
  }

  /**
   * Test if the CAPTCHA description is only shown with  challenge widgets.
   *
   * For example, when a comment is previewed with correct CAPTCHA answer,
   * a challenge is generated and added to the form but removed in the
   * pre_render phase. The CAPTCHA description should not show up either.
   *
   * @see testCaptchaSessionReuseOnNodeForms()
   */
  public function testCaptchaDescriptionAfterCommentPreview() {
    // Set Test CAPTCHA on comment form.
    captcha_set_form_id_setting(self::COMMENT_FORM_ID, 'captcha/Test');

    // Log in as normal user.
    $this->drupalLogin($this->normalUser);

    // Create a node with comments enabled.
    $node = $this->drupalCreateNode();

    // Preview comment with correct CAPTCHA answer.
    $edit = $this->getCommentFormValues();
    $edit['captcha_response'] = 'Test 123';
    $this->drupalGet('comment/reply/node/' . $node->id() . '/comment');
    $this->submitForm($edit, 'Preview');

    // Check that there is no CAPTCHA after preview.
    $this->assertCaptchaPresence(FALSE);
  }

  /**
   * Test if the CAPTCHA session ID is reused when previewing nodes.
   *
   * Node preview after correct response should not show CAPTCHA anymore.
   * The preview functionality of comments and nodes works
   * slightly different under the hood.
   * CAPTCHA module should be able to handle both.
   *
   * @see testCaptchaDescriptionAfterCommentPreview()
   */
  public function testCaptchaSessionReuseOnNodeForms() {
    // Set Test CAPTCHA on page form.
    captcha_set_form_id_setting('node_page_form', 'captcha/Test');

    // Log in as normal user.
    $this->drupalLogin($this->normalUser);

    // Page settings to post, with correct CAPTCHA answer.
    $edit = $this->getNodeFormValues();
    $edit['captcha_response'] = 'Test 123';
    $this->drupalGet('node/add/page');
    $this->submitForm($edit, 'Preview');

    $this->assertCaptchaPresence(FALSE);
  }

  /**
   * CAPTCHA should be put on admin pages even if visitor has no access.
   */
  public function testCaptchaOnLoginBlockOnAdminPagesIssue893810() {
    // Set a CAPTCHA on login block form.
    /** @var \Drupal\captcha\Entity\CaptchaPoint $captcha_point */
    $captcha_point = \Drupal::entityTypeManager()
      ->getStorage('captcha_point')
      ->load('user_login_form');
    $captcha_point->setCaptchaType(CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE);
    $captcha_point->enable()->save();

    // Enable the user login block.
    $this->drupalPlaceBlock('user_login_block', ['id' => 'login']);

    // Check if there is a CAPTCHA on home page.
    $this->drupalGet('');
    $this->assertCaptchaPresence(TRUE);

    // Check there is a CAPTCHA on "forbidden" admin pages.
    $this->drupalGet('admin');
    $this->assertCaptchaPresence(TRUE);
  }

  /**
   * Test that forms with IDs exceeding 64 characters can be assigned captchas.
   */
  public function testLongFormId() {
    // We add the form manually so we can mimic the character
    // truncation of the label field as formId.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH);

    $label = 'this_formid_is_intentionally_longer_than_64_characters_to_test_captcha';
    // Truncated to 64 chars so it can be a machine name.
    $formId = substr($label, 0, 64);

    $form_values = [
      'label' => $label,
      'formId' => $formId,
      'captchaType' => CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE,
    ];

    // Create intentionally long id Captcha Point.
    $this->drupalGet(self::CAPTCHA_ADMIN_PATH . '/captcha-points/add');
    $this->submitForm($form_values, 'Save');
    $this->assertSession()->responseContains($this->t('Captcha Point for %label form was created.', ['%label' => $formId]));

    // We need to log out to test the captcha.
    $this->drupalLogout();

    // Navigate to the form with a >64 char id and confirm there is Captcha.
    $this->drupalGet('captcha/test_form/long_id');
    $this->assertCaptchaPresence(TRUE);
  }

  /**
   * Test if the correct classes from our twig template are set.
   */
  public function testFormCorrectClassesSet() {
    $session = $this->assertSession();

    // Set default challenge math:
    $this->config('captcha.settings')
      ->set('default_challenge', CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE)
      ->save();

    // Check if there is a CAPTCHA on the login form (look for the title).
    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);

    // Check if the correct classes are set from our template with default
    // challenge type set:
    // Check if fieldset exists with correct classes set:
    $session->elementExists('css', '#user-login-form > fieldset');
    $session->elementAttributeContains('css', '#user-login-form > fieldset', 'class', 'captcha');
    $session->elementAttributeContains('css', '#user-login-form > fieldset', 'class', 'captcha-type-challenge--math');
    // The challenge type should NEVER be 'default'.
    $session->elementAttributeNotContains('css', '#user-login-form > fieldset', 'class', 'captcha-type-challenge--default');

    // Check if title exists with the correct class and standard title value:
    $session->elementExists('css', '#user-login-form > fieldset > legend.captcha__title');
    $session->elementTextContains('css', '#user-login-form > fieldset > legend', 'CAPTCHA');

    // Check if description exists with the correct class and standard title
    // value:
    $session->elementExists('css', '#user-login-form > fieldset > div.captcha__description');
    $session->elementTextContains('css', '#user-login-form > fieldset > div.captcha__description', 'This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');

    // Check if the element exists with the correct class:
    $session->elementExists('css', '#user-login-form > fieldset > div.captcha__element');

    // Set challenge type "captcha/Math" explicitly and do the tests again.
    /** @var \Drupal\captcha\Entity\CaptchaPoint $captcha_point */
    $captcha_point = \Drupal::entityTypeManager()
      ->getStorage('captcha_point')
      ->load('user_login_form');
    $captcha_point->setCaptchaType(CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE);
    $captcha_point->enable()->save();

    $this->drupalGet('user');

    // Check if fieldset exists with correct classes set:
    $session->elementExists('css', '#user-login-form > fieldset');
    $session->elementAttributeContains('css', '#user-login-form > fieldset', 'class', 'captcha');
    $session->elementAttributeContains('css', '#user-login-form > fieldset', 'class', 'captcha-type-challenge--math');
    // The challenge type should NEVER be 'default'.
    $session->elementAttributeNotContains('css', '#user-login-form > fieldset', 'class', 'captcha-type-challenge--default');

    // Check if title exists with the correct class and standard title value:
    $session->elementExists('css', '#user-login-form > fieldset > legend.captcha__title');
    $session->elementTextContains('css', '#user-login-form > fieldset > legend', 'CAPTCHA');

    // Check if description exists with the correct class and standard title
    // value:
    $session->elementExists('css', '#user-login-form > fieldset > div.captcha__description');
    $session->elementTextContains('css', '#user-login-form > fieldset > div.captcha__description', 'This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');

    // Check if the element exists with the correct class:
    $session->elementExists('css', '#user-login-form > fieldset > div.captcha__element');
  }

  /**
   * Test if the title element is not present, when title is an empty string.
   */
  public function testTitleNotPresent() {
    $session = $this->assertSession();

    // Set default challenge math:
    $this->config('captcha.settings')
      ->set('title', '')
      ->set('default_challenge', CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE)
      ->save();

    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);

    // Check if the title element does not exist:
    $session->elementNotExists('css', '#user-login-form > fieldset > legend.captcha__title');
    // Even the fieldset should not exist:
    $session->elementNotExists('css', '#user-login-form > fieldset.captcha');
    // But instead a div should be used:
    $session->elementExists('css', '#user-login-form > div.captcha');
    // Containing the captcha element:
    $session->elementExists('css', '#user-login-form > div.captcha > div.captcha__element');
  }

  /**
   * Test if the description element is not present, when title is empty.
   */
  public function testDescriptionNotPresent() {
    $session = $this->assertSession();

    // Set default challenge math:
    $this->config('captcha.settings')
      ->set('description', '')
      ->set('default_challenge', CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE)
      ->save();

    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);

    // Check if the description element does not exist:
    $session->elementNotExists('css', '#user-login-form > fieldset.captcha > div.captcha__description');
    // But the captcha element exists:
    $session->elementExists('css', '#user-login-form > fieldset.captcha > div.captcha__element');
  }

  /**
   * Test if the description and title element is not present, when title empty.
   */
  public function testDescriptionAndTitleNotPresent() {
    $session = $this->assertSession();

    // Set default challenge math:
    $this->config('captcha.settings')
      ->set('title', '')
      ->set('description', '')
      ->set('default_challenge', CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE)
      ->save();

    $this->drupalGet('user');
    $this->assertCaptchaPresence(TRUE);

    // Check if the title element does not exist:
    $session->elementNotExists('css', '#user-login-form > fieldset > label.captcha__title');
    $session->elementNotExists('css', '#user-login-form > div > label.captcha__title');
    // Check if the title element does not exist:
    $session->elementNotExists('css', '#user-login-form > fieldset > div.captcha__description');
    $session->elementNotExists('css', '#user-login-form > div > div.captcha__description');
    // Even the fieldset should not exist:
    $session->elementNotExists('css', '#user-login-form > fieldset');
    // But instead a div should be used, just containing the captcha element:
    $session->elementExists('css', '#user-login-form > div.captcha');
    $session->elementExists('css', '#user-login-form > div.captcha > div.captcha__element');
  }

  /**
   * Tests the math form element and its structure.
   */
  public function testMathFormElement() {
    $session = $this->assertSession();

    $this->drupalLogin($this->adminUser);

    $this->drupalGet('/captcha-test/test');
    $session->statusCodeEquals(200);

    $session->elementExists('css', '#captcha-test-test');

    $session->elementExists('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-true"]');

    // Check the first captcha form element and see if it is complete:
    // Check captcha description:
    $session->elementExists('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-true"] > div.captcha__description');
    $session->elementTextContains('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-true"] > div.captcha__description', 'This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.');
    $session->elementExists('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-true"] > div.captcha__element > div.form-item-captcha-response');
    // Check Question label:
    $session->elementExists('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-true"] > div.captcha__element > div.form-item-captcha-response > label.form-required');
    $session->elementTextContains('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-true"] > div.captcha__element > div.form-item-captcha-response > label.form-required', 'Math question');
    // Check other elements:
    $session->elementExists('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-true"] > div.captcha__element > div.form-item-captcha-response > input.form-text');
    $session->elementExists('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-true"] > div.captcha__element > div.form-item-captcha-response > div#edit-captcha-response--description');
    $session->elementTextContains('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-true"] > div.captcha__element > div.form-item-captcha-response > div#edit-captcha-response--description', 'Solve this simple math problem and enter the result. E.g. for 1+3, enter 4.');
  }

  /**
   * Tests the math form element behaviour.
   *
   * @todo This test will fail, because the "skip CAPTCHA" permission doesn't
   * work for Captcha form elements, but only in conjunction with captcha
   * points. The problem is the captcha rendering on two seperate levels. For
   * more informations, see
   * https://www.drupal.org/project/captcha/issues/2941496
   */
  public function todoTestMathFormElementBehaviour() {
    $session = $this->assertSession();

    $this->drupalLogin($this->adminUser);

    $this->drupalGet('/captcha-test/test');
    $session->statusCodeEquals(200);

    $session->elementExists('css', '#captcha-test-test');

    // As our admin user has the "skip CAPTCHA" permission, they should only see
    // the first captcha element on the page:
    $session->elementExists('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-true"]');
    $session->elementNotExists('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-false"]');
    $session->elementNotExists('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-not-set"]');

    $this->drupalLogout();
    $this->drupalLogin($this->normalUser);
    $this->drupalGet('/captcha-test/test');

    // As our normal user does not have the "skip CAPTCHA" permission, they
    // should be able to see all three captchas:
    $session->elementExists('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-true"]');
    $session->elementExists('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-false"]');
    $session->elementExists('css', '#captcha-test-test > fieldset[data-drupal-selector="edit-math-captcha-admin-not-set"]');
  }

}
