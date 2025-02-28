<?php

declare(strict_types = 1);

namespace Drupal\sms_user;

use Drupal\user\UserInterface;
use Drupal\sms\Entity\SmsMessageInterface;

/**
 * Defines interface for the user active hours service.
 */
interface ActiveHoursInterface {

  /**
   * Determine if the current time of a user is within permitted hour ranges.
   *
   * @param \Drupal\user\UserInterface $user
   *   A user entity.
   * @param string $now
   *   The local time for the user. Defaults to current time.
   *
   * @return bool
   *   Whether the current time for a user is within active hours.
   */
  public function inHours(UserInterface $user, $now = 'now');

  /**
   * Determine the next valid active hours date range for a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   A user entity.
   * @param string $now
   *   A time or strtotime() relative string localised to the users timezone.
   *   Defaults to current time for the user.
   *
   * @return \Drupal\sms_user\ActiveHoursDates|false
   *   A date pair, or FALSE if no next date could be determined.
   */
  public function findNextTime(UserInterface $user, $now = 'now');

  /**
   * Delay a SMS message if active hours require it to be delayed.
   *
   * @param \Drupal\sms\Entity\SmsMessageInterface $sms_message
   *   An SMS message entity.
   */
  public function delaySmsMessage(SmsMessageInterface &$sms_message);

  /**
   * Get ranges converted to local timezone and sorted chronologically.
   *
   * @param string|\DateTimeZone $timezone
   *   A timezone string or object.
   *
   * @return \Drupal\sms_user\ActiveHoursDates[]
   *   A array of date pairs sorted chronologically by start dates.
   */
  public function getRanges($timezone);

}
