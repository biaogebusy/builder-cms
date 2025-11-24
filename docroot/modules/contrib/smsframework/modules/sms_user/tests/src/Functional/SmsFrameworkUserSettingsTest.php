<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms_user\Functional;

use Drupal\Tests\sms\Functional\SmsFrameworkBrowserTestBase;
use Drupal\Core\Url;

/**
 * Tests SMS User settings user interface.
 *
 * @group SMS User
 */
class SmsFrameworkUserSettingsTest extends SmsFrameworkBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sms_user'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * List of days in a week, starting from 'sunday' through to 'saturday'.
   *
   * @var string[]
   */
  protected array $days = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $account = $this->drupalCreateUser([
      'administer smsframework',
    ]);
    $this->drupalLogin($account);

    // Build list of days.
    $date = new \DateTime('next Sunday');
    while (($day = strtolower($date->format('l'))) && !in_array($day, $this->days)) {
      $this->days[] = $day;
      $date->modify('+1 day');
    }
  }

  /**
   * Tests saving form and verifying configuration is saved.
   */
  public function testSettingsForm(): void {
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->fieldExists('active_hours[status]');
    $this->assertSession()->checkboxNotChecked('edit-active-hours-status');

    // Ensure default select field values.
    foreach ($this->days as $day) {
      $optionElement = $this->assertSession()->optionExists('edit-active-hours-days-' . $day . '-start', '-1');
      static::assertTrue($optionElement->hasAttribute('selected'));
    }
    foreach ($this->days as $day) {
      $optionElement = $this->assertSession()->optionExists('edit-active-hours-days-' . $day . '-end', '24');
      static::assertTrue($optionElement->hasAttribute('selected'));
    }

    $edit = [
      'active_hours[status]' => TRUE,
      'active_hours[days][sunday][start]' => 2,
      'active_hours[days][sunday][end]' => 22,
      'active_hours[days][tuesday][start]' => 0,
      'active_hours[days][tuesday][end]' => 24,
      // This day wont save because start is set to disabled.
      'active_hours[days][thursday][start]' => -1,
      'active_hours[days][thursday][end]' => 18,
    ];
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('The configuration options have been saved.');

    // Check values are saved and form reflects this.
    $this->assertSession()->checkboxChecked('edit-active-hours-status');
    $optionElement = $this->assertSession()->optionExists('edit-active-hours-days-sunday-start', '2');
    static::assertTrue($optionElement->hasAttribute('selected'));
    $optionElement = $this->assertSession()->optionExists('edit-active-hours-days-sunday-end', '22');
    static::assertTrue($optionElement->hasAttribute('selected'));
    $optionElement = $this->assertSession()->optionExists('edit-active-hours-days-tuesday-start', '0');
    static::assertTrue($optionElement->hasAttribute('selected'));
    $optionElement = $this->assertSession()->optionExists('edit-active-hours-days-tuesday-end', '24');
    static::assertTrue($optionElement->hasAttribute('selected'));
    $optionElement = $this->assertSession()->optionExists('edit-active-hours-days-thursday-start', '-1');
    static::assertTrue($optionElement->hasAttribute('selected'));
    $optionElement = $this->assertSession()->optionExists('edit-active-hours-days-thursday-end', '24');
    static::assertTrue($optionElement->hasAttribute('selected'));

    $ranges_expected = [
      ['start' => 'sunday 2:00', 'end' => 'sunday 22:00'],
      ['start' => 'tuesday 0:00', 'end' => 'tuesday +1 day'],
    ];
    $ranges_actual = \Drupal::config('sms_user.settings')
      ->get('active_hours.ranges');
    static::assertEquals($ranges_expected, $ranges_actual);
  }

  /**
   * Tests saving form with invalid values.
   */
  public function testSettingsFormValidationFail(): void {
    // End time < start time.
    $edit = [
      'active_hours[days][wednesday][start]' => 10,
      'active_hours[days][wednesday][end]' => 9,
    ];

    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('End time must be greater than start time.');

    // Active hours enabled but no days.
    $edit = [
      'active_hours[status]' => TRUE,
      'active_hours[days][wednesday][start]' => -1,
      'active_hours[days][wednesday][end]' => 24,
    ];
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('If active hours hours are enabled there must be at least one enabled day.');
  }

  /**
   * Test account registrations are off.
   */
  public function testAccountRegistrationOff(): void {
    $edit = [
      'account_registration[behaviour]' => 'none',
    ];
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('The configuration options have been saved.');

    $settings = $this->config('sms_user.settings')->get('account_registration');
    static::assertFalse($settings['unrecognized_sender']['status']);
    static::assertFalse($settings['incoming_pattern']['status']);
  }

  /**
   * Test fallback token list for when token.module not available.
   */
  public function testAccountRegistrationReplyTokens(): void {
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Available tokens include: [sms-message:*] [user:*]');
  }

  /**
   * Test account registrations for unrecognised numbers saves to config.
   */
  public function testAccountRegistrationUnrecognised(): void {
    $this->createPhoneNumberSettings('user', 'user');

    $reply_message = $this->randomString();
    $edit = [
      'account_registration[behaviour]' => 'all',
      'account_registration[all_options][reply_status]' => TRUE,
      'account_registration[all_options][reply][message]' => $reply_message,
    ];
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('The configuration options have been saved.');

    $settings = $this->config('sms_user.settings')->get('account_registration');

    // Status.
    static::assertTrue($settings['unrecognized_sender']['status']);
    static::assertFalse($settings['incoming_pattern']['status']);

    // Settings.
    static::assertTrue($settings['unrecognized_sender']['reply']['status']);
    static::assertEquals($reply_message, $settings['unrecognized_sender']['reply']['message']);
  }

  /**
   * Test account registrations for incoming pattern saves to config.
   */
  public function testAccountRegistrationIncomingPattern(): void {
    $this->createPhoneNumberSettings('user', 'user');

    $incoming_message = '[email] ' . $this->randomString();
    $reply_message_success = $this->randomString();
    $reply_message_failure = $this->randomString();
    $edit = [
      'account_registration[behaviour]' => 'incoming_pattern',
      'account_registration[incoming_pattern_options][incoming_message]' => $incoming_message,
      'account_registration[incoming_pattern_options][send_activation_email]' => TRUE,
      'account_registration[incoming_pattern_options][reply_status]' => TRUE,
      'account_registration[incoming_pattern_options][reply][message_success]' => $reply_message_success,
      'account_registration[incoming_pattern_options][reply][message_failure]' => $reply_message_failure,
    ];
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('The configuration options have been saved.');

    $settings = $this->config('sms_user.settings')->get('account_registration');

    // Status.
    static::assertFalse($settings['unrecognized_sender']['status']);
    static::assertTrue($settings['incoming_pattern']['status']);

    // Settings.
    static::assertEquals($incoming_message, $settings['incoming_pattern']['incoming_messages'][0]);
    static::assertTrue($settings['incoming_pattern']['send_activation_email']);
    static::assertTrue($settings['incoming_pattern']['reply']['status']);
    static::assertEquals($reply_message_success, $settings['incoming_pattern']['reply']['message']);
    static::assertEquals($reply_message_failure, $settings['incoming_pattern']['reply']['message_failure']);
  }

  /**
   * Test account registrations validation failures on empty replies.
   */
  public function testAccountRegistrationValidationEmptyReplies(): void {
    $this->createPhoneNumberSettings('user', 'user');

    $edit = [
      'account_registration[behaviour]' => 'all',
      'account_registration[all_options][reply_status]' => TRUE,
      'account_registration[all_options][reply][message]' => '',
    ];
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('Reply message must have a value if reply is enabled.', 'Validation failed for message on all unrecognised numbers when reply status is enabled.');

    $edit = [
      'account_registration[behaviour]' => 'incoming_pattern',
      'account_registration[incoming_pattern_options][reply_status]' => TRUE,
      'account_registration[incoming_pattern_options][reply][message_success]' => '',
    ];
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('Reply message must have a value if reply is enabled.', 'Validation failed for message_success on incoming_pattern when reply status is enabled.');

    $edit = [
      'account_registration[behaviour]' => 'incoming_pattern',
      'account_registration[incoming_pattern_options][reply_status]' => TRUE,
      'account_registration[incoming_pattern_options][reply][message_failure]' => '',
    ];
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('Reply message must have a value if reply is enabled.', 'Validation failed for message_failure on incoming_pattern when reply status is enabled.');
  }

  /**
   * Test account registrations validation failures on empty replies.
   */
  public function testAccountRegistrationValidationIncomingPattern(): void {
    $this->createPhoneNumberSettings('user', 'user');

    $edit = [
      'account_registration[behaviour]' => 'incoming_pattern',
      'account_registration[incoming_pattern_options][incoming_message]' => '',
    ];
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('Incoming message must be filled if using pre-incoming_pattern option');

    $edit = [
      'account_registration[behaviour]' => 'incoming_pattern',
      'account_registration[incoming_pattern_options][send_activation_email]' => TRUE,
      'account_registration[incoming_pattern_options][incoming_message]' => $this->randomString(),
    ];
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('Activation email cannot be sent if [email] placeholder is missing.');

    $edit = [
      'account_registration[behaviour]' => 'incoming_pattern',
      'account_registration[incoming_pattern_options][send_activation_email]' => TRUE,
      'account_registration[incoming_pattern_options][incoming_message]' => 'E [email] P [password]',
    ];
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('Activation email cannot be sent if [password] placeholder is present.');

    // Placeholder seperation.
    // Tests separator so regex doesn't have problems.
    $edit = [
      'account_registration[behaviour]' => 'incoming_pattern',
      'account_registration[incoming_pattern_options][incoming_message]' => 'Email [email][password]',
    ];
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains('There must be a separator between placeholders.');
  }

  /**
   * Test form state when no phone number settings exist for user entity type.
   *
   * Tests notice is displayed and some form elements are disabled.
   */
  public function testFormNoUserPhoneNumberSettings(): void {
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->assertSession()->responseContains('There are no phone number settings configured for the user entity type. Some features cannot operate without these settings. <a href="' . Url::fromRoute('entity.phone_number_settings.add')->toString() . '">Add phone number settings</a>.', 'Warning message displayed for no phone number settings.');

    $input = $this->xpath('//input[@name="account_registration[behaviour]" and @disabled="disabled" and @value="all"]');
    static::assertTrue(count($input) === 1, "The 'All unrecognised phone numbers' radio is disabled.");

    $input = $this->xpath('//input[@name="account_registration[behaviour]" and @disabled="disabled" and @value="incoming_pattern"]');
    static::assertTrue(count($input) === 1, "The 'incoming_pattern' radio is disabled.");
  }

  /**
   * Test form state when phone number settings exist for user entity type.
   *
   * Tests notice is not displayed and form elements are not disabled.
   */
  public function testFormUserPhoneNumberSettings(): void {
    $this->createPhoneNumberSettings('user', 'user');
    $this->drupalGet(Url::fromRoute('sms_user.options'));
    $this->assertSession()->responseNotContains('There are no phone number settings configured for the user entity type. Some features cannot operate without these settings.', 'Warning message displayed for no phone number settings.');

    $input = $this->xpath('//input[@name="account_registration[behaviour]" and @disabled="disabled" and @value="all"]');
    static::assertTrue(count($input) === 0, "The 'All unrecognised phone numbers' radio is not disabled.");

    $input = $this->xpath('//input[@name="account_registration[behaviour]" and @disabled="disabled" and @value="incoming_pattern"]');
    static::assertTrue(count($input) === 0, "The 'incoming_pattern' radio is not disabled.");
  }

}
