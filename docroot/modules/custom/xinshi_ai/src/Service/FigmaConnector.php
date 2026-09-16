<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\xinshi_ai\Exception\FigmaException;
use GuzzleHttp\ClientInterface;

/** Personal OAuth and bounded, read-only Figma requests; secrets remain in Drupal. */
final class FigmaConnector {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AccountProxyInterface $account,
    private readonly FigmaVault $vault,
    private readonly ClientInterface $http,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
  ) {}

  public static function origin(string $uri): ?string {
    $url = parse_url($uri);
    if (!is_array($url) || !filter_var($uri, FILTER_VALIDATE_URL) ||
        isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) ||
        ($url['path'] ?? '') !== '/chat/figma/callback' ||
        (($url['scheme'] ?? '') !== 'https' && !(($url['scheme'] ?? '') === 'http' &&
          in_array($url['host'] ?? '', ['localhost', '127.0.0.1'], TRUE)))) {
      return NULL;
    }
    return $url['scheme'] . '://' . $url['host'] . (isset($url['port']) ? ':' . $url['port'] : '');
  }

  public static function returnPath(mixed $value): string {
    if (!is_string($value) || strlen($value) > 2000 || preg_match('/[\\\\\r\n]/', $value) ||
        !preg_match('@^/(?:[a-z]{2}(?:-[a-z]+)?/)?builder(?:/|\?|#|$)@i', $value)) {
      return '/builder/chat';
    }
    return $value;
  }

  private function configuration(): array {
    $value = $this->configFactory->get('xinshi_ai.settings')->get('harness.mcp.figma');
    $secret = $this->vault->get('client_secret')['value'] ?? '';
    if (!is_array($value) || ($value['enabled'] ?? FALSE) !== TRUE ||
        !is_string($value['client_id'] ?? NULL) || $value['client_id'] === '' ||
        !is_string($value['redirect_uri'] ?? NULL) || !($origin = self::origin($value['redirect_uri'])) ||
        !is_string($secret) || $secret === '') {
      throw new FigmaException('figma_not_configured', 503);
    }
    return ['client_id' => $value['client_id'], 'redirect_uri' => $value['redirect_uri'],
      'origin' => $origin, 'secret' => $secret,
      'app' => hash('sha256', $value['client_id'] . "\0" . $secret . "\0" . $value['redirect_uri'])];
  }

  private function owner(): string {
    if (!$this->account->isAuthenticated()) {
      throw new FigmaException('figma_login_required', 401);
    }
    return (string) $this->account->id();
  }

  private function locked(callable $action): mixed {
    $name = 'xinshi_ai.figma.' . $this->owner();
    if (!$this->lock->acquire($name, 120.0)) {
      throw new FigmaException('figma_busy', 429);
    }
    try {
      return $action();
    }
    finally {
      $this->lock->release($name);
    }
  }

  private function connection(array $config): ?array {
    $value = $this->vault->get('connection.' . $this->owner());
    return ($value['app'] ?? NULL) === $config['app'] ? $value : NULL;
  }

  public function status(): array {
    $this->owner();
    try {
      $config = $this->configuration();
    }
    catch (FigmaException) {
      return ['available' => FALSE, 'connected' => FALSE];
    }
    try {
      $connection = $this->connection($config);
    }
    catch (FigmaException) {
      $connection = NULL;
    }
    return ['available' => TRUE, 'connected' => $connection !== NULL,
      'handle' => $connection['handle'] ?? NULL, 'redirectUri' => $config['redirect_uri']];
  }

  public function authorize(mixed $returnTo): array {
    $config = $this->configuration();
    return $this->locked(function () use ($config, $returnTo): array {
      $state = self::random();
      $verifier = self::random();
      $this->vault->set('pending.' . $this->owner(), [
        'hash' => hash('sha256', $state), 'verifier' => $verifier, 'app' => $config['app'],
        'returnTo' => self::returnPath($returnTo), 'expires' => $this->time->getCurrentTime() + 600,
      ]);
      return ['state' => $state, 'url' => 'https://www.figma.com/oauth?' . http_build_query([
        'client_id' => $config['client_id'], 'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code', 'scope' => 'file_content:read current_user:read', 'state' => $state,
        'code_challenge' => self::base64url(hash('sha256', $verifier, TRUE)), 'code_challenge_method' => 'S256',
      ], '', '&', PHP_QUERY_RFC3986)];
    });
  }

  public function complete(string $state, mixed $code, bool $denied): array {
    $config = $this->configuration();
    return $this->locked(function () use ($config, $state, $code, $denied): array {
      $id = 'pending.' . $this->owner();
      $pending = $this->vault->get($id);
      if (!$pending || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $state) ||
          !hash_equals($pending['hash'], hash('sha256', $state)) ||
          $pending['expires'] <= $this->time->getCurrentTime() || $pending['app'] !== $config['app']) {
        throw new FigmaException('figma_authorization_expired', 409);
      }
      $this->vault->delete($id);
      $result = ['returnTo' => $pending['returnTo'], 'connected' => FALSE];
      try {
        if ($denied || !is_string($code) || $code === '' || strlen($code) > 4096) {
          throw new FigmaException('figma_authorization_failed');
        }
        $tokens = $this->oauth($config, 'token', [
          'code' => $code, 'redirect_uri' => $config['redirect_uri'],
          'grant_type' => 'authorization_code', 'code_verifier' => $pending['verifier'],
        ]);
        $fields = $this->tokenFields($tokens);
        if (!is_string($tokens['refresh_token'] ?? NULL) || $tokens['refresh_token'] === '' ||
            !is_string($tokens['user_id_string'] ?? NULL)) {
          throw new FigmaException('figma_unavailable', 503);
        }
        $profile = $this->json('/v1/me', ['headers' => ['Authorization' => 'Bearer ' . $fields['access_token']]]);
        if (($profile['id'] ?? NULL) !== $tokens['user_id_string'] || !is_string($profile['handle'] ?? NULL)) {
          throw new FigmaException('figma_unavailable', 503);
        }
        $this->vault->set('connection.' . $this->owner(), $fields + [
          'refresh_token' => $tokens['refresh_token'], 'figma_id' => $profile['id'],
          'handle' => mb_substr($profile['handle'], 0, 200), 'app' => $config['app'],
        ]);
        $result['connected'] = TRUE;
      }
      catch (FigmaException $error) {
        $result['error'] = $error->error;
      }
      return $result;
    });
  }

  public function disconnect(): array {
    return $this->locked(function (): array {
      $this->vault->delete('connection.' . $this->owner());
      $this->vault->delete('pending.' . $this->owner());
      return ['connected' => FALSE];
    });
  }

  /** Only the three design-read operations used by Node can reach Figma. */
  public static function readPath(string $path): string {
    if (strlen($path) > 4096 || preg_match('/[\\\\\r\n#]/', $path)) {
      throw new FigmaException('figma_invalid_url');
    }
    $url = parse_url($path);
    if (!is_array($url) || isset($url['scheme']) || isset($url['host']) ||
        !preg_match('@^/v1/(files/([A-Za-z0-9_-]{6,128})/(nodes|images)|images/([A-Za-z0-9_-]{6,128}))$@D', $url['path'] ?? '', $matches)) {
      throw new FigmaException('figma_invalid_url');
    }
    parse_str($url['query'] ?? '', $query);
    $kind = str_starts_with($url['path'], '/v1/images/') ? 'export' : $matches[3];
    $allowed = match ($kind) { 'nodes' => ['ids'], 'export' => ['ids', 'format', 'scale', 'version'], default => [] };
    if (array_diff(array_keys($query), $allowed) || array_diff($allowed, array_keys($query)) ||
        count(array_filter($query, 'is_string')) !== count($query)) {
      throw new FigmaException('figma_invalid_url');
    }
    if ($kind !== 'images') {
      $ids = explode(',', $query['ids']);
      if (count($ids) > ($kind === 'nodes' ? 1 : 32) ||
          array_filter($ids, static fn($id) => strlen($id) > 256 ||
            !preg_match('/^I?\d+:\d+(?:;\d+:\d+)*$/D', $id))) {
        throw new FigmaException('figma_invalid_url');
      }
      if ($kind === 'export' && ($query['format'] !== 'png' || $query['scale'] !== '2' ||
          !preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $query['version']))) {
        throw new FigmaException('figma_invalid_url');
      }
    }
    return $url['path'] . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
  }

  public function read(string $path): array {
    $path = self::readPath($path);
    $config = $this->configuration();
    return $this->locked(function () use ($config, $path): array {
      $grant = $this->connection($config);
      if (!$grant) {
        throw new FigmaException('figma_connection_required', 409);
      }
      $refreshed = $grant['expires_at'] <= $this->time->getCurrentTime() + 60;
      if ($refreshed) {
        $grant = $this->refresh($config, $grant);
      }
      try {
        return $this->json($path, ['headers' => ['Authorization' => 'Bearer ' . $grant['access_token']]]);
      }
      catch (FigmaException $error) {
        if ($error->status !== 401) {
          throw $error;
        }
        if (!$refreshed) {
          $grant = $this->refresh($config, $grant);
          try {
            return $this->json($path, ['headers' => ['Authorization' => 'Bearer ' . $grant['access_token']]]);
          }
          catch (FigmaException $retry) {
            if ($retry->status !== 401) {
              throw $retry;
            }
          }
        }
        $this->vault->delete('connection.' . $this->owner());
        throw new FigmaException('figma_connection_required', 409);
      }
    });
  }

  private function refresh(array $config, array $grant): array {
    try {
      $tokens = $this->oauth($config, 'refresh', ['refresh_token' => $grant['refresh_token']]);
      $grant = array_replace($grant, $this->tokenFields($tokens));
      $this->vault->set('connection.' . $this->owner(), $grant);
      return $grant;
    }
    catch (FigmaException $error) {
      if (in_array($error->status, [400, 401, 403], TRUE)) {
        $this->vault->delete('connection.' . $this->owner());
        throw new FigmaException('figma_connection_required', 409);
      }
      throw $error;
    }
  }

  private function tokenFields(array $tokens): array {
    if (!is_string($tokens['access_token'] ?? NULL) || $tokens['access_token'] === '' ||
        !is_numeric($tokens['expires_in'] ?? NULL) || !is_finite((float) $tokens['expires_in']) ||
        $tokens['expires_in'] <= 0) {
      throw new FigmaException('figma_unavailable', 503);
    }
    return ['access_token' => $tokens['access_token'], 'expires_at' => $this->time->getCurrentTime() + (int) $tokens['expires_in']];
  }

  private function oauth(array $config, string $operation, array $data): array {
    return $this->json('/v1/oauth/' . $operation, [
      'auth' => [$config['client_id'], $config['secret']], 'form_params' => $data,
    ], 'POST');
  }

  private function json(string $path, array $options, string $method = 'GET'): array {
    try {
      $response = $this->http->request($method, 'https://api.figma.com' . $path, $options + [
        'timeout' => 25, 'connect_timeout' => 5, 'allow_redirects' => FALSE,
        'http_errors' => FALSE, 'stream' => TRUE, 'cookies' => FALSE,
      ]);
      $status = $response->getStatusCode();
      $limit = $status >= 200 && $status < 300 ? 4 * 1024 * 1024 : 16384;
      $stream = $response->getBody();
      try {
        if ((int) $response->getHeaderLine('Content-Length') > $limit) {
          throw new FigmaException('figma_too_large', 413);
        }
        $body = '';
        while (!$stream->eof()) {
          $body .= $stream->read(min(8192, $limit + 1 - strlen($body)));
          if (strlen($body) > $limit) {
            throw new FigmaException('figma_too_large', 413);
          }
        }
      }
      finally {
        $stream->close();
      }
      $data = json_decode($body, TRUE);
      if ($status === 401 || ($status === 403 && is_string($data['err'] ?? NULL) &&
          preg_match('/^(?:invalid|expired) (?:oauth |access )?token\b/i', $data['err']))) {
        throw new FigmaException('figma_connection_required', 401);
      }
      if ($status < 200 || $status >= 300) {
        $code = $status === 429 ? 'figma_rate_limited' : (in_array($status, [403, 404], TRUE) ? 'figma_file_unavailable' : 'figma_unavailable');
        throw new FigmaException($code, $status >= 400 && $status < 600 ? $status : 502);
      }
      if (!is_array($data) || array_is_list($data)) {
        throw new FigmaException('figma_unavailable', 503);
      }
      return $data;
    }
    catch (FigmaException $error) {
      throw $error;
    }
    catch (\Throwable) {
      throw new FigmaException('figma_unavailable', 503);
    }
  }

  private static function random(): string {
    return self::base64url(random_bytes(32));
  }

  private static function base64url(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
  }

}
