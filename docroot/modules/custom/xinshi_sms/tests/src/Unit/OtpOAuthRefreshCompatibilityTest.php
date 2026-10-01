<?php

namespace Drupal\Tests\xinshi_sms\Unit;

use Defuse\Crypto\Crypto;
use Drupal\Component\Uuid\Php as Uuid;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Password\PhpPassword;
use Drupal\Core\Site\Settings;
use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Entities\RefreshTokenEntity;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Oauth2ScopeProviderInterface;
use Drupal\simple_oauth\Plugin\Oauth2Grant\RefreshToken;
use Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface;
use Drupal\simple_oauth\Repositories\ClientRepository;
use Drupal\simple_oauth\Repositories\ScopeRepository;
use Drupal\simple_oauth\Server\AuthorizationServerFactory;
use Drupal\user\UserInterface;
use Drupal\xinshi_sms\Otp;
use Drupal\xinshi_sms\Plugin\rest\resource\OtpSubmitResource;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Checks real OTP encryption against the installed OAuth factory and grant.
 *
 * Consumer/user fields and token storage are in-memory fixtures. Access JWTs
 * use League's signing traits; Drupal JWT claims and database hooks are outside
 * this test. No real account, site salt, key, SMS or session is used.
 */
final class OtpOAuthRefreshCompatibilityTest extends TestCase {

  private static string $privateKeyPath;
  private const SALT = '0123456789abcdef0123456789abcdefABCDEFGHIJKLMNOPQRSTUVWXYZabcdefgh';
  private AuthorizationServerFactory $factory;
  private Consumer $consumer;
  private OtpSubmitResource $issuer;
  private array $accessTokens = [];
  private array $refreshTokens = [];
  private array $revokedAccess = [];
  private array $revokedRefresh = [];
  private bool $confidential = FALSE;
  private array $scopeIds = ['read', 'write'];

