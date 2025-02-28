<?php

namespace Drupal\media_entity_instagram\OEmbed;

use Drupal\Component\Utility\UrlHelper;
use Drupal\media\OEmbed\UrlResolver;

/**
 * Converts oEmbed media URLs into endpoint-specific resource URLs.
 */
class InstagramUrlResolver extends UrlResolver {

  /**
   * {@inheritdoc}
   */
  public function getResourceUrl($url, $max_width = NULL, $max_height = NULL, $settings = []) {
    // Try to get the resource URL from the static cache.
    if (isset($this->urlCache[$url])) {
      return $this->urlCache[$url];
    }

    // Try to get the resource URL from the persistent cache.
    $cache_id = "media:oembed_resource_url:$url:$max_width:$max_height:" . serialize($settings);

    $cached = $this->cacheBackend->get($cache_id);
    if ($cached) {
      $this->urlCache[$url] = $cached->data;
      return $this->urlCache[$url];
    }

    $provider = $this->getProviderByUrl($url);
    $endpoints = $provider->getEndpoints();
    $endpoint = reset($endpoints);
    $resource_url = $endpoint->buildResourceUrl($url);

    $parsed_url = UrlHelper::parse($resource_url);
    if ($max_width) {
      $parsed_url['query']['maxwidth'] = $max_width;
    }
    if ($settings['hidecaption']) {
      $parsed_url['query']['hidecaption'] = 1;
    }
    // Let other modules alter the resource URL, because some oEmbed providers
    // provide extra parameters in the query string. For example, Instagram also
    // supports the 'omitscript' parameter.
    $this->moduleHandler->alter('oembed_resource_url', $parsed_url, $provider);
    $resource_url = $parsed_url['path'] . '?' . rawurldecode(UrlHelper::buildQuery($parsed_url['query']));

    $this->urlCache[$url] = $resource_url;
    $this->cacheBackend->set($cache_id, $resource_url);

    return $resource_url;
  }

}
