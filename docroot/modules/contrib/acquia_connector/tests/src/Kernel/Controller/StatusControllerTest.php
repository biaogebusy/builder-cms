<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel\Controller;

use Drupal\acquia_connector\Controller\StatusController;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\Php as PhpUuid;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Url;
use Drupal\Tests\acquia_connector\Kernel\AcquiaConnectorTestBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @coversDefaultClass \Drupal\acquia_connector\Controller\StatusController
 * @group acquia_connector
 */
final class StatusControllerTest extends AcquiaConnectorTestBase {

  /**
   * Tests the refresh route.
   */
  public function testRefresh(): void {
    $this->createUserWithSession();

    $url = $this->getCsrfUrlString(Url::fromRoute('acquia_connector.refresh_status'));
    $request = Request::create($url);
    $response = $this->doRequest($request);
    self::assertEquals(302, $response->getStatusCode());
    self::assertEquals(
      Url::fromRoute('system.status')->setAbsolute()->toString(),
      $response->headers->get('Location')
    );
  }

  /**
   * Tests status route.
   *
   * @param bool $with_page_cache
   *   Test with page_cache installed or not.
   *
   * @dataProvider withPageCache
   */
  public function testJson(bool $with_page_cache): void {
    if ($with_page_cache) {
      $this->container->get('module_installer')->install(['page_cache']);
    }
    $uuid = (new PhpUuid())->generate();
    $state = $this->container->get('state');
    $state->set('acquia_connector.key', $this->randomMachineName());
    $state->set('acquia_connector.identifier', 'ABC-1234');
    $state->set('acquia_connector.application_uuid', $uuid);

    $url = Url::fromRoute('acquia_connector.status', [], [
      'query' => [
        'nonce' => 'f00bar',
        'key' => hash('sha1', "$uuid:f00bar"),
      ],
    ]);
    $request = Request::create($url->toString());
    $response = $this->doRequest($request);
    self::assertInstanceOf(JsonResponse::class, $response);
    self::assertEquals(
      [
        'version' => '1.0',
        'data' => [
          'maintenance_mode' => FALSE,
          'cache' => $with_page_cache,
          'block_cache' => FALSE,
        ],
      ],
      Json::decode((string) $response->getContent())
    );
    self::assertEquals('must-revalidate, no-cache, private', $response->headers->get('Cache-Control'));
  }

  /**
   * Data for testing the status response.
   *
   * @return \Generator
   *   The test data.
   */
  public static function withPageCache() {
    yield 'page_cache installed' => [TRUE];
    yield 'page_cache uninstalled' => [FALSE];
  }

  /**
   * Tests the access method.
   *
   * @dataProvider accessData
   */
  public function testAccess(string $uuid, string $nonce, string $key, AccessResultInterface $result): void {
    $state = $this->container->get('state');
    $state->set('acquia_connector.key', $this->randomMachineName());
    $state->set('acquia_connector.identifier', 'ABC-1234');
    $state->set('acquia_connector.application_uuid', $uuid);

    $url = Url::fromRoute('acquia_connector.status', [], [
      'query' => [
        'nonce' => $nonce,
        'key' => $key,
      ],
    ]);
    $request = Request::create($url->toString());
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
    $sut = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(StatusController::class);
    assert($sut instanceof StatusController);
    self::assertEquals($result, $sut->access());
  }

  /**
   * Data for access check test.
   *
   * @return \Generator
   *   The test data.
   */
  public static function accessData() {
    $uuid = (new PhpUuid())->generate();
    // phpcs:disable
    yield 'missing nonce' => [$uuid, '', '', AccessResult::forbidden('Missing nonce.')];
    yield 'missing uuid' => ['', 'f00Bar', '', AccessResult::forbidden('Missing application UUID.')];
    yield 'missing key' => [$uuid, 'f00Bar', '', AccessResult::forbidden('Could not validate key.')];
    yield 'invalid key' => [$uuid, 'f00Bar', 'ddsdfdsfdsdf', AccessResult::forbidden('Could not validate key.')];
    yield 'okay' => [$uuid, 'f00Bar', hash('sha1', "$uuid:f00Bar"), AccessResult::allowed()];
    // phpcs:enable
  }

}
