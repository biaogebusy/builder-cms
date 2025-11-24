<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\private_message\Traits\PrivateMessageTestTrait;
use Drupal\private_message\Entity\PrivateMessageBan;

/**
 * Tests for the uninstallation process.
 *
 * @group private_message
 */
class PrivateMessageUninstallTest extends BrowserTestBase {

  use PrivateMessageTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['private_message'];

  /**
   * Tests the access to uninstall routes.
   */
  public function testLimitedAccess(): void {
    $this->drupalGet('/admin/config/private-message/uninstall');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/config/private-message/uninstall/confirm');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests deletion of all private message content from the system.
   */
  public function testUninstallation(): void {
    $this->createTestingUsers();
    $adminUser = $this->DrupalCreateUser([
      'administer site configuration',
      'administer private message module',
    ]);

    $this->createThreadWithMessages(
      [$this->users['a'], $adminUser],
      $this->users['a']
    );

    $this->createThreadWithMessages(
      [$this->users['b'], $adminUser],
      $this->users['b']
    );

    PrivateMessageBan::create([
      'owner' => $this->users['a'],
      'target' => $this->users['b'],
    ]
    )->save();

    $this->drupalLogin($adminUser);
    $this->drupalGet('/admin/config/private-message/uninstall');

    $this->getSession()
      ->getPage()
      ->clickLink('Delete all private message content');
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->clickLink('Cancel');

    $this->assertSame(5, $this->retrieveCount());

    $this->getSession()
      ->getPage()
      ->clickLink('Delete all private message content');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], t('Confirm'));

    $this->assertSame(0, $this->retrieveCount());
  }

  /**
   * Returns a count of entities.
   *
   * @return int
   *   Number of entities.
   */
  protected function retrieveCount(): int {
    $entityTypeManager = $this->container->get('entity_type.manager');

    $messages = $entityTypeManager
      ->getStorage('private_message')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    $threads = $entityTypeManager
      ->getStorage('private_message_thread')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    $bans = $entityTypeManager
      ->getStorage('private_message_ban')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    return $messages + $threads + $bans;
  }

}
