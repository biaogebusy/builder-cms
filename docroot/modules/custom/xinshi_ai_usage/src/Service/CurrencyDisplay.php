<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Stores daily reference rates for display without changing saved valuations.
 */
final class CurrencyDisplay {

  public const CONFIG = 'xinshi_ai_usage.currency';
  public const STATE = 'xinshi_ai_usage.exchange_rates';
  public const SOURCE = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
  private const MAX_BYTES = 65536;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
    private readonly ClientInterface $http,
    private readonly TimeInterface $time,
    private readonly LockBackendInterface $lock,
    private readonly LoggerInterface $logger,
  ) {}

  /** Returns the site preference, including on sites not yet configured. */
  public function currency(): string {
    return $this->configFactory->get(self::CONFIG)->get('currency') ?: 'CNY';
  }

  /** Returns a saved snapshot only; report reads never contact a rate provider. */
  public function snapshot(): array {
    $saved = $this->state->get(self::STATE, []);
    $date = $saved['rate_date'] ?? NULL;
    return [
      'currency' => $this->currency(),
      'source' => 'ECB',
      'base' => 'EUR',
      'rate_date' => $date,
      'rates' => $saved['rates'] ?? ['EUR' => '1'],
      'fetched_at' => isset($saved['fetched_at']) ? gmdate('c', $saved['fetched_at']) : NULL,
      'stale' => $date !== NULL && (($saved['refresh_failed'] ?? FALSE)
        || $date < gmdate('Y-m-d', $this->time->getCurrentTime() - 7 * 86400)),
    ];
  }

  /** Converts non-negative integer micros using exact decimal rates and half-up rounding. */
  public static function convertMicros(string $micros, string $from, array $snapshot): ?string {
    if ($from === $snapshot['currency']) {
      return $micros;
    }
    if ($snapshot['rate_date'] === NULL || !isset($snapshot['rates'][$from],
      $snapshot['rates'][$snapshot['currency']])) {
      return NULL;
    }
    $units = static function (string $rate): int {
      [$whole, $fraction] = array_pad(explode('.', $rate), 2, '');
      return (int) ($whole . str_pad($fraction, 6, '0'));
    };
    $source = $units($snapshot['rates'][$from]);
    $target = $units($snapshot['rates'][$snapshot['currency']]);
    // Rate units are bounded by parse(); each digit operation fits in a 64-bit integer.
    $product = '';
    $carry = 0;
    for ($i = strlen($micros) - 1; $i >= 0; $i--) {
      $value = (int) $micros[$i] * $target + $carry;
      $product = ($value % 10) . $product;
      $carry = intdiv($value, 10);
    }
    $product = UsageCostSummary::addDecimal(($carry ? (string) $carry : '') . $product,
      (string) intdiv($source, 2));
    $quotient = '';
    $remainder = 0;
    foreach (str_split($product) as $digit) {
      $value = $remainder * 10 + (int) $digit;
      $quotient .= (string) intdiv($value, $source);
      $remainder = $value % $source;
    }
    return ltrim($quotient, '0') ?: '0';
  }

  /** Checks daily data at most every six hours; failed checks retry after one hour. */
  public function refresh(bool $force = FALSE): bool {
    if (!$this->lock->acquire(self::STATE, 30.0)) {
      return FALSE;
    }
    try {
      $saved = $this->state->get(self::STATE, []);
      $now = $this->time->getCurrentTime();
      $interval = ($saved['refresh_failed'] ?? FALSE) ? 3600 : 21600;
      if (!$force && isset($saved['checked_at']) && $now - $saved['checked_at'] < $interval) {
        return !($saved['refresh_failed'] ?? FALSE);
      }
      try {
        $response = $this->http->request('GET', self::SOURCE, [
          'connect_timeout' => 5, 'timeout' => 15, 'read_timeout' => 10,
          'allow_redirects' => FALSE, 'stream' => TRUE,
          'headers' => ['Accept' => 'application/xml'],
        ]);
        if ($response->getStatusCode() !== 200) {
          throw new \UnexpectedValueException('Unexpected exchange-rate response status.');
        }
        $body = $response->getBody();
        try {
          $xml = '';
          while (!$body->eof() && strlen($xml) <= self::MAX_BYTES) {
            $chunk = $body->read(min(8192, self::MAX_BYTES + 1 - strlen($xml)));
            if ($chunk === '') {
              throw new \UnexpectedValueException('Incomplete exchange-rate response.');
            }
            $xml .= $chunk;
          }
        }
        finally {
          $body->close();
        }
        $parsed = self::parse($xml, gmdate('Y-m-d', $now));
        if (isset($saved['rate_date']) && $parsed['rate_date'] < $saved['rate_date']) {
          throw new \UnexpectedValueException('Exchange-rate date moved backwards.');
        }
        $this->state->set(self::STATE, $parsed + [
          'fetched_at' => $now, 'checked_at' => $now, 'refresh_failed' => FALSE,
        ]);
        return TRUE;
      }
      catch (\Throwable) {
        // Keep the last complete snapshot. Neither logs nor reports need upstream error bodies.
        $saved['checked_at'] = $now;
        $saved['refresh_failed'] = TRUE;
        $this->state->set(self::STATE, $saved);
        $this->logger->warning('ECB reference-rate refresh failed; the previous snapshot is retained.');
        return FALSE;
      }
    }
    finally {
      $this->lock->release(self::STATE);
    }
  }

  /** Validates one complete ECB observation before replacing the saved snapshot. */
  public static function parse(string $xml, string $today): array {
    if (strlen($xml) > self::MAX_BYTES || stripos($xml, '<!DOCTYPE') !== FALSE) {
      throw new \UnexpectedValueException('Invalid exchange-rate document.');
    }
    $document = new \DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    try {
      if (!$document->loadXML($xml, LIBXML_NONET)) {
        throw new \UnexpectedValueException('Invalid exchange-rate XML.');
      }
    }
    finally {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
    $path = new \DOMXPath($document);
    $path->registerNamespace('fx', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');
    $observations = $path->query('//fx:Cube[@time]');
    if ($observations->length !== 1) {
      throw new \UnexpectedValueException('Expected one exchange-rate observation.');
    }
    $observation = $observations->item(0);
    $date = $observation->getAttribute('time');
    $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date || $date > $today) {
      throw new \UnexpectedValueException('Invalid exchange-rate date.');
    }
    $rates = ['EUR' => '1'];
    foreach ($path->query('fx:Cube', $observation) as $node) {
      $currency = $node->getAttribute('currency');
      $rate = $node->getAttribute('rate');
      if (!preg_match('/^[A-Z]{3}$/D', $currency) || isset($rates[$currency])
        || !preg_match('/^(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,6})?$/D', $rate)
        || (float) $rate <= 0) {
        throw new \UnexpectedValueException('Invalid exchange-rate value.');
      }
      $rates[$currency] = $rate;
    }
    if (!isset($rates['CNY'], $rates['USD'])) {
      throw new \UnexpectedValueException('Incomplete exchange-rate observation.');
    }
    return ['rate_date' => $date, 'rates' => $rates];
  }

}
