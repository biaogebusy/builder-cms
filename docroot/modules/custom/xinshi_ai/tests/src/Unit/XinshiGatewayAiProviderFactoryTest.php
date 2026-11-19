<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInput;
use Drupal\ai_provider_openai\OpenAiHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;
use Drupal\key\KeyRepositoryInterface;
use Drupal\xinshi_ai\Exception\ImageSafetyException;
use Drupal\xinshi_ai\Plugin\AiProvider\XinshiGatewayAiProvider;
use Drupal\xinshi_ai\Service\ApiKeyVault;
use Drupal\xinshi_ai\Service\ImageFileValidator;
use Drupal\xinshi_ai\Service\ImageHttpClientFactory;
use Drupal\xinshi_ai\Service\ProviderImageFiles;
use Drupal\xinshi_ai\Service\PublicUrlPolicy;
use Drupal\xinshi_ai\Service\SupplierConfiguration;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/** Exercises the actual factory and SDK with synthetic, network-free responses. */
final class XinshiGatewayAiProviderFactoryTest extends TestCase {

  private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC';

  protected function tearDown(): void {
    new Settings([]);
  }

  #[DataProvider('imageOperations')]
  public function testFactoryInitializesImageResultsAndGuardedTransport(bool $edit, bool $url): void {
    $requests = 0;
    $seenOptions = [];
    $data = $url ? ['url' => 'https://8.8.8.8/image.png'] : ['b64_json' => self::PNG];
    $responses = new MockHandler([
      new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'created' => 1, 'data' => [$data],
      ], JSON_THROW_ON_ERROR)),
      new Response(200, ['Content-Type' => 'image/png'], base64_decode(self::PNG)),
    ]);
    $handler = function (RequestInterface $request, array $options) use ($edit, $responses, &$requests, &$seenOptions): PromiseInterface {
      if (++$requests === 1) {
        $seenOptions = $options;
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/images/' . ($edit ? 'edits' : 'generations'), $request->getUri()->getPath());
        $this->assertSame('Bearer synthetic-supplier-key', $request->getHeaderLine('Authorization'));
      }
      else {
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/image.png', $request->getUri()->getPath());
        $this->assertFalse($request->hasHeader('Authorization'));
      }
      return $responses($request, $options);
    };
    $provider = $this->provider($handler);
    $output = $edit
      ? $provider->imageToImage($this->input(), 'fixture')
      : $provider->textToImage('Turn the fixture blue', 'fixture');
    $this->assertCount(1, $output->getNormalized());
    $this->assertSame(base64_decode(self::PNG), $output->getNormalized()[0]->getBinary());
    $this->assertSame('image/png', $output->getNormalized()[0]->getMimeType());
    $this->assertSame($url ? 2 : 1, $requests);
    $this->assertSame(23.0, $seenOptions['timeout']);
    $this->assertFalse($seenOptions['allow_redirects']);
    $this->assertTrue($seenOptions['verify']);
    $this->assertSame('', $seenOptions['proxy']);
    $this->assertArrayHasKey(CURLOPT_RESOLVE, $seenOptions['curl']);
  }

  public static function imageOperations(): array {
    return ['edit base64' => [TRUE, FALSE], 'edit URL' => [TRUE, TRUE], 'generate base64' => [FALSE, FALSE]];
  }

  public function testFactoryRetainsImageContentValidation(): void {
    $provider = $this->provider(new MockHandler([new Response(200, ['Content-Type' => 'application/json'],
      json_encode(['created' => 1, 'data' => [['b64_json' => base64_encode('not an image')]]], JSON_THROW_ON_ERROR))]));
    $this->expectException(ImageSafetyException::class);
    $provider->imageToImage($this->input(), 'fixture');
  }

  #[DataProvider('guardedOperations')]
  public function testFactoryRejectsPrivateGatewayBeforeDispatch(bool $edit): void {
    $provider = $this->provider(new MockHandler([function () {
      $this->fail('A private gateway must never reach the transport.');
    }]), 'http://127.0.0.1');
    $this->expectException(ImageSafetyException::class);
    if ($edit) {
      $provider->imageToImage($this->input(), 'fixture');
    }
    else {
      $provider->textToImage('Turn the fixture blue', 'fixture');
    }
  }

  public static function guardedOperations(): array {
    return ['edit' => [TRUE], 'generate' => [FALSE]];
  }

  private function input(): ImageToImageInput {
    $input = new ImageToImageInput(new ImageFile(base64_decode(self::PNG), 'image/png', 'input.png'));
    $input->setPrompt('Turn the fixture blue');
    return $input;
  }

  private function provider(callable $handler, string $baseUrl = 'https://8.8.8.8'): XinshiGatewayAiProvider {
    $storage = new MemoryStorage();
    $storage->write('xinshi_ai.settings', ['gateway' => ['base_url' => $baseUrl], 'moderation' => FALSE]);
    $dispatcher = new EventDispatcher();
    $config = new ConfigFactory($storage, $dispatcher, $this->createMock(TypedConfigManagerInterface::class));
    $settings = new Settings(['xinshi_ai.supplier_keys' => ['xinshi' => 'synthetic-supplier-key']]);
    $logger = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    $http = $this->createMock(ClientFactory::class);
    $http->method('fromOptions')->willReturn(new Client(['handler' => $handler]));
    $realFactory = new ImageHttpClientFactory(new PublicUrlPolicy());
    $imageHttp = $this->createMock(ImageHttpClientFactory::class);
    $imageHttp->method('create')->willReturnCallback(static function (array $options = [], int $limit = ImageHttpClientFactory::MAX_RESPONSE_BYTES) use ($realFactory, $handler): Client {
      $client = $realFactory->create($options, $limit);
      // Replace only the network handler; retain the production middleware.
      $client->getConfig('handler')->setHandler($handler);
      return $client;
    });
    $container = new ContainerBuilder();
    foreach ([
      'config.factory' => $config, 'logger.factory' => $logger, 'http_client_factory' => $http,
      'cache.default' => $this->createMock(CacheBackendInterface::class),
      'key.repository' => $this->createMock(KeyRepositoryInterface::class),
      'module_handler' => $this->createMock(ModuleHandlerInterface::class),
      'event_dispatcher' => $dispatcher, 'file_system' => $this->createMock(FileSystemInterface::class),
      'ai_provider_openai.helper' => $this->createMock(OpenAiHelper::class),
      'xinshi_ai.image_http' => $imageHttp,
      'xinshi_ai.provider_image_files' => new ProviderImageFiles($imageHttp, new ImageFileValidator()),
      'xinshi_ai.supplier_configuration' => new SupplierConfiguration($config,
        new ApiKeyVault(new KeyValueMemoryFactory(), $this->createMock(PrivateKey::class)), $settings),
    ] as $id => $service) {
      $container->set($id, $service);
    }
    return XinshiGatewayAiProvider::create($container, ['http_client_options' => ['timeout' => 23]],
      'xinshi', ['provider' => 'xinshi_ai']);
  }

}
