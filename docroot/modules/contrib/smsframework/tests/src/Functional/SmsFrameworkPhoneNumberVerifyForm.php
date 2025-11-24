<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Functional;

use Drupal\Core\Url;

/**
 * Tests phone numbers verification code form.
 *
 * @group SMS Framework
 */
final class SmsFrameworkPhoneNumberVerifyForm extends SmsFrameworkBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test'];

  /**
   * Test phone number verification form.
   */
  public function testVerifyFormAccess(): void {
    // Anonymous.
    $this->drupalGet(Url::fromRoute('sms.phone.verify'));
    $this->assertSession()->statusCodeEquals(403);

    // User with permission.
    $account = $this->drupalCreateUser([
      'sms verify phone number',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet(Url::fromRoute('sms.phone.verify'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(t('Verify a phone number'));
    $this->assertSession()->pageTextContains(t('Enter the code you received from a SMS message.'));
  }

  /**
   * Test phone number verification form.
   */
  public function testVerifyForm(): void {
    $account = $this->drupalCreateUser([
      'sms verify phone number',
    ]);
    $this->drupalLogin($account);

    $this->createEntityWithPhoneNumber(
      $this->createPhoneNumberSettings('entity_test', 'entity_test'),
      ['+123123123'],
    );

    $verification = $this->getLastVerification();
    $code = $verification->getCode();

    static::assertFalse($verification->getStatus(), 'Phone number verification is not verified.');
    static::assertFalse(empty($code), 'Verification code is set.');

    // Invalid code.
    $edit['code'] = $this->randomMachineName();
    $this->drupalGet(Url::fromRoute('sms.phone.verify'));
    $this->submitForm($edit, 'Verify code');
    $this->assertSession()->pageTextContains(t('Invalid verification code.'));

    // Valid code.
    $edit['code'] = $code;
    $this->drupalGet(Url::fromRoute('sms.phone.verify'));
    $this->submitForm($edit, 'Verify code');
    $this->assertSession()->pageTextContains(t('Phone number is now verified.'));

    // Reset verification code static cache.
    $this->resetAll();
    $verification = $this->getLastVerification();
    static::assertTrue($verification->getStatus(), 'Phone number is verified.');
  }

  /**
   * Test phone number verification form.
   */
  public function testVerifyFormFlood(): void {
    // Reduce number of POST requests. Number isn't important.
    \Drupal::configFactory()->getEditable('sms.settings')
      ->set('flood.verify_limit', 1)
      ->save();

    $account = $this->drupalCreateUser([
      'sms verify phone number',
    ]);
    $this->drupalLogin($account);

    $edit['code'] = $this->randomMachineName();
    $this->drupalGet(Url::fromRoute('sms.phone.verify'));
    $this->submitForm($edit, 'Verify code');
    $this->assertSession()->responseNotContains(t('There has been too many failed verification attempts. Try again later.'));
    $this->drupalGet(Url::fromRoute('sms.phone.verify'));
    $this->submitForm($edit, 'Verify code');
    $this->assertSession()->pageTextContains(t('There has been too many failed verification attempts. Try again later.'));
  }

  /**
   * Test changing verification path.
   */
  public function testVerifyPathSettings(): void {
    $account = $this->drupalCreateUser([
      'sms verify phone number',
      'administer smsframework',
    ]);
    $this->drupalLogin($account);

    // Hard code path, don't use Url::fromRoute.
    $this->drupalGet('/verify');
    $this->assertSession()->statusCodeEquals(200, 'Default phone number verification route exists at /verify');

    $path_verify = '/' . $this->randomMachineName() . '/' . $this->randomMachineName();
    $this->drupalGet(Url::fromRoute('sms.settings'));
    $this->submitForm([
      'pages[verify]' => $path_verify,
    ], 'Save configuration');

    // Ensure the route cache is rebuilt by getting the verify route.
    $this->drupalGet($path_verify);
    $this->assertSession()->statusCodeEquals(200, 'Phone number verification route changed to ' . $path_verify);
    $this->drupalGet('/verify');
    $this->assertSession()->statusCodeEquals(404, 'Previous route path was invalidated.');
  }

}
