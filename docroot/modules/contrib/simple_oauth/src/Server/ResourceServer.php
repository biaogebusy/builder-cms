<?php

namespace Drupal\simple_oauth\Server;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\ResourceServer as LeagueResourceServer;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The resource server.
 */
class ResourceServer implements ResourceServerInterface {

  /**
   * The decorated resource server.
   *
   * @var \League\OAuth2\Server\ResourceServer|null
   */
  protected ?LeagueResourceServer $subject;

  /**
   * The message factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected HttpMessageFactoryInterface $messageFactory;

  /**
   * The HTTP foundation factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface
   */
  protected HttpFoundationFactoryInterface $foundationFactory;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Access Token Repository.
   *
   * @var \League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface
   */
  protected AccessTokenRepositoryInterface $accessTokenRepository;

  /**
   * ResourceServer constructor.
   */
  public function __construct(
    AccessTokenRepositoryInterface $access_token_repository,
    ConfigFactoryInterface $config_factory,
    HttpMessageFactoryInterface $message_factory,
    HttpFoundationFactoryInterface $foundation_factory
  ) {
    $this->accessTokenRepository = $access_token_repository;
    $this->configFactory = $config_factory;
    $this->messageFactory = $message_factory;
    $this->foundationFactory = $foundation_factory;
  }

  /**
   * Lazy loads the file system.
   *
   * @return \Drupal\Core\File\FileSystemInterface
   *   The file system service.
   */
  protected function fileSystem(): FileSystemInterface {
    if (!isset($this->fileSystem)) {
      $this->fileSystem = \Drupal::service('file_system');
    }
    return $this->fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public function validateAuthenticatedRequest(Request $request): Request {
    // We don't initialize the LeagueResourceServer in the constructor, due the
    // fact that the ResourceServer is being included on every page load.
    $this->initializeSubject();
    assert($this->subject instanceof LeagueResourceServer);
    // Create a PSR-7 message from the request that is compatible with the OAuth
    // library.
    $psr7_request = $this->messageFactory->createRequest($request);
    // Augment the request with the access token's decoded data or throw an
    // exception if authentication is unsuccessful.
    $output_psr7_request = $this
      ->subject
      ->validateAuthenticatedRequest($psr7_request);

    // Convert back to the Drupal/Symfony HttpFoundation objects.
    return $this->foundationFactory->createRequest($output_psr7_request);
  }

  /**
   * Initialize the subject (LeagueResourceServer).
   */
  protected function initializeSubject(): void {
    if (!isset($this->subject)) {
      try {
        $public_key = $this->configFactory->get('simple_oauth.settings')
          ->get('public_key');
        $public_key_real = $public_key ? $this->fileSystem()->realpath($public_key) : NULL;
        if ($public_key_real) {
          // Initialize the crypto key, optionally disabling the permissions
          // check.
          $crypt_key = new CryptKey(
            $public_key_real,
            NULL,
            Settings::get('simple_oauth.key_permissions_check', TRUE)
          );
          $this->subject = new LeagueResourceServer(
            $this->accessTokenRepository,
            $crypt_key
          );
        }
      }
      catch (\LogicException $exception) {
        trigger_error($exception, E_USER_WARNING);
      }
    }
  }

}
