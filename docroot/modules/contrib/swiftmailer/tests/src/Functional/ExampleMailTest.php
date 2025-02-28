<?php

namespace Drupal\Tests\swiftmailer\Functional;

use Drupal\Core\Url;

/**
 * @group swiftmailer
 */
class ExampleMailTest extends SwiftMailerTestBase {

  /**
   * Tests the e-mail test form.
   */
  public function testForm() {
    $account = $this->createUser(['administer swiftmailer']);
    $this->drupalLogin($account);
    $this->drupalGet(Url::fromRoute('swiftmailer.test'));
    $this->submitForm([], 'Send');
    $this->assertSession()->pageTextContains(t('An attempt has been made to send an e-mail to @email.', ['@email' => $account->getEmail()]));
    $this->assertBodyContains('The module has been successfully configured.');
  }

}
