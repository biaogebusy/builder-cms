<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\Core\State\StateInterface;
use Drupal\xinshi_ai_usage\Service\CurrencyDisplay;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;

/** Exercises reference data, exact arithmetic and last-known-good recovery without a site. */
final class CurrencyDisplayTest extends TestCase {

  use CurrencyDisplayTestTrait;

  private const XML = '<Envelope xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">'
    . '<Cube><Cube time="2026-10-23"><Cube currency="USD" rate="1.2"/>'
    . '<Cube currency="CNY" rate="8.4"/></Cube></Cube></Envelope>';

  public function testSnapshotDefaultsToCnyWithoutNetworkOrInventedRates(): void {
    $snapshot = $this->currencyDisplay()->snapshot();
    $this->assertSame('CNY', $snapshot['currency']);
    $this->assertNull($snapshot['rate_date']);
    $this->assertSame(['EUR' => '1'], $snapshot['rates']);
    $this->assertNull(CurrencyDisplay::convertMicros('1000000', 'USD', $snapshot));
    $this->assertSame('1000000', CurrencyDisplay::convertMicros('1000000', 'CNY', $snapshot));
  }

  public function testDecimalConversionKeepsLargeAmountsAndRoundsHalfUp(): void {
    $snapshot = CurrencyDisplay::parse(self::XML, '2026-10-25') + ['currency' => 'CNY'];
    $this->assertSame('6305039478318695186415',
      CurrencyDisplay::convertMicros('900719925474099312345', 'USD', $snapshot));
    $this->assertSame('0', CurrencyDisplay::convertMicros('0', 'USD', $snapshot));
    $snapshot['currency'] = 'USD';
    $this->assertSame('142857', CurrencyDisplay::convertMicros('1000000', 'CNY', $snapshot));
    $snapshot['rates'] = ['EUR' => '1', 'USD' => '0.5'];
    $this->assertSame('1', CurrencyDisplay::convertMicros('1', 'EUR', $snapshot));
  }

  #[DataProvider('invalidDocuments')]
  public function testInvalidDocumentsCannotReplaceTheSnapshot(string $xml): void {
    $this->expectException(\UnexpectedValueException::class);
    CurrencyDisplay::parse($xml, '2026-10-25');
  }

  public static function invalidDocuments(): array {
    return [
      ['<invalid>'],
      [str_replace('2026-10-23', '2026-10-26', self::XML)],
      [str_replace('2026-10-23', '2026-02-30', self::XML)],
      [str_replace('rate="1.2"', 'rate="0"', self::XML)],
      [str_replace('rate="1.2"', 'rate="1e3"', self::XML)],
      [str_replace('rate="1.2"', 'rate="1.1234567"', self::XML)],
      [str_replace('currency="CNY"', 'currency="USD"', self::XML)],
      ['<!DOCTYPE Envelope [<!ENTITY value SYSTEM "file:///etc/passwd">]>' . self::XML],
      [str_repeat(' ', 65537) . self::XML],
      [str_replace('<Cube currency="CNY" rate="8.4"/>', '', self::XML)],
    ];
  }

  public function testRefreshIsBoundedAndRetainsTheLastGoodSnapshotAfterFailure(): void {
    $saved = [];
    $now = strtotime('2026-10-25T12:00:00Z');
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturn(NULL);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($config);
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnCallback(static function () use (&$saved) { return $saved; });
    $state->method('set')->willReturnCallback(static function ($key, $value) use (&$saved) { $saved = $value; });
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturnCallback(static function () use (&$now) { return $now; });
    $handler = new MockHandler([
      new Response(200, [], self::XML),
      new Response(503),
      new Response(200, [], str_replace('2026-10-23', '2026-10-22', self::XML)),
      new Response(200, [], self::XML),
    ]);
    $service = new CurrencyDisplay($factory, $state, new Client(['handler' => $handler]),
      $time, new NullLockBackend(), new NullLogger());
    $this->assertTrue($service->refresh());
    $this->assertSame('CNY', $service->snapshot()['currency']);
    $good = $service->snapshot();
    $this->assertSame(3, $handler->count());
    $this->assertTrue($service->refresh());
    $this->assertSame(3, $handler->count(), 'A second cron must use its cooldown.');
    $now += 21600;
    $this->assertFalse($service->refresh());
    $this->assertTrue($service->snapshot()['stale']);
    $this->assertSame($good['rates'], $service->snapshot()['rates']);
    $this->assertSame($good['fetched_at'], $service->snapshot()['fetched_at']);
    $this->assertFalse($service->refresh());
    $this->assertSame(2, $handler->count());
    $now += 3600;
    $this->assertFalse($service->refresh(), 'Older observations must not overwrite newer ones.');
    $this->assertSame('2026-10-23', $service->snapshot()['rate_date']);
    $this->assertTrue($service->refresh(TRUE));
    $this->assertFalse($service->snapshot()['stale']);
    $this->assertSame(CurrencyDisplay::SOURCE, (string) $handler->getLastRequest()->getUri());
    $this->assertFalse($handler->getLastOptions()['allow_redirects']);
    $this->assertSame(15, $handler->getLastOptions()['timeout']);
  }

  public function testSettingsUseTheExistingPricingPermissionAndHaveInstallDefaults(): void {
    $module = dirname(__DIR__, 3);
    $route = Yaml::parseFile($module . '/xinshi_ai_usage.routing.yml')['xinshi_ai_usage.currency_display'];
    $this->assertSame('administer xinshi ai pricing', $route['requirements']['_permission']);
    $this->assertTrue($route['options']['_admin_route']);
    $this->assertSame(['currency' => 'CNY'],
      Yaml::parseFile($module . '/config/install/xinshi_ai_usage.currency.yml'));
  }

}
