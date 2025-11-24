<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base test class for Acquia Connector.
 */
abstract class AcquiaConnectorTestBase extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Modified environment variables.
   *
   * @var array
   */
  private $modifiedEnv = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'acquia_connector',
    'acquia_connector_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    // Burn uid:1.
    $this->createUser();
    $this->enableModules($this::$modules);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->modifiedEnv as $key) {
      putenv("$key=");
    }
    parent::tearDown();
  }

  /**
   * Put an environment variable.
   *
   * @param string $key
   *   The key.
   * @param string|int $value
   *   The value.
   */
  protected function putEnv(string $key, $value): void {
    $this->modifiedEnv[] = $key;
    putenv("$key=$value");
  }

  /**
   * Creates a user, its session, and sets it as the current user.
   *
   * @return \Drupal\user\UserInterface
   *   The user.
   */
  protected function createUserWithSession(): UserInterface {
    $this->container->get('session_manager.metadata_bag')->stampNew();
    $user = $this->createUser(['administer site configuration']);
    self::assertNotFalse($user);
    $this->container->get('current_user')->setAccount($user);
    return $user;
  }

  /**
   * Passes a request to the HTTP kernel and returns a response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Exception
   */
  protected function doRequest(Request $request): Response {
    $response = $this->container->get('http_kernel')->handle($request);
    $content = $response->getContent();
    self::assertNotFalse($content);
    $this->setRawContent($content);
    return $response;
  }

  /**
   * Get the string URL for a CSRF protected route.
   *
   * @param \Drupal\Core\Url $url
   *   The URL.
   *
   * @return string
   *   The URL string.
   */
  protected function getCsrfUrlString(Url $url): string {
    $context = new RenderContext();
    $url = $this->container->get('renderer')->executeInRenderContext($context, function () use ($url) {
      return $url->toString();
    });
    $bubbleable_metadata = $context->pop();
    assert($bubbleable_metadata instanceof BubbleableMetadata);
    $build = [
      '#plain_text' => $url,
    ];
    $bubbleable_metadata->applyTo($build);
    return (string) $this->container->get('renderer')->renderPlain($build);
  }

  /**
   * Populates the oAuth settings with dummy data.
   *
   * @param array $data
   *   oAuth settings data.
   */
  protected function populateOauthSettings(array $data = []): void {
    if (!$data) {
      $data = [
        'access_token' => 'ACCESS_TOKEN',
      ];
    }

    $this->container
      ->get('keyvalue.expirable')
      ->get('acquia_connector')
      ->setWithExpire(
        'oauth',
        $data,
        5400
      );
  }

}
