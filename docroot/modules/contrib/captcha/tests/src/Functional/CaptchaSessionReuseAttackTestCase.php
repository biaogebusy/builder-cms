<?php

namespace Drupal\Tests\captcha\Functional;

use Drupal\captcha\Constants\CaptchaConstants;

/**
 * Tests CAPTCHA session reusing.
 *
 * @group captcha
 */
class CaptchaSessionReuseAttackTestCase extends CaptchaWebTestBase {

  /**
   * Assert that the CAPTCHA session ID reuse attack was detected.
   */
  protected function assertCaptchaSessionIdReuseAttackDetection() {
    // There should be an error message about wrong response.
    $this->assertSession()->pageTextContains(self::CAPTCHA_WRONG_RESPONSE_ERROR_MESSAGE);
  }

  /**
   * Test captcha attack detection on comment form.
   */
  public function testCaptchaSessionReuseAttackDetectionOnCommentPreview() {
    // Create commentable node.
    $node = $this->drupalCreateNode();
    // Set Test CAPTCHA on comment form.
    captcha_set_form_id_setting(self::COMMENT_FORM_ID, 'captcha/Test');
    $this->config('captcha.settings')
      ->set('persistence', CaptchaConstants::CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_INSTANCE)
      ->save();

    // Log in as normal user.
    $this->drupalLogin($this->normalUser);

    // Go to comment form of commentable node.
    $this->drupalGet('comment/reply/node/' . $node->id() . '/comment');
    $this->assertCaptchaPresence(TRUE);

    // Get CAPTCHA session ID and solution of the challenge.
    $captcha_sid = $this->getCaptchaSidFromForm();
    $captcha_token = $this->getCaptchaTokenFromForm();
    $solution = "Test 123";

    // Post the form with the solution.
    $edit = $this->getCommentFormValues();
    $edit['captcha_response'] = $solution;
    $this->submitForm($edit, 'Preview');
    // Answer should be accepted and further CAPTCHA omitted.
    $this->assertCaptchaResponseAccepted();
    $this->assertCaptchaPresence(FALSE);

    // Go to comment form of commentable node again.
    $this->drupalGet('comment/reply/node/' . $node->id() . '/comment');

    // Post a new comment, reusing the previous CAPTCHA session.
    $edit = $this->getCommentFormValues();
    $this->assertSession()->hiddenFieldExists("captcha_sid")->setValue((string) $captcha_sid);
    $this->assertSession()->hiddenFieldExists("captcha_token")->setValue((string) $captcha_token);
    $edit['captcha_response'] = $solution;
    $this->submitForm($edit, 'Preview');
    // CAPTCHA session reuse attack should be detected.
    $this->assertCaptchaSessionIdReuseAttackDetection();
    // There should be a CAPTCHA.
    $this->assertCaptchaPresence(TRUE);
  }

  /**
   * Test captcha attach detection on node form.
   */
  public function testCaptchaSessionReuseAttackDetectionOnNodeForm() {
    // Set CAPTCHA on page form.
    captcha_set_form_id_setting('node_page_form', 'captcha/Test');
    $this->config('captcha.settings')
      ->set('persistence', CaptchaConstants::CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_INSTANCE)
      ->save();

    // Log in as normal user.
    $this->drupalLogin($this->normalUser);

    // Go to node add form.
    $this->drupalGet('node/add/page');
    $this->assertCaptchaPresence(TRUE);

    // Get CAPTCHA session ID and solution of the challenge.
    $captcha_sid = $this->getCaptchaSidFromForm();
    $captcha_token = $this->getCaptchaTokenFromForm();
    $solution = "Test 123";

    // Page settings to post, with correct CAPTCHA answer.
    $edit = $this->getNodeFormValues();
    $edit['captcha_response'] = $solution;
    // Preview the node.
    $this->submitForm($edit, 'Preview');
    // Answer should be accepted.
    $this->assertCaptchaResponseAccepted();
    // Check that there is no CAPTCHA after preview.
    $this->assertCaptchaPresence(FALSE);

    // Go to node add form again.
    $this->drupalGet('node/add/page');
    $this->assertCaptchaPresence(TRUE);

    // Post a new node, reusing the previous CAPTCHA session.
    $edit = $this->getNodeFormValues();
    $this->assertSession()->hiddenFieldExists("captcha_sid")->setValue((string) $captcha_sid);
    $this->assertSession()->hiddenFieldExists("captcha_token")->setValue((string) $captcha_token);
    $edit['captcha_response'] = $solution;
    $this->submitForm($edit, 'Preview');
    // CAPTCHA session reuse attack should be detected.
    $this->assertCaptchaSessionIdReuseAttackDetection();
    // There should be a CAPTCHA.
    $this->assertCaptchaPresence(TRUE);
  }

