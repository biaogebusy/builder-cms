<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Unit;

use Drupal\acquia_connector\Client;
use Drupal\acquia_connector\ConnectorException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @group acquia_connector
 */
final class EndOfLifeTest extends UnitTestCase {

  /**
   * Tests severity in status report.
   */
  public function testRequirements(): void {
    $container = new ContainerBuilder();
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $time = $this->createMock(TimeInterface::class);
    $time
      ->method('getRequestTime')
      ->willReturn(
        strtotime('Oct 12 2022'),
        strtotime('Nov 15 2022'),
        strtotime('Mar 01 2023'),
      );
    $state = $this->createMock(StateInterface::class);
    $state
      ->method('get')
      ->withConsecutive(
        ['acquia_connector.cron_last', 0],
      )
      ->willReturnOnConsecutiveCalls(time());
    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager
      ->method('getCurrentLanguage')
      ->willReturn(new Language(['id' => 'en']));
    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $unrouted_url_assembler = $this->createMock(UnroutedUrlAssemblerInterface::class);
    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $container->set('config.factory', $config_factory);
    $container->set('state', $state);
    $container->set('datetime.time', $time);
    $container->set('language_manager', $language_manager);
    $container->set('cache.default', new MemoryBackend());
    $container->set('url_generator', $url_generator);
    $container->set('unrouted_url_assembler', $unrouted_url_assembler);
    $container->set('module_handler', $module_handler);
    \Drupal::setContainer($container);

    require_once $this->root . '/core/includes/install.inc';
    require_once __DIR__ . '/../../../acquia_connector.module';
    require_once __DIR__ . '/../../../acquia_connector.install';

    $requirements = acquia_connector_requirements('runtime');
    self::assertArrayNotHasKey('acquia_3_x_eol', $requirements);
    $requirements = acquia_connector_requirements('runtime');
    self::assertArrayHasKey('acquia_3_x_eol', $requirements);
    self::assertEquals(REQUIREMENT_WARNING, $requirements['acquia_3_x_eol']['severity']);
    $requirements = acquia_connector_requirements('runtime');
    self::assertArrayHasKey('acquia_3_x_eol', $requirements);
    self::assertEquals(REQUIREMENT_ERROR, $requirements['acquia_3_x_eol']['severity']);
  }

  /**
   * Tests Client::nspiCall shutoff.
   */
  public function testClientNspiCall(): void {
    $client = new GuzzleClient([
      'handler' => HandlerStack::create(new MockHandler([
        new Response(200, [], Json::encode(['result' => []])),
        new Response(200, [], Json::encode(['result' => []])),
      ])),
    ]);
    $http_client_factory = $this->createMock(ClientFactory::class);
    $http_client_factory
      ->method('fromOptions')
      ->willReturn($client);

    $state = $this->createMock(StateInterface::class);
    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager
      ->method('getCurrentLanguage')
      ->willReturn(new Language(['id' => 'en']));
    $request_stack = new RequestStack();
    $request_stack->push(Request::create('/'));
    $container = new ContainerBuilder();
    $container->set('http_client_factory', $http_client_factory);
    $container->set('language_manager', $language_manager);
    $container->set('state', $state);
    $container->set('request_stack', $request_stack);
    \Drupal::setContainer($container);

    require_once __DIR__ . '/../../../acquia_connector.module';

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory
      ->method('get')
      ->with('acquia_connector.settings')
      ->willReturn($this->createMock(Config::class));
    $time = $this->createMock(TimeInterface::class);
    $time
      ->method('getRequestTime')
      ->willReturn(
        strtotime('Oct 12 2022'),
        strtotime('Oct 12 2022'),
        strtotime('Nov 15 2022'),
        strtotime('Nov 15 2022'),
        strtotime('Mar 01 2023'),
      );

    $client = new Client(
      $config_factory,
      $state,
      $time
    );
    $data = $client->nspiCall('/agent-api/subscription', [], 'ABC');
    self::assertArrayHasKey('result', $data);
    self::assertNotFalse($data['result']);
    $data = $client->nspiCall('/agent-api/subscription', [], 'ABC');
    self::assertArrayHasKey('result', $data);
    self::assertNotFalse($data['result']);

    $this->expectException(ConnectorException::class);
    $this->expectExceptionMessage('Acquia Connector 3.x has reached end of life, please upgrade to 4.x to continue using it.');
    $client->nspiCall('/agent-api/subscription', [], 'ABC');
  }

}
