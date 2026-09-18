<?php

namespace Drupal\Tests\captcha\Kernel;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that CAPTCHA replay attacks are blocked.
 *
 * @group captcha
 */
class CaptchaTokenInvalidationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['captcha'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('captcha', ['captcha_sessions']);
    $this->installConfig('captcha');

    // Load the captcha module files.
    \Drupal::moduleHandler()->loadInclude('captcha', 'inc');
    \Drupal::moduleHandler()->loadInclude('captcha', 'module');
  }

  /**
   * Tests that replay attacks are blocked after successful CAPTCHA validation.
   */
  public function testReplayAttackIsBlocked(): void {
    // Create a CAPTCHA session.
    $captcha_sid = _captcha_generate_captcha_session('test_form');

    // Generate and store a token.
    $captcha_token = Crypt::randomBytesBase64();
    \Drupal::database()->update('captcha_sessions')
      ->fields(['token' => $captcha_token])
      ->condition('csid', $captcha_sid)
      ->execute();

    // Set a known solution.
    $solution = 'Test 123';
    _captcha_update_captcha_session($captcha_sid, $solution);

    // First submission.
    $element = $this->createCaptchaElement();
    $form_state = $this->createFormState($captcha_sid, $solution);

    captcha_validate($element, $form_state);

    // First validation should pass (no errors).
    $this->assertFalse(
      $form_state->hasAnyErrors(),
      'First CAPTCHA submission should pass without errors.'
    );

    // Replay attack - call captcha_validate() again with same session.
    $replay_form_state = $this->createFormState($captcha_sid, $solution);

    captcha_validate($element, $replay_form_state);

    $this->assertTrue(
      $replay_form_state->hasAnyErrors(),
      'Replay attack should be blocked - CAPTCHA session was already used.'
    );
  }

  /**
   * Tests that CAPTCHA token is invalidated after successful validation.
   */
  public function testTokenInvalidatedAfterSuccessfulValidation(): void {
    // Create a CAPTCHA session.
    $captcha_sid = _captcha_generate_captcha_session('test_form');

    // Generate and store a token.
    $captcha_token = Crypt::randomBytesBase64();
    \Drupal::database()->update('captcha_sessions')
      ->fields(['token' => $captcha_token])
      ->condition('csid', $captcha_sid)
      ->execute();

    // Set a known solution.
    $solution = 'Test 123';
    _captcha_update_captcha_session($captcha_sid, $solution);

    // Verify token exists before validation.
    $token_before = $this->getSessionToken($captcha_sid);
    $this->assertNotEmpty($token_before, 'Token should exist before validation.');

    // Call actual captcha_validate() with correct solution.
    $element = $this->createCaptchaElement();
    $form_state = $this->createFormState($captcha_sid, $solution);

    captcha_validate($element, $form_state);

    // Validation should pass.
    $this->assertFalse($form_state->hasAnyErrors(), 'Validation should pass.');

    // Check token after validation.
    $token_after = $this->getSessionToken($captcha_sid);

    $this->assertTrue(
      empty($token_after),
      'Token should be invalidated after successful CAPTCHA validation.'
    );
  }

  /**
   * Creates a CAPTCHA form element for testing.
   *
   * @return array
   *   The form element array.
   */
  protected function createCaptchaElement(): array {
    return [
      '#captcha_validate' => 'captcha_validate_strict_equality',
      '#captcha_info' => [
        'form_id' => 'test_form',
      ],
    ];
  }

  /**
   * Creates a FormState for CAPTCHA validation.
   *
   * @param int $captcha_sid
   *   The CAPTCHA session ID.
   * @param string $response
   *   The user's CAPTCHA response.
   *
   * @return \Drupal\Core\Form\FormState
   *   The configured form state.
   */
  protected function createFormState(int $captcha_sid, string $response): FormState {
    $form_state = new FormState();

    // Set captcha_info (required by captcha_validate).
    $form_state->set('captcha_info', [
      'this_form_id' => 'test_form',
      'captcha_sid' => $captcha_sid,
      'captcha_type' => 'Test',
      'module' => 'captcha',
      'access' => TRUE,
    ]);

    // Set the user's response.
    $form_state->setValue('captcha_response', $response);

    return $form_state;
  }

  /**
   * Gets the token for a CAPTCHA session.
   *
   * @param int $csid
   *   The CAPTCHA session ID.
   *
   * @return string|false
   *   The token, or FALSE if not found/empty.
   */
  protected function getSessionToken(int $csid) {
    return \Drupal::database()
      ->select('captcha_sessions', 'cs')
      ->fields('cs', ['token'])
      ->condition('csid', $csid)
      ->execute()
      ->fetchField();
  }

}
