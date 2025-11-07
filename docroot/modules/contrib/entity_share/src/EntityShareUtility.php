<?php

declare(strict_types = 1);

namespace Drupal\entity_share;

use Drupal\Component\Utility\UrlHelper;

/**
 * Contains helper methods for Entity Share.
 */
class EntityShareUtility {

  /**
   * Uniformize JSON data in case of single value.
   *
   * @param array $data
   *   The JSON data.
   *
   * @return array
   *   An array of data.
   */
  public static function prepareData(array $data) {
    if (self::isNumericArray($data)) {
      return $data;
    }
    else {
      return [$data];
    }
  }

  /**
   * Check if a array is numeric.
   *
   * @param array $array
   *   The array to check.
   *
   * @return bool
   *   TRUE if the array is numeric. FALSE in case of associative array.
   */
  public static function isNumericArray(array $array) {
    foreach (array_keys($array) as $a) {
      if (!is_int($a)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Converts any expected "changed" time value into integer timestamp.
   *
   * Needed mostly for converting times coming from Remotes.
   *
   * @param string|int $changed_time
   *   The timestamp or formatted date of "changed" date.
   *
   * @return int
   *   The timestamp of "changed" date.
   */
  public static function convertChangedTime($changed_time) {
    $entity_changed_time = 0;
    // If the website is using backward compatible timestamps output.
    // @see https://www.drupal.org/node/2859657.
    // The value is cast in integer for
    // https://www.drupal.org/node/2837696.
    if (is_numeric($changed_time)) {
      $entity_changed_time = (int) $changed_time;
    }
    else {
      $changed_datetime = \DateTime::createFromFormat(\DateTime::RFC3339, $changed_time);
      if ($changed_datetime) {
        $entity_changed_time = $changed_datetime->getTimestamp();
      }
    }
    return $entity_changed_time;
  }

  /**
   * Alters the JSON:API URL by applying filtering by UUID's.
   *
   * @param string $url
   *   URL to request.
   * @param string[] $uuids
   *   Array of entity UUID's.
   *
   * @return string
   *   The URL with UUID filter.
   */
  public static function prepareUuidsFilteredUrl(string $url, array $uuids) {
    $parsed_url = UrlHelper::parse($url);
    $query = $parsed_url['query'];
    $query['filter']['uuid-filter'] = [
      'condition' => [
        'path' => 'id',
        'operator' => 'IN',
        'value' => $uuids,
      ],
    ];
    $query = UrlHelper::buildQuery($query);
    return $parsed_url['path'] . '?' . $query;
  }

  /**
   * Compute the max size.
   *
   * It will be the minimum between import config and channel config.
   *
   * @param \Drupal\entity_share_client\Entity\ImportConfigInterface|null $import_config
   *   The selected import config.
   * @param string $channel_id
   *   The selected channel ID.
   * @param array $channel_infos
   *   The channels infos.
   *
   * @return int
   *   The max size.
   */
  public static function getMaxSize($import_config, string $channel_id, array $channel_infos) : int {
    $import_maxsize = 50;
    $channel_maxsize = 50;

    if (!is_null($import_config)) {
      $import_maxsize = $import_config->get('import_maxsize');
    }

    if (isset($channel_infos[$channel_id]['channel_maxsize'])) {
      $channel_maxsize = $channel_infos[$channel_id]['channel_maxsize'];
    }

    return (int) min($import_maxsize, $channel_maxsize);
  }

}
