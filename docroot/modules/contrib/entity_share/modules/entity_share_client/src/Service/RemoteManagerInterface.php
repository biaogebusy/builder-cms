<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\entity_share_client\Entity\RemoteInterface;

/**
 * Remote manager interface methods.
 */
interface RemoteManagerInterface {

  /**
   * Performs a HTTP request. Wraps the HTTP client.
   *
   * We need to override this method during tests to emulate another website.
   *
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote website on which to perform the request.
   * @param string $method
   *   HTTP method.
   * @param string $url
   *   URL to request.
   * @param array $options
   *   Some options to alter the behavior.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  public function request(RemoteInterface $remote, $method, $url, array $options = []);

  /**
   * Performs a HTTP request on a JSON:API endpoint. Wraps the HTTP client.
   *
   * We need to override this method during tests to emulate another website.
   *
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote website on which to perform the request.
   * @param string $method
   *   HTTP method.
   * @param string $url
   *   URL to request.
   * @param array $options
   *   Some options to alter the behavior.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  public function jsonApiRequest(RemoteInterface $remote, $method, $url, array $options = []);

  /**
   * Get the channels infos of a remote website.
   *
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote website on which to get the channels infos.
   * @param array $options
   *   Some options to alter the behavior.
   *
   * @return array
   *   Channel infos as returned by entity_share_server entry point.
   */
  public function getChannelsInfos(RemoteInterface $remote, array $options = []);

  /**
   * Get the field mappings of a remote website.
   *
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The remote website on which to get the field mappings.
   *
   * @return array
   *   Field mappings as returned by entity_share_server entry point.
   */
  public function getfieldMappings(RemoteInterface $remote);

}
