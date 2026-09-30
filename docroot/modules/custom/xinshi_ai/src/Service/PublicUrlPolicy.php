<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\xinshi_ai\Exception\ImageSafetyException;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Resolves only public HTTP destinations, for subsequent connection pinning.
 */
class PublicUrlPolicy {

  /**
   * @return array{host: string, port: int, address: string}
   *   An approved destination; DNS must not be resolved again by the transport.
   */
  public function destination(string $url): array {
    $parts = parse_url($url);
    if (strlen($url) > 4096 || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || !$parts
      || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], TRUE)
      || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
      throw new ImageSafetyException('Remote URL must be an absolute public HTTP(S) URL without user information or fragment.');
    }
    $host = strtolower(trim($parts['host'] ?? '', '[]'));
    $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);
    if (!in_array($port, [80, 443], TRUE)) {
      throw new ImageSafetyException('Remote URL port must be 80 or 443.');
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      $addresses = [$host];
    }
    else {
      // Reject single-label, encoded, numeric-alias and local hostnames.
      if (!preg_match('/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/', $host)
        || preg_match('/\.(?:localhost|local|internal|home|lan)\z/', $host)) {
        throw new ImageSafetyException('Remote hostname is not allowed.');
      }
      $addresses = $this->resolve($host);
    }
    if (!$addresses) {
      throw new ImageSafetyException('Remote hostname could not be resolved.');
    }
    foreach ($addresses as $address) {
      if (!$this->isPublic($address)) {
        throw new ImageSafetyException('Remote destination must resolve only to public addresses.');
      }
    }
    return ['host' => $host, 'port' => $port, 'address' => reset($addresses)];
  }

  /**
   * Resolve both address families; every returned address must pass policy.
   */
  protected function resolve(string $host): array {
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    $addresses = [];
    foreach ($records ?: [] as $record) {
      if (isset($record['ip']) || isset($record['ipv6'])) {
        $addresses[] = $record['ip'] ?? $record['ipv6'];
      }
    }
    return array_values(array_unique($addresses));
  }

  private function isPublic(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
      return FALSE;
    }
    if (str_contains($ip, ':')) {
      // Exclude mapped IPv4, translation/tunnel prefixes and special-use ranges.
      return IpUtils::checkIp($ip, '2000::/3')
        && !IpUtils::checkIp($ip, ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20']);
    }
    return !IpUtils::checkIp($ip, ['0.0.0.0/8', '100.64.0.0/10', '169.254.0.0/16', '192.0.0.0/24',
      '192.0.2.0/24', '192.88.99.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4']);
  }

}
