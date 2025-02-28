<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms_user\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\sms\Entity\SmsMessage;
use Drupal\Tests\sms\Kernel\SmsFrameworkKernelBase;
use Drupal\user\Entity\User;
use Drupal\sms\Direction;

/**
 * Tests active hours service.
 *
 * Using absolute dates to prevent random test failures.
 *
 * @group SMS Framework
 * @coversDefaultClass \Drupal\sms_user\ActiveHours
 */
class SmsFrameworkUserActiveHoursServiceTest extends SmsFrameworkKernelBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sms',
    'sms_user',
    'user',
    'telephone',
    'dynamic_entity_reference',
  ];

  /**
   * The active hours service.
   *
   * @var \Drupal\sms_user\ActiveHoursInterface
   */
  protected $activeHoursService;

  /**
   * The SMS provider.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  protected $smsProvider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->activeHoursService = $this->container->get('sms_user.active_hours');
    $this->smsProvider = $this->container->get('sms.provider');
    $this->installEntitySchema('user');
    $this->installEntitySchema('sms');
  }

  /**
   * Test if in hours if active hours is disabled and out of active hours.
   */
  public function testInHoursActiveHoursOff(): void {
    $this->activeHoursStatus(FALSE);
    $this->setActiveHours([['start' => '2016-03-13 sunday 9:00', 'end' => '2016-03-13 sunday 17:00']]);
    $user = $this->createUser(['timezone' => 'America/New_York']);

    $now = '2016-03-13 Sunday 10pm America/New_York';
    $this->assertTrue($this->activeHoursService->inHours($user, $now));
  }

  /**
   * Test if in hours with different timezone.
   */
  public function testInHoursDifferentTimezone(): void {
    $this->activeHoursStatus(TRUE);
    $this->setActiveHours([['start' => '2016-03-13 sunday 9:00', 'end' => '2016-03-13 sunday 17:00']]);
    $user = $this->createUser(['timezone' => 'America/New_York']);

    $now = '2016-03-13 Sunday 4pm America/Los_Angeles';
    $this->assertFalse($this->activeHoursService->inHours($user, $now));
  }

  /**
   * Test if in hours with same timezone.
   */
  public function testInHoursSameTimezone(): void {
    $this->activeHoursStatus(TRUE);
    $this->setActiveHours([['start' => '2016-03-13 sunday 9:00', 'end' => '2016-03-13 sunday 17:00']]);
    $user = $this->createUser(['timezone' => 'America/New_York']);

    $now = '2016-03-13 Sunday 4pm America/New_York';
    $this->assertTrue($this->activeHoursService->inHours($user, $now));
  }

  /**
   * Test if in hours with day not in active hours.
   */
  public function testInHoursDifferentDay(): void {
    $this->activeHoursStatus(TRUE);
    $this->setActiveHours([['start' => '2016-03-14 monday 9:00', 'end' => '2016-03-14 monday 17:00']]);
    $user = $this->createUser(['timezone' => 'America/New_York']);

    $now = '2016-03-13 Sunday 12pm America/New_York';
    $this->assertFalse($this->activeHoursService->inHours($user, $now));
  }

  /**
   * Test if in hours for 24 hours with same timezone.
   */
  public function testInHoursAllDay(): void {
    $this->activeHoursStatus(TRUE);
    $this->setActiveHours([['start' => '2016-03-16 wednesday', 'end' => '2016-03-16 wednesday +1 day']]);
    $user = $this->createUser(['timezone' => 'America/New_York']);

    $now = '2016-03-15 Tuesday 8pm America/New_York';
    $this->assertFalse($this->activeHoursService->inHours($user, $now));
    $now = '2016-03-15 Tuesday 11:59:59pm America/New_York';
    $this->assertFalse($this->activeHoursService->inHours($user, $now));
    $now = '2016-03-16 Wednesday 12am America/New_York';
    $this->assertTrue($this->activeHoursService->inHours($user, $now));
    $now = '2016-03-16 Wednesday 12pm America/New_York';
    $this->assertTrue($this->activeHoursService->inHours($user, $now));
    $now = '2016-03-16 Wednesday 11:59pm America/New_York';
    $this->assertTrue($this->activeHoursService->inHours($user, $now));
    $now = '2016-03-17 Thursday 12am America/New_York';
    $this->assertTrue($this->activeHoursService->inHours($user, $now));
    $now = '2016-03-17 Thursday 12:01:01am America/New_York';
    $this->assertFalse($this->activeHoursService->inHours($user, $now));
  }

  /**
   * Test if in hours for 24 hours with different timezone.
   */
  public function testInHoursAllDayDifferentTimezone(): void {
    $this->activeHoursStatus(TRUE);
    $this->setActiveHours([['start' => '2016-03-16 wednesday', 'end' => '2016-03-16 wednesday +1 day']]);
    $user = $this->createUser(['timezone' => 'America/New_York']);

    $now = '2016-03-15 Tuesday 8pm America/Los_Angeles';
    $this->assertFalse($this->activeHoursService->inHours($user, $now));
    $now = '2016-03-15 Tuesday 11:59:59pm America/Los_Angeles';
    $this->assertTrue($this->activeHoursService->inHours($user, $now));
    $now = '2016-03-16 Wednesday 12am America/Los_Angeles';
    $this->assertTrue($this->activeHoursService->inHours($user, $now));
    $now = '2016-03-16 Wednesday 12pm America/Los_Angeles';
    $this->assertTrue($this->activeHoursService->inHours($user, $now));
    $now = '2016-03-16 Wednesday 11:59pm America/Los_Angeles';
    $this->assertFalse($this->activeHoursService->inHours($user, $now));
    $now = '2016-03-17 Thursday 12am America/Los_Angeles';
    $this->assertFalse($this->activeHoursService->inHours($user, $now));
    $now = '2016-03-17 Thursday 12:01:01am America/Los_Angeles';
    $this->assertFalse($this->activeHoursService->inHours($user, $now));
  }

  /**
   * Test no next time when no ranges are set.
   */
  public function testFindNextTimeNoRanges(): void {
    $user = $this->createUser();
    $this->assertFalse($this->activeHoursService->findNextTime($user));
  }

  /**
   * Test getting the range for today when within todays range.
   */
  public function testFindNextTimeSameDay(): void {
    $this->setActiveHours([
      ['start' => '2016-03-15 tuesday 9:00', 'end' => '2016-03-15 tuesday 17:00'],
      ['start' => '2016-03-16 wednesday 9:00', 'end' => '2016-03-16 wednesday 17:00'],
      ['start' => '2016-03-17 thursday 9:00', 'end' => '2016-03-17 thursday 17:00'],
    ]);
    $user = $this->createUser(['timezone' => 'America/New_York']);
    $now = '2016-03-16 Wednesday 1pm America/New_York';
    $range = $this->activeHoursService->findNextTime($user, $now);
    $this->assertEquals(new DrupalDateTime('2016-03-16 Wednesday 9am America/New_York'), $range->getStartDate());
    $this->assertEquals(new DrupalDateTime('2016-03-16 Wednesday 5pm America/New_York'), $range->getEndDate());
  }

  /**
   * Test getting a range for next day when out of hours when a range was today.
   */
  public function testFindNextTimeSameDayOutOfHours(): void {
    $this->setActiveHours([
      ['start' => '2016-03-15 tuesday 9:00', 'end' => '2016-03-15 tuesday 17:00'],
      ['start' => '2016-03-12 saturday 9:00', 'end' => '2016-03-12 saturday 17:00'],
      ['start' => '2016-03-13 sunday 9:00', 'end' => '2016-03-13 sunday 17:00'],
    ]);
    $user = $this->createUser(['timezone' => 'America/New_York']);
    $now = '2016-03-12 saturday 5:00:01pm America/New_York';
    $range = $this->activeHoursService->findNextTime($user, $now);
    $this->assertEquals(new DrupalDateTime('2016-03-13 Sunday 9am America/New_York'), $range->getStartDate());
    $this->assertEquals(new DrupalDateTime('2016-03-13 Sunday 5pm America/New_York'), $range->getEndDate());
  }

  /**
   * Tests getting date ranges.
   */
  public function testGetRanges(): void {
    $this->setActiveHours([
      ['start' => '2016-03-15 tuesday 9:00', 'end' => '2016-03-15 tuesday 17:00'],
      ['start' => '2016-03-16 wednesday 9:00', 'end' => '2016-03-16 wednesday 17:00'],
    ]);

    $ranges = $this->activeHoursService->getRanges('America/New_York');
    // Need to test timezone is same as well, as data objects will compare
    // equality across timezones.
    $this->assertEquals(new DrupalDateTime('2016-03-15 tuesday 9am America/New_York'), $ranges[0]->getStartDate());
    $this->assertEquals('America/New_York', $ranges[0]->getStartDate()->getTimezone()->getName());
    $this->assertEquals(new DrupalDateTime('2016-03-15 tuesday 5pm America/New_York'), $ranges[0]->getEndDate());
    $this->assertEquals('America/New_York', $ranges[0]->getEndDate()->getTimezone()->getName());
    $this->assertEquals(new DrupalDateTime('2016-03-16 wednesday 9am America/New_York'), $ranges[1]->getStartDate());
    $this->assertEquals('America/New_York', $ranges[1]->getStartDate()->getTimezone()->getName());
    $this->assertEquals(new DrupalDateTime('2016-03-16 wednesday 5pm America/New_York'), $ranges[1]->getEndDate());
    $this->assertEquals('America/New_York', $ranges[1]->getEndDate()->getTimezone()->getName());
  }

  /**
   * Tests delay was applied to a SMS message.
   *
   * Checks invokation of sms_user_entity_presave(). This happens when queue()
   * is called and the SMS message is saved.
   */
  public function testDelaySmsMessage(): void {
    $timestamp = (new DrupalDateTime('next tuesday 9:00'))->format('U');
    $this->activeHoursStatus(TRUE);
    $this->setActiveHours([
      ['start' => 'next tuesday 9:00', 'end' => 'next tuesday 17:00'],
    ]);

    $user = $this->createUser();
    $sms_message = SmsMessage::create()
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers())
      ->setDirection(Direction::OUTGOING)
      ->setRecipientEntity($user)
      ->setAutomated(TRUE);
    $return = $this->smsProvider->queue($sms_message);

    $this->assertEquals($timestamp, $return[0]->getSendTime());
  }

  /**
   * Tests delay was not applied to a SMS message if it is tagged as automated.
   */
  public function testDelaySmsMessageNotAutomated(): void {
    $timestamp = (new DrupalDateTime('next tuesday 9:00'))->format('U');
    $this->activeHoursStatus(TRUE);
    $this->setActiveHours([
      ['start' => 'next tuesday 9:00', 'end' => 'next tuesday 17:00'],
    ]);

    $user = $this->createUser();
    $sms_message = SmsMessage::create()
      ->addRecipients($this->randomPhoneNumbers(1))
      ->setMessage($this->randomString())
      ->setDirection(Direction::OUTGOING)
      ->setRecipientEntity($user)
      ->setAutomated(FALSE);
    $this->smsProvider->queue($sms_message);

    $this->assertNotEquals($timestamp, $sms_message->getSendTime());
  }

  /**
   * Helper to set status of active hours.
   */
  protected function activeHoursStatus($status) {
    \Drupal::configFactory()
      ->getEditable('sms_user.settings')
      ->set('active_hours.status', $status)
      ->save();
  }

  /**
   * Helper to set and replace existing active hours ranges.
   */
  protected function setActiveHours($ranges) {
    \Drupal::configFactory()
      ->getEditable('sms_user.settings')
      ->set('active_hours.ranges', $ranges)
      ->save();
  }

  /**
   * Creates a user.
   *
   * @param array $values
   *   Optional settings to add to user.
   *
   * @return \Drupal\user\UserInterface
   *   A saved user entity.
   */
  protected function createUser(array $values = []) {
    $user = User::create([
      'uid' => 1,
      'name' => $this->randomMachineName(),
    ] + $values);

    // Need to activate so when DER does entity validation it is included by the
    // UserSelection plugin.
    $user->activate();

    $user->save();
    return $user;
  }

}
