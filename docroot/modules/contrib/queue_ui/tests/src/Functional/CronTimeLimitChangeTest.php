<?php

namespace Drupal\Tests\queue_ui\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Class CronTimeLimitChangeTest declaration.
 *
 * @package Drupal\Tests\queue_ui\Functional
 * @group queue_ui
 */
class CronTimeLimitChangeTest extends BrowserTestBase {

  /**
   * The theme to install as the default for testing.
   *
   * Defaults to the install profile's default theme, if it specifies any.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * The test runner will merge the $modules lists from this class, the class
   * it extends, and so on up the class hierarchy. It is not necessary to
   * include modules in your list that a parent class has already declared.
   *
   * @var string[]
   *
   * @see \Drupal\Tests\BrowserTestBase::installDrupal()
   */
  protected static $modules = ['queue_ui_cron_test_fixtures'];

  /**
   * Test set negative value.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSetCronNegativeValue() {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $overview_form_url = Url::fromRoute('queue_ui.overview_form');
    $cron_form_url = Url::fromRoute('queue_ui.cron_form', ['queue' => 'queue_ui_cron_test']);
    $session = $this->assertSession();
    $this->drupalGet($overview_form_url);

    $session->elementTextContains('css', '[data-queue-name="queue_ui_cron_test"] > td:nth-child(6)', '59 seconds');
    $session->linkByHrefExists($cron_form_url->toString());
    $this->clickLink('Cron settings');

    $session->fieldValueEquals('cron', '59');

    $this->submitForm(['cron' => '-1'], 'Save');

    $session->statusMessageContains('Cron value cannot be less than 0', 'error');

    $this->drupalGet($overview_form_url);
    $session->elementTextContains('css', '[data-queue-name="queue_ui_cron_test"] > td:nth-child(6)', '59 seconds');
  }

  /**
   * Test set empty value.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSetCronEmptyValue() {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $overview_form_url = Url::fromRoute('queue_ui.overview_form');
    $cron_form_url = Url::fromRoute('queue_ui.cron_form', ['queue' => 'queue_ui_cron_test']);
    $session = $this->assertSession();
    $this->drupalGet($overview_form_url);

    $session->elementTextContains('css', '[data-queue-name="queue_ui_cron_test"] > td:nth-child(6)', '59 seconds');
    $session->linkByHrefExists($cron_form_url->toString());
    $this->clickLink('Cron settings');

    $session->fieldValueEquals('cron', '59');

    $this->submitForm(['cron' => ''], 'Save');

    $session->fieldValueEquals('cron', '');

    $session->statusMessageContains('Cron Time Limit updated for: Cron Test Queue [queue_ui_cron_test]', 'status');

    $this->drupalGet($overview_form_url);
    $session->elementTextContains('css', '[data-queue-name="queue_ui_cron_test"] > td:nth-child(6)', 'Cron Disabled');
  }

  /**
   * Test set 0 value.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSetCronZeroValue() {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $overview_form_url = Url::fromRoute('queue_ui.overview_form');
    $cron_form_url = Url::fromRoute('queue_ui.cron_form', ['queue' => 'queue_ui_cron_test']);
    $session = $this->assertSession();
    $this->drupalGet($overview_form_url);

    $session->elementTextContains('css', '[data-queue-name="queue_ui_cron_test"] > td:nth-child(6)', '59 seconds');
    $session->linkByHrefExists($cron_form_url->toString());
    $this->clickLink('Cron settings');

    $session->fieldValueEquals('cron', '59');

    $this->submitForm(['cron' => '0'], 'Save');

    $session->fieldValueEquals('cron', '0');

    $session->statusMessageContains('Cron Time Limit updated for: Cron Test Queue [queue_ui_cron_test]', 'status');

    $this->drupalGet($overview_form_url);
    $session->elementTextContains('css', '[data-queue-name="queue_ui_cron_test"] > td:nth-child(6)', '0 seconds');
  }

  /**
   * Test set 1 value.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSetCronOneValue() {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $overview_form_url = Url::fromRoute('queue_ui.overview_form');
    $cron_form_url = Url::fromRoute('queue_ui.cron_form', ['queue' => 'queue_ui_cron_test']);
    $session = $this->assertSession();
    $this->drupalGet($overview_form_url);

    $session->elementTextContains('css', '[data-queue-name="queue_ui_cron_test"] > td:nth-child(6)', '59 seconds');
    $session->linkByHrefExists($cron_form_url->toString());
    $this->clickLink('Cron settings');

    $session->fieldValueEquals('cron', '59');

    $this->submitForm(['cron' => '1'], 'Save');

    $session->fieldValueEquals('cron', '1');

    $session->statusMessageContains('Cron Time Limit updated for: Cron Test Queue [queue_ui_cron_test]', 'status');

    $this->drupalGet($overview_form_url);
    $session->elementTextContains('css', '[data-queue-name="queue_ui_cron_test"] > td:nth-child(6)', '1 second');
  }

  /**
   * Test set positive value.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSetCronPositiveValue() {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $overview_form_url = Url::fromRoute('queue_ui.overview_form');
    $cron_form_url = Url::fromRoute('queue_ui.cron_form', ['queue' => 'queue_ui_cron_test']);
    $session = $this->assertSession();
    $this->drupalGet($overview_form_url);

    $session->elementTextContains('css', '[data-queue-name="queue_ui_cron_test"] > td:nth-child(6)', '59 seconds');
    $session->linkByHrefExists($cron_form_url->toString());
    $this->clickLink('Cron settings');

    $session->fieldValueEquals('cron', '59');

    $this->submitForm(['cron' => '242'], 'Save');

    $session->fieldValueEquals('cron', '242');

    $session->statusMessageContains('Cron Time Limit updated for: Cron Test Queue [queue_ui_cron_test]', 'status');

    $this->drupalGet($overview_form_url);
    $session->elementTextContains('css', '[data-queue-name="queue_ui_cron_test"] > td:nth-child(6)', '242 seconds');
  }

  /**
   * Test set string value.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSetCronStringValue() {
    $this->drupalLogin($this->createUser(['admin queue_ui']));
    $overview_form_url = Url::fromRoute('queue_ui.overview_form');
    $cron_form_url = Url::fromRoute('queue_ui.cron_form', ['queue' => 'queue_ui_cron_test']);
    $session = $this->assertSession();
    $this->drupalGet($overview_form_url);

    $session->elementTextContains('css', '[data-queue-name="queue_ui_cron_test"] > td:nth-child(6)', '59 seconds');
    $session->linkByHrefExists($cron_form_url->toString());
    $this->clickLink('Cron settings');

    $session->fieldValueEquals('cron', '59');

    $this->submitForm(['cron' => 'test'], 'Save');

    $session->fieldValueEquals('cron', 'test');

    $session->statusMessageContains('Cron Time Limit must be a number', 'error');

    $this->drupalGet($overview_form_url);
    $session->elementTextContains('css', '[data-queue-name="queue_ui_cron_test"] > td:nth-child(6)', '59 seconds');
  }

}
