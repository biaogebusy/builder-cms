<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Tests\acquia_connector\Kernel\AcquiaConnectorTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\acquia_connector\Controller\AuthController
 * @group acquia_connector
 */
final class AuthControllerTest extends AcquiaConnectorTestBase implements LoggerInterface {

  use UserCreationTrait;
  use StringTranslationTrait;
  use RfcLoggerTrait;

  /**
   * Tracks logs during the test.
   *
   * @var string[]
   */
  private $logs = [];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $container
      ->register('testing.acquia_conector_logger', self::class)
      ->addTag('logger');
    $container->set('testing.acquia_conector_logger', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    $message_placeholders = $this->container
      ->get('logger.log_message_parser')
      ->parseMessagePlaceholders($message, $context);
    $message = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);

    $entry = strtr('!severity|!type|!message', [
      '!type' => $context['channel'],
      '!request_uri' => $context['request_uri'],
      '!severity' => $level,
      '!uid' => $context['uid'],
      '!message' => strip_tags($message),
    ]);
    $this->logs[] = $entry;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createUserWithSession();
  }

  /**
   * Tests the ::setup method with an API key.
   */
  public function testSetupWithApiKeys(): void {
    $request = Request::create(
      Url::fromRoute('acquia_connector.setup_oauth')->toString()
    );
    $response = $this->doRequest($request);
    self::assertEquals(200, $response->getStatusCode());
    // @note: cannot use generated URL to due generated CSRF token.
    $this->assertStringNotContainsString(
      $this->getCsrfUrlString(Url::fromRoute('acquia_connector.auth.begin')),
      $this->getRawContent()
    );
    $this->assertStringContainsString(
      Url::fromRoute('acquia_connector.setup_manual')->toString(),
      $this->getRawContent()
    );

    // Submit the form
    $request = Request::create(
      Url::fromRoute('acquia_connector.setup_oauth')->toString(),
      'POST',
      [
        'api_key' => 'VALID_KEY',
        'api_secret' => 'VALID_SECRET',
        // @phpstan-ignore-next-line
        'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
        // @phpstan-ignore-next-line
        'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
        // @phpstan-ignore-next-line
        'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
        'op' => 'Connect',
      ]);
    $response = $this->doRequest($request);
    self::assertEquals(303, $response->getStatusCode(), var_export($response->getContent(), TRUE));
    self::assertEquals(
      Url::fromRoute('acquia_connector.setup_configure')->setAbsolute()->toString(),
      $response->headers->get('Location')
    );
    // Ensure keys are being stored locally.
    $state = $this->container->get('state');
    $this->assertEquals('{"api_key":"VALID_KEY","api_secret":"VALID_SECRET"}', $state->get('acquia_connector.credentials', ''));
  }

}
