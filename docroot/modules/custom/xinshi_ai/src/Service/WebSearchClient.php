<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Utils;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Calls only the official DeepSeek endpoints with a server-owned credential. */
final class WebSearchClient {

  private const MAX_BYTES = 1048576;

  public function __construct(
    private readonly ClientInterface $http,
    private readonly WebSearchCredentials $credentials,
    private readonly ConfigFactoryInterface $config,
    private readonly AccountProxyInterface $account,
  ) {}

  /** Validate user inputs before making a paid, read-only provider request. */
  public function read(string $operation, array $input): array {
    if (!$this->account->isAuthenticated()) {
      throw new HttpException(403, 'web_search_access_denied');
    }
    if (!in_array($operation, ['search', 'fetch'], TRUE)) {
      throw new HttpException(400, 'web_search_invalid_input');
    }
    $settings = HarnessSettings::normalize($this->config->get('xinshi_ai.settings')->get('harness'));
    if (in_array('web_' . $operation, $settings['tools']['disabled'], TRUE)) {
      throw new HttpException(403, 'web_search_disabled');
    }
    $search = $operation === 'search';
    $allowed = $search ? ['query', 'count'] : ['url'];
    $value = $input[$search ? 'query' : 'url'] ?? NULL;
    if (array_diff(array_keys($input), $allowed) || !is_string($value) || trim($value) === '' ||
      mb_strlen(trim($value)) > ($search ? 128 : 8192)) {
      throw new HttpException(400, 'web_search_invalid_input');
    }
    $value = trim($value);
    if ($search) {
      $count = array_key_exists('count', $input) ? $input['count'] : 5;
      if (!is_int($count) || $count < 1 || $count > 10) {
        throw new HttpException(400, 'web_search_invalid_input');
      }
      $body = ['query' => $value, 'count' => $count, 'need_content' => FALSE];
    }
    else {
      if (!self::isWebUrl($value)) {
        throw new HttpException(400, 'web_search_invalid_input');
      }
      $body = ['url' => $value];
    }
    try {
      $key = $this->credentials->get();
    }
    catch (\Throwable) {
      throw new HttpException(503, 'web_search_not_configured');
    }
    try {
      $response = $this->http->request('POST', 'https://api.deepseek.com/v1/tools/' . $operation, [
        'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
        'json' => $body, 'timeout' => 20, 'connect_timeout' => 5, 'read_timeout' => 20,
        'allow_redirects' => FALSE, 'http_errors' => FALSE, 'stream' => TRUE,
      ]);
      $status = $response->getStatusCode();
      if ($status !== 200) {
        $response->getBody()->close();
        throw new HttpException($status === 429 ? 429 : 502, 'web_search_provider_failed');
      }
      $stream = $response->getBody();
      try {
        $json = Utils::copyToString($stream, self::MAX_BYTES + 1);
      }
      finally {
        $stream->close();
      }
      if (strlen($json) > self::MAX_BYTES) {
        throw new \RuntimeException();
      }
      $data = json_decode($json, TRUE, 32, JSON_THROW_ON_ERROR);
      if (!is_array($data)) {
        throw new \RuntimeException();
      }
      if ($search) {
        if (!isset($data['results']) || !is_array($data['results']) || !array_is_list($data['results'])) {
          throw new \RuntimeException();
        }
        $results = [];
        foreach (array_slice($data['results'], 0, $count) as $result) {
          if (!is_array($result) || !is_string($result['title'] ?? NULL) ||
            !is_string($result['url'] ?? NULL) || !self::isWebUrl($result['url']) ||
            !is_string($result['summary'] ?? NULL) || !is_string($result['snippet'] ?? NULL)) {
            throw new \RuntimeException();
          }
          $results[] = [
            'title' => mb_substr($result['title'], 0, 500), 'url' => $result['url'],
            'snippet' => mb_substr($result['snippet'], 0, 500),
            'summary' => mb_substr($result['summary'], 0, 2400),
            'date' => is_string($result['date'] ?? NULL) ? mb_substr($result['date'], 0, 64) : NULL,
          ];
        }
        return ['results' => $results];
      }
      if (!is_string($data['title'] ?? NULL) || !is_string($data['content'] ?? NULL)) {
        throw new \RuntimeException();
      }
      return ['url' => $value, 'title' => mb_substr($data['title'], 0, 500),
        'content' => mb_substr($data['content'], 0, 24000),
        'truncated' => mb_strlen($data['content']) > 24000];
    }
    catch (HttpException $error) {
      throw $error;
    }
    catch (\Throwable) {
      // Provider exceptions can contain authorization headers and remote response bodies.
      throw new HttpException(502, 'web_search_provider_failed');
    }
  }

  private static function isWebUrl(string $value): bool {
    $url = parse_url($value);
    return strlen($value) <= 8192 && filter_var($value, FILTER_VALIDATE_URL) !== FALSE &&
      is_array($url) && in_array(strtolower($url['scheme'] ?? ''), ['http', 'https'], TRUE) &&
      !empty($url['host']) && !isset($url['user']) && !isset($url['pass']);
  }

}
