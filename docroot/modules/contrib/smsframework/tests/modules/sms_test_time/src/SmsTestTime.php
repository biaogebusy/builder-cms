<?php

declare(strict_types = 1);

namespace Drupal\sms_test_time;

use Drupal\Component\Datetime\TimeInterface;

/**
 * Time service with a predictable time.
 */
final class SmsTestTime implements TimeInterface {

  /**
   * Date for testing.
   *
   * @var \DateTimeInterface
   */
  protected \DateTimeInterface $date;

  /**
   * SmsTestTime constructor.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $original
   *   Original time service.
   */
  public function __construct(protected TimeInterface $original) {
    $this->date = new \DateTimeImmutable('2:30pm 17 October 1997', new \DateTimeZone('UTC'));
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestTime(): int {
    return (int) $this->date->getTimestamp();
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestMicroTime(): float {
    return (float) $this->date->format('U.u');
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentTime(): int {
    return (int) $this->date->getTimestamp();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentMicroTime(): float {
    return (float) $this->date->format('U.u');
  }

}
