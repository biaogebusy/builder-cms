<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Functional;

use Drupal\sms\Direction;
use Drupal\sms\Entity\SmsDeliveryReport;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Entity\SmsMessageResult;
use Drupal\sms\Entity\SmsMessageResultInterface;
use Drupal\sms\Message\SmsMessageReportStatus;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Update path test coverage for sms module.
 *
 * @group SMS Framework
 */
final class SmsFrameworkUpdateTest extends UpdatePathTestBase {

  use SmsFrameworkTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'telephone',
    'dynamic_entity_reference',
    'sms',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../../core/modules/system/tests/fixtures/update/drupal-8.8.0.bare.standard.php.gz',
      __DIR__ . '/../../../tests/fixtures/update/sms-8.x-1.x-result-field-2836157.php.gz',
    ];
  }

  /**
   * Tests sms_update_8101.
   */
  public function testSmsUpdate8101(): void {
    $db_schema = \Drupal::database()->schema();
    // Check that the sms tables exist but the others don't.
    $this->assertTrue($db_schema->tableExists('sms'));
    $this->assertTrue($db_schema->tableExists('sms__recipient_phone_number'));
    $this->assertTrue($db_schema->tableExists('sms_phone_number_verification'));
    $this->assertFalse($db_schema->tableExists('sms_result'));
    $this->assertFalse($db_schema->tableExists('sms_report'));
    $this->assertFalse($db_schema->tableExists('sms_report_revision'));

    $this->runUpdates();

    // Check that all the sms entity tables exist.
    $this->assertTrue($db_schema->tableExists('sms'));
    $this->assertTrue($db_schema->tableExists('sms__recipient_phone_number'));
    $this->assertTrue($db_schema->tableExists('sms_phone_number_verification'));
    $this->assertTrue($db_schema->tableExists('sms_result'));
    $this->assertTrue($db_schema->tableExists('sms_report'));
    $this->assertTrue($db_schema->tableExists('sms_report_revision'));

    // Confirm that the existing SMS message was not clobbered.
    /** @var \Drupal\sms\Entity\SmsMessageInterface[] $sms_messages */
    $sms_messages = SmsMessage::loadMultiple();
    $this->assertCount(1, $sms_messages);
    $this->assertCount(2, $sms_messages[1]->getRecipients());
    $this->assertNull($sms_messages[1]->getResult());

    // Create new SMS with delivery report and save it.
    $sms_message = SmsMessage::create()
      ->addRecipients($this->randomPhoneNumbers())
      ->setSender($this->randomMachineName())
      ->setDirection(Direction::OUTGOING)
      ->setAutomated(TRUE);
    $reports = array_map(function ($recipient) {
      return SmsDeliveryReport::create()
        ->setMessageId($this->randomString())
        ->setRecipient($recipient)
        ->setStatus(SmsMessageReportStatus::DELIVERED)
        ->setStatusMessage($this->randomString());
    }, $sms_message->getRecipients());
    $result = SmsMessageResult::create()
      ->setReports($reports);
    $sms_message
      ->setResult($result)
      ->save();
    \Drupal::entityTypeManager()->getStorage('sms')->resetCache();

    $sms_messages = SmsMessage::loadMultiple();
    $this->assertCount(2, $sms_messages);
    $this->assertTrue($sms_messages[2]->getResult() instanceof SmsMessageResultInterface);
    $this->assertCount(count($sms_message->getRecipients()), $sms_messages[2]->getResult()->getReports());
  }

}
