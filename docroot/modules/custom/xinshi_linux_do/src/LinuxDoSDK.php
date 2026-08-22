<?php

namespace Drupal\xinshi_linux_do;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\file\FileRepositoryInterface;
use Drupal\user\UserInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service wrapping linux.do OAuth2 client + user mapping.
 */
class LinuxDoSDK {

  const CONFIG_NAME = 'xinshi_linux_do.settings';
  const FIELD_ID = 'field_linux_do_id';
  const FIELD_USERNAME = 'field_linux_do_username';
  // State token is valid for 10 minutes.
  const STATE_TTL = 600;

  protected ConfigFactoryInterface $configFactory;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected ClientInterface $httpClient;
  protected LoggerChannelInterface $logger;
  protected FileRepositoryInterface $fileRepository;
  protected FileSystemInterface $fileSystem;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    FileRepositoryInterface $file_repository,
    FileSystemInterface $file_system,
  ) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('xinshi_linux_do');
    $this->fileRepository = $file_repository;
    $this->fileSystem = $file_system;
  }

  /**
   * Get module config.
   */
  public function getConfig(): array {
    return $this->configFactory->get(self::CONFIG_NAME)->getRawData();
  }

  /**
   * Build the redirect URI registered on linux.do.
   */
  public function getRedirectUri(): string {
    return $GLOBALS['base_url'] . $GLOBALS['base_path'] . 'user/login/linux_do/callback';
  }

  /**
   * Build the linux.do authorize URL.
   */
  public function buildAuthorizeUrl(string $state): string {
    $config = $this->getConfig();
    $params = http_build_query([
      'response_type' => 'code',
      'client_id' => $config['client_id'] ?? '',
      'redirect_uri' => $this->getRedirectUri(),
      'state' => $state,
    ]);
    return rtrim($config['authorize_url'] ?? '', '?&') . '?' . $params;
  }

  /**
   * Build a stateless, signed state token.
   *
   * The token is `base64url(payload).hex(hmac)` where payload is JSON
   * `{n: nonce, t: issued_at, d: destination}`. We verify it on callback
   * without any server-side storage, so the OAuth flow does not depend on
   * the Drupal session cookie surviving the cross-site redirect to linux.do.
   */
  public function startState(?string $destination = NULL): string {
    $payload = [
      'n' => Crypt::randomBytesBase64(16),
      't' => time(),
    ];
    if ($destination !== NULL) {
      $payload['d'] = $destination;
    }
    $encoded = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $sig = hash_hmac('sha256', $encoded, $this->getStateSecret());
    return $encoded . '.' . $sig;
  }

  /**
   * Verify a state token, decode and stash the decoded payload for callers.
   */
  public function consumeState(string $received): bool {
    $this->decodedState = NULL;
    if (!str_contains($received, '.')) {
      return FALSE;
    }
    [$encoded, $sig] = explode('.', $received, 2);
    $expected = hash_hmac('sha256', $encoded, $this->getStateSecret());
    if (!hash_equals($expected, $sig)) {
      return FALSE;
    }
    $payload = json_decode(self::base64UrlDecode($encoded), TRUE);
    if (!is_array($payload) || empty($payload['t'])) {
      return FALSE;
    }
    if ((time() - (int) $payload['t']) > self::STATE_TTL) {
      return FALSE;
    }
    $this->decodedState = $payload;
    return TRUE;
  }

  /**
   * Return destination extracted from the most recently consumed state.
   */
  public function consumeDestination(): ?string {
    return $this->decodedState['d'] ?? NULL;
  }

  /**
   * Last successfully verified state payload.
   */
  protected ?array $decodedState = NULL;

  /**
   * Site-wide secret used to sign the state token.
   */
  protected function getStateSecret(): string {
    return Settings::getHashSalt();
  }

  /**
   * URL-safe base64 encode without padding.
   */
  protected static function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  /**
   * URL-safe base64 decode.
   */
  protected static function base64UrlDecode(string $data): string {
    $pad = strlen($data) % 4;
    if ($pad) {
      $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode(strtr($data, '-_', '+/')) ?: '';
  }

  /**
   * Exchange authorization code for access token.
   *
   * @return array|null
   *   Decoded token response, or NULL on failure.
   */
  public function exchangeCodeForToken(string $code): ?array {
    $config = $this->getConfig();
    try {
      $response = $this->httpClient->request('POST', $config['token_url'], [
        'form_params' => [
          'grant_type' => 'authorization_code',
          'code' => $code,
          'redirect_uri' => $this->getRedirectUri(),
        ],
        'auth' => [
          $config['client_id'] ?? '',
          $config['client_secret'] ?? '',
        ],
        'headers' => [
          'Accept' => 'application/json',
        ],
        'timeout' => 10,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
      if (!is_array($data) || empty($data['access_token'])) {
        $this->logger->error('linux.do token response missing access_token: @resp', [
          '@resp' => (string) $response->getBody(),
        ]);
        return NULL;
      }
      return $data;
    }
    catch (GuzzleException $e) {
      $this->logger->error('linux.do token exchange failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Fetch /api/user profile with the linux.do access token.
   *
   * @return array|null
   *   Decoded profile, or NULL on failure.
   */
  public function fetchProfile(string $access_token): ?array {
    $config = $this->getConfig();
    try {
      $response = $this->httpClient->request('GET', $config['userinfo_url'], [
        'headers' => [
          'Authorization' => 'Bearer ' . $access_token,
          'Accept' => 'application/json',
        ],
        'timeout' => 10,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
      if (!is_array($data) || empty($data['id']) || empty($data['email'])) {
        $this->logger->error('linux.do profile missing id/email: @resp', [
          '@resp' => (string) $response->getBody(),
        ]);
        return NULL;
      }
      return $data;
    }
    catch (GuzzleException $e) {
      $this->logger->error('linux.do profile fetch failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Map a linux.do profile to a Drupal user, creating if needed.
   *
   * @return array
   *   Tuple of [UserInterface|null, string|null reason]. reason is set on hard
   *   failure (e.g. email belongs to a Drupal user already bound to a different
   *   linux_do_id).
   */
  public function mapProfileToUser(array $profile): array {
    $user_storage = $this->entityTypeManager->getStorage('user');
    $linux_do_id = (int) $profile['id'];
    $email = mb_strtolower(trim($profile['email']));

    // 1) Lookup by linux_do_id.
    $by_id = $user_storage->loadByProperties([self::FIELD_ID => $linux_do_id]);
    if ($by_id) {
      $user = reset($by_id);
      $this->syncProfileFields($user, $profile);
      return [$user, NULL];
    }

    // 2) Lookup by email.
    $by_mail = $user_storage->loadByProperties(['mail' => $email]);
    if ($by_mail) {
      /** @var \Drupal\user\UserInterface $user */
      $user = reset($by_mail);
      // Hijack guard: if this user already has a different linux_do_id bound,
      // refuse to rebind.
      if ($user->hasField(self::FIELD_ID) && !$user->get(self::FIELD_ID)->isEmpty()) {
        $existing = (int) $user->get(self::FIELD_ID)->value;
        if ($existing !== $linux_do_id) {
          return [NULL, 'email_bound_to_different_linux_do_id'];
        }
      }
      $this->bindAndSync($user, $profile);
      return [$user, NULL];
    }

    // 3) Create new user.
    $name = $this->resolveUsername($profile['username'] ?? ('linuxdo_' . $linux_do_id));
    /** @var \Drupal\user\UserInterface $user */
    $user = $user_storage->create([
      'name' => $name,
      'mail' => $email,
      'status' => 1,
      'init' => $email,
      'pass' => \Drupal::service('password_generator')->generate(32),
    ]);
    $this->bindAndSync($user, $profile);
    return [$user, NULL];
  }

  /**
   * Bind linux.do id/username on user and sync optional fields.
   */
  protected function bindAndSync(UserInterface $user, array $profile): void {
    if ($user->hasField(self::FIELD_ID)) {
      $user->set(self::FIELD_ID, (int) $profile['id']);
    }
    $this->syncProfileFields($user, $profile);
  }

  /**
   * Sync mutable profile fields (username, avatar) onto the user.
   */
  protected function syncProfileFields(UserInterface $user, array $profile): void {
    if (!empty($profile['username']) && $user->hasField(self::FIELD_USERNAME)) {
      $user->set(self::FIELD_USERNAME, mb_substr($profile['username'], 0, 255));
    }

    // Avatar.
    if (!empty($profile['avatar_template']) && $user->hasField('user_picture')) {
      $avatar_url = $this->resolveAvatarUrl($profile['avatar_template']);
      $config = $this->getConfig();
      if (!empty($config['download_avatar'])) {
        if ($fid = $this->downloadAvatar($avatar_url, (int) $profile['id'])) {
          $user->set('user_picture', $fid);
        }
      }
    }
    $user->save();
  }

  /**
   * Replace {size} in linux.do avatar_template with a concrete pixel size.
   */
  public function resolveAvatarUrl(string $template, int $size = 120): string {
    return str_replace('{size}', (string) $size, $template);
  }

  /**
   * Download a remote avatar to public:// and return file id.
   */
  protected function downloadAvatar(string $url, int $linux_do_id): ?int {
    try {
      $contents = $this->httpClient->request('GET', $url, ['timeout' => 10])->getBody();
      $dir = 'public://linux_do_avatars';
      $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
      $destination = $dir . '/' . $linux_do_id . '.' . $ext;
      $file = $this->fileRepository->writeData((string) $contents, $destination, FileSystemInterface::EXISTS_REPLACE);
      return (int) $file->id();
    }
    catch (\Throwable $e) {
      $this->logger->warning('linux.do avatar download failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Pick a non-conflicting Drupal username.
   */
  protected function resolveUsername(string $candidate): string {
    $candidate = preg_replace('/[^a-zA-Z0-9_.\-]/', '_', trim($candidate));
    if ($candidate === '') {
      $candidate = 'linuxdo_user';
    }
    $user_storage = $this->entityTypeManager->getStorage('user');
    $name = $candidate;
    while ($user_storage->loadByProperties(['name' => $name])) {
      $name = $candidate . '_' . substr(Crypt::randomBytesBase64(4), 0, 4);
    }
    return $name;
  }

}
