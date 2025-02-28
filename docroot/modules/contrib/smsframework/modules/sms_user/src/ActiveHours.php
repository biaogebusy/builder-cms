<?php

declare(strict_types = 1);

namespace Drupal\sms_user;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\sms\Entity\SmsMessageInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Defines the user active hours service.
 */
class ActiveHours implements ActiveHoursInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Whether active hours is enabled in configuration.
   *
   * @var bool|null
   *   Whether active hours is enabled in configuration or NULL if configuration
   *   has not been built yet.
   */
  protected $status = NULL;

  /**
   * Date ranges as they exist in configuration.
   *
   * @var array
   *   An unsorted array containing arrays with keys 'start' and 'end' with
   *   values in strtotime() format.
   */
  protected $ranges = [];

  /**
   * Constructs a ActiveHours object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function inHours(UserInterface $user, $now = 'now') {
    $this->build();

    // We're in hours if active hours feature is disabled.
    if (!$this->status) {
      return TRUE;
    }

    $timezone = $user->getTimeZone();
    $now = new DrupalDateTime($now, $timezone);
    foreach ($this->getRanges($timezone) as $date) {
      if ($now >= $date->getStartDate() && $now <= $date->getEndDate()) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function findNextTime(UserInterface $user, $now = 'now') {
    $timezone = $user->getTimeZone();
    $now = new DrupalDateTime($now, $timezone);
    foreach ($this->getRanges($timezone) as $date) {
      // The end date may have already passed.
      if ($now > $date->getEndDate()) {
        continue;
      }
      return $date;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function delaySmsMessage(SmsMessageInterface &$sms_message) {
    $recipient = $sms_message->getRecipientEntity();
    if ($sms_message->isAutomated() && $recipient instanceof UserInterface) {
      if (!$this->inHours($recipient) && ($range = $this->findNextTime($recipient))) {
        $sms_message->setSendTime($range->getStartDate()->format('U'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRanges($timezone) {
    $this->build();

    $dates = [];
    foreach ($this->ranges as $range) {
      $dates[] = new ActiveHoursDates(
        new DrupalDateTime($range['start'], $timezone),
        new DrupalDateTime($range['end'], $timezone)
      );
    }

    // Sort so nearest date is closest.
    // Can't do this in build() since computed relative dates can be different
    // per timezone.
    usort($dates, function ($a, $b) {
      if ($a->getStartDate() == $b->getStartDate()) {
        return 0;
      }
      return $a->getStartDate() < $b->getStartDate() ? -1 : 1;
    });

    return $dates;
  }

  /**
   * Store the active hours configuration state.
   */
  protected function build() {
    if (isset($this->status)) {
      return;
    }

    $settings = $this->configFactory
      ->get('sms_user.settings')
      ->get('active_hours');

    $this->status = !empty($settings['status']);
    $this->ranges = !empty($settings['ranges']) ? $settings['ranges'] : [];
  }

}
