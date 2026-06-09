<?php

namespace Drupal\ai\ProviderClient;

use OpenAI\Client;

/**
 * Interface for OpenAI-based provider clients.
 */
interface OpenAiBasedProviderClientInterface {

  /**
   * Indicates if the provider requires authentication.
   *
   * @return bool
   *   TRUE if authentication is required, FALSE otherwise.
   */
  public function hasAuthentication(): bool;

  /**
   * Gets the endpoint for the provider, if any.
   *
   * @return string|null
   *   The endpoint URL or NULL if not set.
   */
  public function getEndpoint(): ?string;

  /**
   * Gets the raw OpenAI client.
   *
   * @param string $api_key
   *   Optional API key to hot-swap authentication.
   *
   * @return \OpenAI\Client
   *   The OpenAI client instance.
   */
  public function getClient(string $api_key = ''): Client;

}