  public static function setUpBeforeClass(): void {
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $pem);
    self::$privateKeyPath = tempnam(sys_get_temp_dir(), 'otp-oauth-test-');
    file_put_contents(self::$privateKeyPath, $pem);
    chmod(self::$privateKeyPath, 0600);
  }

  public static function tearDownAfterClass(): void {
    unlink(self::$privateKeyPath);
  }

  protected function setUp(): void {
    new Settings(['hash_salt' => self::SALT]);
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
    $modules = $this->createMock(ModuleHandlerInterface::class);
    $modules->method('moduleExists')->with('simple_oauth')->willReturn(TRUE);
    $this->issuer = new class([], 'xinshi_otp_login', [], ['json'], new NullLogger()) extends OtpSubmitResource {
      public function initialize(ModuleHandlerInterface $modules): void { $this->moduleHandler = $modules; }
      public function issue(array $input): array { return $this->generateOAuthToken($input); }
    };
    $this->issuer->initialize($modules);
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn('7');
    $user->method('isActive')->willReturn(TRUE);
    $otp = $this->createMock(Otp::class);
    $otp->method('otpLoginCheckUserAlreadyExists')->willReturn($user);
    $container->set('xinshi_sms.OTP', $otp);
    $passwords = new PhpPassword();
    $secret_hash = $passwords->hash('fixture-secret');
    $this->consumer = $this->createMock(Consumer::class);
    $this->consumer->method('getClientId')->willReturn('fixture-client');
    $this->consumer->method('label')->willReturn('Test consumer');
    $this->consumer->method('get')->willReturnCallback(fn($name) => match ($name) {
      'confidential' => $this->field($this->confidential),
      'secret' => $this->field($this->confidential ? $secret_hash : NULL),
      'access_token_expiration' => $this->field(600),
      'refresh_token_expiration' => $this->field(3600),
      'grant_types' => $this->field(NULL, [['value' => 'refresh_token']]),
      'scopes' => $this->field(NULL, array_map(fn($id) => ['scope_id' => $id], $this->scopeIds)),
      default => $this->field(NULL),
    });
    $consumers = $this->createMock(EntityStorageInterface::class);
    $consumers->method('loadByProperties')->willReturnCallback(fn($values) => $values['client_id'] === 'fixture-client' ? [$this->consumer] : []);
    $users = $this->createMock(EntityStorageInterface::class);
    $users->method('load')->with('7')->willReturn($user);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([['consumer', $consumers], ['user', $users]]);
    $clients = new ClientRepository($entities, $passwords);
    $provider = $this->createMock(Oauth2ScopeProviderInterface::class);
    $provider->method('loadByName')->willReturnCallback(function ($id) {
      if (!in_array($id, ['read', 'write', 'admin'], TRUE)) { return NULL; }
      $scope = $this->createMockForIntersectionOfInterfaces([Oauth2ScopeInterface::class, CacheableDependencyInterface::class]);
      $scope->method('getName')->willReturn($id);
      $scope->method('isGrantTypeEnabled')->with('refresh_token')->willReturn(TRUE);
      $scope->method('getCacheTags')->willReturn([]);
      $scope->method('getCacheContexts')->willReturn([]);
      $scope->method('getCacheMaxAge')->willReturn(-1);
      return $scope;
    });
    $scopes = new ScopeRepository($entities, $provider);
    $access = $this->createMock(AccessTokenRepositoryInterface::class);
    $access->method('getNewToken')->willReturnCallback(function ($client, $scopes, $uid) {
      $token = new RefreshCompatibilityAccessToken();
      $token->setClient($client);
      $token->setUserIdentifier($uid);
      foreach ($scopes as $scope) { $token->addScope($scope); }
      return $token;
    });
    $access->method('persistNewAccessToken')->willReturnCallback(function ($token) {
      $this->accessTokens[$token->getIdentifier()] = $token;
    });
    $access->method('revokeAccessToken')->willReturnCallback(function ($id) { $this->revokedAccess[$id] = TRUE; });
    $refresh = $this->createMock(RefreshTokenRepositoryInterface::class);
    $refresh->method('getNewRefreshToken')->willReturnCallback(fn() => new RefreshTokenEntity());
    $refresh->method('persistNewRefreshToken')->willReturnCallback(function ($token) {
      $this->refreshTokens[$token->getIdentifier()] = $token;
    });
    $refresh->method('revokeRefreshToken')->willReturnCallback(function ($id) { $this->revokedRefresh[$id] = TRUE; });
    $refresh->method('isRefreshTokenRevoked')->willReturnCallback(fn($id) => !isset($this->refreshTokens[$id]) || isset($this->revokedRefresh[$id]));
    foreach (['client' => $clients, 'scope' => $scopes, 'access_token' => $access, 'refresh_token' => $refresh] as $name => $repository) {
      $container->set('simple_oauth.repositories.' . $name, $repository);
    }
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnMap([
      ['private_key', self::$privateKeyPath], ['access_token_expiration', 600], ['refresh_token_expiration', 3600],
    ]);
    $configs = $this->createMock(ConfigFactoryInterface::class);
    $configs->method('get')->with('simple_oauth.settings')->willReturn($settings);
    $files = $this->createMock(FileSystemInterface::class);
    $files->method('realpath')->willReturnArgument(0);
    $container->set('config.factory', $configs);
    $container->set('file_system', $files);
    $container->set('uuid', new Uuid());
    $grants = $this->createMock(Oauth2GrantManagerInterface::class);
    $grants->method('createInstance')->with('refresh_token')->willReturnCallback(fn() => new RefreshToken([], 'refresh_token', [], $refresh));
    // Exercise get(), getSalt() and getPrivateKey() unchanged from Simple OAuth.
    $this->factory = new AuthorizationServerFactory($configs, $files, $grants, $clients, $scopes, $access, $refresh, NULL);
  }

  protected function tearDown(): void {
    new Settings([]);
    \Drupal::unsetContainer();
  }

  private function field(mixed $value, array $items = []): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->willReturnMap([['value', $value], ['isEmpty', $value === NULL && !$items]]);
    $field->method('getValue')->willReturn($items);
    $field->method('isEmpty')->willReturn($value === NULL && !$items);
    return $field;
  }

  private function issue(): array {
    return $this->issuer->issue(['client_id' => 'fixture-client', 'mobile_number' => '13800000000']
      + ($this->confidential ? ['client_secret' => 'fixture-secret'] : []));
  }

  private function refresh(string $token, array $parameters = []): array {
    $request = (new ServerRequest('POST', 'https://cms.example.test/oauth/token'))->withParsedBody($parameters + [
      'grant_type' => 'refresh_token', 'client_id' => 'fixture-client', 'refresh_token' => $token,
    ] + ($this->confidential ? ['client_secret' => 'fixture-secret'] : []));
    $response = $this->factory->get($this->consumer)->respondToAccessTokenRequest($request, new Response());
    $this->assertSame(200, $response->getStatusCode());
    return json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  private function payload(string $token): array {
    return json_decode(Crypto::decryptWithPassword($token, substr(Settings::getHashSalt(), 0, 32)), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  public static function compatibleClients(): array {
    return [[32, FALSE], [64, FALSE], [32, TRUE], [64, TRUE]];
  }

  #[DataProvider('compatibleClients')]
  public function testOtpTokensRefreshTwiceAndRotate(int $salt_length, bool $confidential): void {
    new Settings(['hash_salt' => substr(self::SALT, 0, $salt_length)]);
    $this->confidential = $confidential;
    $token = $this->issue();
    $this->assertSame('Bearer', $token['token_type']);
    $this->assertSame(600, $token['expires_in']);
    foreach ([1, 2] as $iteration) {
      $old = $this->payload($token['refresh_token']);
      $next = $this->refresh($token['refresh_token']);
      $payload = $this->payload($next['refresh_token']);
      $this->assertSame('7', $payload['user_id']);
      $this->assertSame('fixture-client', $payload['client_id']);
      $this->assertSame(['read', 'write'], $payload['scopes']);
      $this->assertNotSame($token['access_token'], $next['access_token']);
      $this->assertNotSame($old['refresh_token_id'], $payload['refresh_token_id']);
      $this->assertTrue($this->revokedAccess[$old['access_token_id']]);
      $this->assertTrue($this->revokedRefresh[$old['refresh_token_id']]);
      $token = $next;
    }
    $this->assertCount(3, $this->accessTokens);
    $this->assertCount(3, $this->refreshTokens);
  }

  public function testEmptyScopesAndScopeReductionRemainCompatible(): void {
    $token = $this->issue();
    $reduced = $this->refresh($token['refresh_token'], ['scope' => 'read']);
    $this->assertSame(['read'], $this->payload($reduced['refresh_token'])['scopes']);
    $this->scopeIds = [];
    $empty = $this->refresh($this->issue()['refresh_token']);
    $this->assertSame([], $this->payload($empty['refresh_token'])['scopes']);
  }

  public static function rejectedRefreshes(): array {
    return [['expired', 'invalid_grant'], ['revoked', 'invalid_grant'], ['reused', 'invalid_grant'],
      ['full-salt', 'invalid_grant'], ['scope-expansion', 'invalid_scope'], ['wrong-secret', 'invalid_client']];
  }

  #[DataProvider('rejectedRefreshes')]
  public function testInvalidRefreshDoesNotWriteTokens(string $case, string $error): void {
    $this->confidential = $case === 'wrong-secret';
    $token = $this->issue()['refresh_token'];
    $payload = $this->payload($token);
    $parameters = [];
    if ($case === 'expired') {
      $payload['expire_time'] = time() - 1;
      $token = Crypto::encryptWithPassword(json_encode($payload), substr(self::SALT, 0, 32));
    }
    elseif ($case === 'revoked') { $this->revokedRefresh[$payload['refresh_token_id']] = TRUE; }
    elseif ($case === 'reused') { $this->refresh($token); }
    elseif ($case === 'full-salt') {
      // The proposed full-salt "fix" would break the existing wire protocol.
      $token = Crypto::encryptWithPassword(json_encode($payload), self::SALT);
    }
    elseif ($case === 'scope-expansion') { $parameters['scope'] = 'admin'; }
    elseif ($case === 'wrong-secret') { $parameters['client_secret'] = 'wrong'; }
    $access_count = count($this->accessTokens);
    $refresh_count = count($this->refreshTokens);
    try {
      $this->refresh($token, $parameters);
      $this->fail('Invalid refresh was accepted.');
    }
    catch (OAuthServerException $exception) {
      $this->assertSame($error, $exception->getErrorType());
      $this->assertCount($access_count, $this->accessTokens);
      $this->assertCount($refresh_count, $this->refreshTokens);
    }
  }

}

/** Access JWT signing without Drupal URL generation or private-claim hooks. */
final class RefreshCompatibilityAccessToken implements AccessTokenEntityInterface {
  use AccessTokenTrait, EntityTrait, TokenEntityTrait;
}
