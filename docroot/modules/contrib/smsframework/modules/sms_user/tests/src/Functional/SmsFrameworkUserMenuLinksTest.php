<?php

declare(strict_types=1);

namespace Drupal\Tests\sms_user\Functional;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Url;
use Drupal\Tests\sms\Functional\SmsFrameworkBrowserTestBase;

/**
 * Tests dynamically created SMS User menu links.
 *
 * @group SMS User
 */
class SmsFrameworkUserMenuLinksTest extends SmsFrameworkBrowserTestBase {

  protected static $modules = ['sms_user'];

  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();
    $user = $this->drupalCreateUser([
      'access administration pages',
      'administer smsframework',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests dynamic menu links are found.
   */
  public function testDynamicMenuLinks(): void {
    $entityDisplayRepo = \Drupal::service('entity_display.repository');
    \assert($entityDisplayRepo instanceof EntityDisplayRepositoryInterface);
    $entityDisplayRepo->getFormDisplay('user', 'user', 'default')->save();
    $this->createPhoneNumberSettings('user', 'user');
    $this->drupalGet(Url::fromRoute('user.admin_index'));
    $this->assertSession()->linkExists('User phone number');
  }

  /**
   * Tests no dynamic menu links are found.
   */
  public function testNoDynamicMenuLinks(): void {
    $this->drupalGet(Url::fromRoute('user.admin_index'));
    $this->assertSession()->linkNotExists('User phone number');
  }

}
