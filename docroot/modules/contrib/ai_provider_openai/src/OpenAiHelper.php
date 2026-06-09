<?php

namespace Drupal\ai_provider_openai;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;

/**
 * Small helper commands that both form and provider needs.
 */
class OpenAiHelper {

  use StringTranslationTrait;

  /**
   * The config factory service.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    private readonly MessengerInterface $messenger,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->configFactory = $config_factory;
  }

  /**
   * Check the rate limit and create a warning message if its free tier.
   *
   * @param string $api_key
   *   The API Key.
   */
  public function testRateLimit(string $api_key) {
    $headers = [];

    // Create a Guzzle client with a handler to capture response headers.
    $guzzle = new Client([
      'on_stats' => function (TransferStats $stats) use (&$headers) {
        if ($stats->hasResponse()) {
          $headers = $stats->getResponse()->getHeaders();
        }
      },
    ]);

    // Build the endpoint from config.
    $host = $this->configFactory->get('ai_provider_openai.settings')->get('host');
    $endpoint = 'https://' . ($host ?: 'api.openai.com/v1') . '/chat/completions';

    // We need to catch errors, since the API key might be invalid, so plain
    // Guzzle is used.
    $content = $guzzle->request('POST', $endpoint, [
      'headers' => [
        'Authorization' => 'Bearer ' . $api_key,
      ],
      // Do not throw errors.
      'http_errors' => FALSE,
      'json' => [
        'model' => 'gpt-4o-mini',
        'messages' => [
          [
            'role' => 'user',
            'content' => 'Answer with Hello',
          ],
        ],
      ],
    ]);

    $response = Json::decode($content->getBody()->getContents());
    if ((isset($response['error']['code']) && $response['error']['code'] === 'insufficient_quota') || (isset($headers['x-ratelimit-limit-requests'][0]) && $headers['x-ratelimit-limit-requests'][0] <= 200)) {
      $this->messenger->addError($this->t('You are using the Free Tier of OpenAI or have run out of quota. This will limit almost all the ways you can use AI in Drupal. Please add $5 to your OpenAI account to reach Tier 1. You can read more here <a href=":link" target="_blank">:link</a>.', [
        ':link' => 'https://platform.openai.com/docs/guides/rate-limits',
      ]));
    }
  }

}
