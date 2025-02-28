<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Functional;

use Drupal\Core\Url;

/**
 * Tests SMS Framework settings form.
 *
 * @group SMS Framework
 */
final class SmsFrameworkSettingsTest extends SmsFrameworkBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $account = $this->drupalCreateUser([
      'administer smsframework',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Test setting form without gateway.
   */
  public function testSettingsForm(): void {
    $edit['fallback_gateway'] = '';
    $edit['pages[verify]'] = '/' . $this->randomMachineName();
    $this->drupalGet(Url::fromRoute('sms.settings'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains(t('SMS settings saved.'));
  }

  /**
   * Test setting gateway.
   */
  public function testGatewaySet(): void {
    $gateway = $this->createMemoryGateway();
    $edit['fallback_gateway'] = $gateway->id();
    $edit['pages[verify]'] = '/' . $this->randomMachineName();
    $this->drupalGet(Url::fromRoute('sms.settings'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains(t('SMS settings saved.'));
  }

  /**
   * Test changing verification path.
   */
  public function testVerificationPathInvalid(): void {
    // Test invalid path.
    $edit['pages[verify]'] = $this->randomMachineName() . '/' . $this->randomMachineName();
    $this->drupalGet(Url::fromRoute('sms.settings'));
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->responseContains(t("Path must begin with a '/' character."));
  }

}
