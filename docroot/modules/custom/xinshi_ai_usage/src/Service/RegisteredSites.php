<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Core\Site\Settings;

/**
 * The logical site ids this installation records usage for.
 *
 * Events, price books and the local image producer must agree on the site id,
 * so there is exactly one resolution rule: an explicit `xinshi_ai_usage.site_id`
 * setting wins; otherwise the distinct site ids of the registered producers,
 * settings.php entries first, then the admin registry.
 */
final class RegisteredSites {

  public const SITE_SETTING = 'xinshi_ai_usage.site_id';
  public const PRODUCERS_SETTING = 'xinshi_ai_usage.producers';

  /**
   * @return list<string>
   *   Site ids in precedence order; empty when nothing is configured.
   */
  public static function list(Settings $settings, ProducerVault $vault): array {
    $configured = $settings->get(self::SITE_SETTING);
    if (is_string($configured) && $configured !== '') {
      return [$configured];
    }
    $ids = [];
    $producers = $settings->get(self::PRODUCERS_SETTING, []);
    $producers = (is_array($producers) ? $producers : []) + $vault->all();
    foreach ($producers as $producer) {
      $siteId = is_array($producer) ? ($producer['site_id'] ?? NULL) : NULL;
      if (is_string($siteId) && $siteId !== '') {
        $ids[$siteId] = $siteId;
      }
    }
    return array_values($ids);
  }

}
