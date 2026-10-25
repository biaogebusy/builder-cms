<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\Core\State\StateInterface;
use Drupal\xinshi_ai_usage\Service\CurrencyDisplay;
use GuzzleHttp\ClientInterface;
use Psr\Log\NullLogger;

/** Supplies reference metadata without networking or a site database. */
trait CurrencyDisplayTestTrait {

  private function currencyDisplay(string $currency = 'CNY', array $snapshot = []): CurrencyDisplay {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('currency')->willReturn($currency);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with(CurrencyDisplay::CONFIG)->willReturn($config);
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturn($snapshot);
    $http = $this->createMock(ClientInterface::class);
    $http->expects($this->never())->method('request');
    return new CurrencyDisplay($factory, $state, $http,
      $this->createMock(TimeInterface::class), new NullLockBackend(), new NullLogger());
  }

}
