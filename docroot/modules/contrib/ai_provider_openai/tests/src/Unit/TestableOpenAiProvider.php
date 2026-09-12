<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_openai\Unit;

use Drupal\ai_provider_openai\Plugin\AiProvider\OpenAiProvider;
use OpenAI\Client;

/**
 * Test-only subclass that injects a real OpenAI Client.
 *
 * The parent constructor is final and requires Drupal DI; we bypass it with
 * ReflectionClass::newInstanceWithoutConstructor() in tests. getClient() is
 * overridden so its return value satisfies the Client type declaration while
 * also ensuring $this->client is populated for the actual API call path.
 */
class TestableOpenAiProvider extends OpenAiProvider {

  /**
   * The test client to use instead of a real OpenAI client.
   *
   * @var \OpenAI\Client
   */
  private Client $testClient;

  /**
   * Injects the client used for API calls in tests.
   *
   * @param \OpenAI\Client $client
   *   A real Client built with a mock TransporterContract.
   */
  public function injectTestClient(Client $client): void {
    $this->testClient = $client;
    // Also set $this->client (the untyped parent property) so the API call
    // path in moderationEndpoints() — which reads $this->client directly —
    // finds a live client rather than null.
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(string $api_key = ''): Client {
    // Return the pre-injected client directly — the parent implementation
    // calls createClient() which needs a live API key and HTTP client.
    return $this->testClient;
  }

}
