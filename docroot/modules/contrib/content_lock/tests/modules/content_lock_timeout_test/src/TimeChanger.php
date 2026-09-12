<?php

declare(strict_types=1);

namespace Drupal\content_lock_timeout_test;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * This is a datetime.time service for testing only.
 *
 * @package Drupal\Tests\content_lock_timeout\Functional
 */
class TimeChanger implements TimeInterface {

  /**
   * The key value storage.
   */
  protected KeyValueStoreInterface $keyValue;

  public function __construct(
    protected TimeInterface $time,
    #[Autowire(service: 'keyvalue')]
    KeyValueFactoryInterface $keyValueFactory,
  ) {
    $this->keyValue = $keyValueFactory->get(TimeChanger::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentTime() {
    return $this->time->getCurrentTime() + $this->keyValue->get('time', 0);
  }

  /**
   * Change current time by the given amount.
   *
   * @param int $seconds
   *   The number of seconds to change the time by..
   */
  public function setTimePatch(int $seconds): void {
    if ($seconds === 0) {
      $this->keyValue->delete('time');
      return;
    }
    $this->keyValue->set('time', $seconds);
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestTime(): int {
    return $this->time->getRequestTime() + $this->keyValue->get('time', 0);
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestMicroTime(): float {
    return $this->time->getRequestMicroTime() + $this->keyValue->get('time', 0);
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentMicroTime(): float {
    return $this->time->getRequestMicroTime() + $this->keyValue->get('time', 0);
  }

}
