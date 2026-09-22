<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

/**
 * Time arithmetic shared by the user and site usage reports.
 *
 * Instants are milliseconds since the epoch; buckets are cut on wall-clock
 * boundaries of the requested IANA zone, so a day in Asia/Shanghai starts at
 * 16:00 UTC and daylight-saving days are 23 or 25 hours long.
 */
final class ReportTime {

  public const GRANULARITIES = ['hour', 'day'];

  public static function at(int $ms, \DateTimeZone $tz): \DateTimeImmutable {
    return (new \DateTimeImmutable('@' . intdiv($ms, 1000)))->setTimezone($tz)
      ->modify(sprintf('+%d milliseconds', $ms % 1000));
  }

  public static function iso(int $ms, \DateTimeZone $tz): string {
    return self::at($ms, $tz)->format('Y-m-d\TH:i:s.vP');
  }

  /** The label of a bucket: its local start, minutes and seconds zeroed. */
  public static function bucketFormat(string $granularity): string {
    return $granularity === 'hour' ? 'Y-m-d\TH:00:00P' : 'Y-m-d\T00:00:00P';
  }

  /**
   * Labels of the consecutive buckets covering `[from, to)`, oldest first.
   *
   * The first bucket is the floor of `from`, so it may start before the
   * window. An attempt formatted with bucketFormat() yields the label of the
   * bucket it belongs to, so grouping and this list use the same keys.
   *
   * @return list<string>
   */
  public static function bucketLabels(int $fromMs, int $toMs, \DateTimeZone $tz, string $granularity): array {
    $format = self::bucketFormat($granularity);
    $labels = [];
    $cursor = self::floor(self::at($fromMs, $tz), $granularity);
    $end = self::at($toMs, $tz);
    while ($cursor < $end) {
      $labels[] = $cursor->format($format);
      $cursor = $cursor->modify($granularity === 'hour' ? '+1 hour' : '+1 day');
    }
    return $labels;
  }

  private static function floor(\DateTimeImmutable $time, string $granularity): \DateTimeImmutable {
    return $granularity === 'hour' ? $time->setTime((int) $time->format('G'), 0) : $time->setTime(0, 0);
  }

}
