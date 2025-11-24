<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Functional;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Url;
use Drupal\sms\Direction;
use Drupal\sms\Entity\SmsGateway;
use Drupal\sms\Plugin\SmsGateway\LogGateway;

/**
 * Tests gateway administration user interface.
 *
 * @group SMS Framework
 */
final class SmsFrameworkGatewayAdminTest extends SmsFrameworkBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block'];

  /**
   * SMS Gateway entity storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected ConfigEntityStorageInterface $smsGatewayStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->smsGatewayStorage = \Drupal::entityTypeManager()->getStorage('sms_gateway');
    $this->drupalPlaceBlock('page_title_block');
  }

  /**
   * Tests the Gateway list implementation.
   */
  public function testGatewayList(): void {
    $this->createMemoryGateway();

    // Test no access for anonymous.
    $this->drupalGet(Url::fromRoute('sms.gateway.list'));
    $this->assertSession()->statusCodeEquals(403);

    $account = $this->drupalCreateUser(['administer smsframework']);
    $this->drupalLogin($account);

    $this->drupalGet(Url::fromRoute('sms.gateway.list'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<td>Drupal log</td>');
    $this->assertSession()->responseContains('<td>Memory</td>');

    // Delete all gateways.
    $this->smsGatewayStorage->delete($this->smsGatewayStorage->loadMultiple());
    $this->drupalGet(Url::fromRoute('sms.gateway.list'));
    $this->assertSession()->responseContains(t('No gateways found.'));
  }

  /**
   * Tests setting up the fallback gateway.
   */
  public function testFallbackGateway(): void {
    $test_gateway = $this->createMemoryGateway(['skip_queue' => TRUE]);

    // Test initial fallback gateway.
    $sms_gateway_fallback = SmsGateway::load($this->config('sms.settings')->get('fallback_gateway'));

    static::assertEquals($sms_gateway_fallback->id(), LogGateway::PLUGIN_ID, 'Initial fallback gateway is "log".');

    $this->drupalLogin($this->drupalCreateUser(['administer smsframework']));

    // Change fallback gateway.
    $this->drupalGet(Url::fromRoute('sms.settings'));
    $this->submitForm([
      'fallback_gateway' => $test_gateway->id(),
    ], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);

    $sms_gateway_fallback = SmsGateway::load($this->config('sms.settings')->get('fallback_gateway'));
    static::assertEquals($sms_gateway_fallback->id(), $test_gateway->id(), 'Fallback gateway changed.');
  }

  /**
   * Test adding a gateway.
   */
  public function testGatewayAdd(): void {
    $account = $this->drupalCreateUser(['administer smsframework']);
    $this->drupalLogin($account);

    $this->drupalGet(Url::fromRoute('entity.sms_gateway.add'));
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      'label' => $this->randomString(),
      'id' => mb_strtolower($this->randomMachineName()),
      'status' => TRUE,
      'plugin_id' => 'memory',
    ];
    $this->drupalGet(Url::fromRoute('entity.sms_gateway.add'));
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->addressEquals(Url::fromRoute('entity.sms_gateway.edit_form', [
      'sms_gateway' => $edit['id'],
    ]));
    $this->assertSession()->responseContains(t('Gateway created.'));

    $this->drupalGet(Url::fromRoute('sms.gateway.list'));
    $this->assertSession()->responseContains('<td>' . t('@label', ['@label' => $edit['label']]) . '</td>', 'New gateway appears on list.');
  }

  /**
   * Tests configuring a gateway.
   *
   * Ensures gateway plugin custom configuration form is shown, and new
   * configuration is saved to the config entity.
   */
  public function testGatewayEdit(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer smsframework']));
    $test_gateway = $this->createMemoryGateway();

    $this->drupalGet(Url::fromRoute('entity.sms_gateway.edit_form', [
      'sms_gateway' => $test_gateway->id(),
    ]));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('widget');
    $this->assertSession()->checkboxNotChecked('edit-skip-queue');
    $this->assertSession()->fieldValueEquals('retention_duration_incoming', '0');
    $this->assertSession()->fieldValueEquals('retention_duration_outgoing', '0');

    // Memory gateway supports pushed reports, so the URL should display.
    $this->assertSession()->fieldValueEquals('delivery_reports[push_path]', $test_gateway->getPushReportPath());

    // Memory gateway has a decoy configuration form.
    $widget = $this->randomString();
    $this->submitForm([
      'widget' => $widget,
      'skip_queue' => '1',
      'retention_duration_incoming' => '3600',
      'retention_duration_outgoing' => '-1',
    ], 'Save');
    $this->assertSession()->addressEquals(Url::fromRoute('sms.gateway.list'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Gateway saved.');

    // Reload the gateway, check configuration saved to config entity.
    /** @var \Drupal\sms\Entity\SmsGatewayInterface $test_gateway */
    $test_gateway = $this->smsGatewayStorage
      ->load($test_gateway->id());

    // Gateway settings.
    static::assertEquals(TRUE, $test_gateway->getSkipQueue());
    static::assertEquals('3600', $test_gateway->getRetentionDuration(Direction::INCOMING));
    static::assertEquals('-1', $test_gateway->getRetentionDuration(Direction::OUTGOING));

    // Plugin form.
    $config = $test_gateway->getPlugin()
      ->getConfiguration();
    static::assertEquals($widget, $config['widget'], 'Plugin configuration changed.');
  }

  /**
   * Tests a gateway edit form does not display delivery report URL.
   */
  public function testGatewayEditNoDeliveryUrl(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer smsframework']));
    $test_gateway = $this->createMemoryGateway(['plugin' => 'capabilities_default']);

    $this->drupalGet(Url::fromRoute('entity.sms_gateway.edit_form', [
      'sms_gateway' => $test_gateway->id(),
    ]));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Edit gateway');

    $this->assertSession()->fieldNotExists('delivery_reports[push_path]');
  }

  /**
   * Tests deleting a gateway.
   */
  public function testGatewayDelete(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer smsframework']));
    $test_gateway = $this->createMemoryGateway();
    $this->drupalGet(Url::fromRoute('entity.sms_gateway.edit_form', [
      'sms_gateway' => $test_gateway->id(),
    ]));

    $this->clickLink(t('Delete'));
    $delete_url = Url::fromRoute('entity.sms_gateway.delete_form', [
      'sms_gateway' => $test_gateway->id(),
    ]);
    $this->assertSession()->addressEquals(sprintf('/admin/config/smsframework/gateways/%s/delete', $test_gateway->id()));
    $this->assertSession()->responseContains(t('Are you sure you want to delete SMS gateway %label?', [
      '%label' => $test_gateway->label(),
    ]));
    $this->drupalGet($delete_url);
    $this->submitForm([], 'Delete');

    $this->assertSession()->addressEquals(Url::fromRoute('sms.gateway.list'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains(t('Gateway %label was deleted.', [
      '%label' => $test_gateway->label(),
    ]));
    $this->assertSession()->responseNotContains('<td>' . t('@label', ['@label' => $test_gateway->label()]) . '</td>');
  }

  /**
   * Tests incoming specific features of gateway edit form.
   */
  public function testIncomingGatewayEdit(): void {
    $gateway = $this->createMemoryGateway(['plugin' => 'incoming']);
    $this->drupalLogin($this->drupalCreateUser(['administer smsframework']));

    $url = Url::fromRoute('entity.sms_gateway.edit_form')
      ->setRouteParameter('sms_gateway', $gateway->id());
    $this->drupalGet($url);

    // Memory gateway supports incoming messages, so the URL should display.
    $this->assertSession()
      ->fieldValueEquals('incoming_messages[push_path]', $gateway->getPushIncomingPath());

    $incoming_route = '/' . $this->randomMachineName();
    $this->submitForm([
      'incoming_messages[push_path]' => $incoming_route,
    ], 'Save');

    // Reload the gateway, check properties modified.
    $gateway = SmsGateway::load($gateway->id());
    static::assertEquals($incoming_route, $gateway->getPushIncomingPath());
  }

  /**
   * Tests a gateway edit form does not contain incoming path fields.
   */
  public function testNoIncomingFields(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer smsframework']));
    $gateway = $this->createMemoryGateway(['plugin' => 'capabilities_default']);

    $this->drupalGet(Url::fromRoute('entity.sms_gateway.edit_form', [
      'sms_gateway' => $gateway->id(),
    ]));

    $this->assertSession()->responseContains('Edit gateway');
    $this->assertSession()->fieldNotExists('incoming_messages[push_path]');
    $this->assertSession()->responseContains(t('This gateway does not support receiving messages.'));
  }

}