  /**
   * Test Captcha attack detection on login form.
   */
  public function testCaptchaSessionReuseAttackDetectionOnLoginForm() {
    // Set CAPTCHA on login form.
    captcha_set_form_id_setting('user_login_form', 'captcha/Test');
    $this->config('captcha.settings')
      ->set('persistence', CaptchaConstants::CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_INSTANCE)
      ->save();

    // Go to log in form.
    $this->drupalGet('<front>');
    $this->assertCaptchaPresence(TRUE);

    // Get CAPTCHA session ID and solution of the challenge.
    $captcha_sid = $this->getCaptchaSidFromForm();
    $captcha_token = $this->getCaptchaTokenFromForm();
    $solution = "Test 123";

    // Log in through form.
    $edit = [
      'name' => $this->normalUser->getDisplayName(),
      'pass' => $this->normalUser->pass_raw,
      'captcha_response' => $solution,
    ];
    $this->submitForm($edit, 'Log in', self::LOGIN_HTML_FORM_ID);
    $this->assertCaptchaResponseAccepted();
    $this->assertCaptchaPresence(FALSE);
    // If a "log out" link appears on the page, it is almost certainly because
    // the login was successful.
    $this->assertSession()->pageTextContains($this->normalUser->getDisplayName());

    // Log out again.
    $this->drupalLogout();

    // Go to log in form again.
    $this->drupalGet('<front>');

    // Try to log in again, reusing the previous CAPTCHA session.
    $this->assertSession()->hiddenFieldExists("captcha_sid")->setValue((string) $captcha_sid);
    $this->assertSession()->hiddenFieldExists("captcha_token")->setValue((string) $captcha_token);
    $this->assertNotEmpty(json_encode($edit));
    $this->submitForm($edit, 'Log in');
    // CAPTCHA session reuse attack should be detected.
    $this->assertCaptchaSessionIdReuseAttackDetection();
    // There should be a CAPTCHA.
    $this->assertCaptchaPresence(TRUE);
  }

  /**
   * Test multiple captcha widgets on single page.
   */
  public function testMultipleCaptchaProtectedFormsOnOnePage() {
    \Drupal::service('module_installer')->install(['block']);
    $this->drupalPlaceBlock('user_login_block');
    // Set Test CAPTCHA on comment form and login block.
    captcha_set_form_id_setting(self::COMMENT_FORM_ID, 'captcha/Test');
    captcha_set_form_id_setting('user_login_form', 'captcha/Test');
    $this->allowCommentPostingForAnonymousVisitors();

    // Create a node with comments enabled.
    $node = $this->drupalCreateNode();

    // Preview comment with correct CAPTCHA answer.
    $edit = $this->getCommentFormValues();
    $comment_subject = $edit['subject[0][value]'];
    $edit['captcha_response'] = 'Test 123';
    $this->drupalGet('comment/reply/node/' . $node->id() . '/comment');
    $this->submitForm($edit, 'Preview');
    // Post should be accepted: no warnings,
    // no CAPTCHA reuse detection (which could be used by user log in block).
    $this->assertCaptchaResponseAccepted();
    $this->assertSession()->pageTextContains($comment_subject);
  }

}
