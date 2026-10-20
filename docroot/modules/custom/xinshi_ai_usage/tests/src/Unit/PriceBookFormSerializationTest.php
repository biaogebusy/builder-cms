<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\Time;
use Drupal\Component\DependencyInjection\ReverseContainer;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Cache\MemoryCache\MemoryCache;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\State;
use Drupal\xinshi_ai_usage\Form\PriceBookForm;
use Drupal\xinshi_ai_usage\Service\LitellmPriceCatalog;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use Drupal\xinshi_ai_usage\Service\ReportedModels;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The price book form survives the form-state cache cycle.
 *
 * The prefill button rebuilds the form, which caches it in cache_form: the
 * serialized form state carries the form object, and
 * DependencySerializationTrait (running in FormBase scope) must strip and
 * restore the injected services. With private child properties the services
 * were silently dropped and the next request crashed with "must not be
 * accessed before initialization" in validateForm().
 */
final class PriceBookFormSerializationTest extends TestCase {

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('price_form_serialization_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $database = Database::getConnection('default', 'price_form_serialization_test');
    $settings = new Settings(['hash_salt' => 'isolated-hash-salt']);
    $requests = new RequestStack();
    $time = new Time($requests);
    $container = new ContainerBuilder();
    $container->set('xinshi_ai_usage.price_book', new PriceBookService($database, $time));
    $container->set('xinshi_ai_usage.price_catalog', new LitellmPriceCatalog(
      new Client(['handler' => new MockHandler()]), new MemoryBackend($time), $settings, $time));
    $container->set('xinshi_ai_usage.producer_vault', new ProducerVault(
      new KeyValueMemoryFactory(),
      new PrivateKey(new State(new KeyValueMemoryFactory(), new MemoryCache($time), new NullLockBackend()))));
    $container->set('xinshi_ai_usage.reported_models', new ReportedModels($database));
    $container->set('settings', $settings);
    $container->set('datetime.time', $time);
    $container->set('request_stack', $requests);
    $container->set(ReverseContainer::class, new ReverseContainer($container));
    $container->set('string_translation', new class {

      public function translateString($markup) {
        return $markup->getUntranslatedString();
      }

    });
    \Drupal::setContainer($container);
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    Database::removeConnection('price_form_serialization_test');
    parent::tearDown();
  }

  public function testValidateFormRunsOnTheRestoredCacheCopy(): void {
    $form = PriceBookForm::create(\Drupal::getContainer());

    // The cache write strips the services and records their IDs.
    $payload = serialize($form);
    $this->assertStringContainsString('"priceBook";s:26:"xinshi_ai_usage.price_book"', $payload,
      '__sleep() must record every injected service for the wakeup restore');

    // The cache read: unserialize must restore the services, so the exact
    // production call path keeps working.
    $restored = unserialize($payload);
    $this->validate($restored);

    // A later rebuild caches the restored object again; the cycle repeats.
    $this->validate(unserialize(serialize($restored)));
  }

  private function validate(PriceBookForm $form): void {
    $state = (new FormState())->setValues([
      'book_kind' => PriceBookService::KIND_SUPPLIER_CHAT,
      'rates_json' => '{"accounts":{"xinshi":{"models":{"m":{"per_million_input":1,'
        . '"per_million_cache_read":1,"per_million_cache_write":1,"per_million_output":1}}}}}',
    ]);
    $built = [];
    // Fails with "must not be accessed before initialization" before the fix.
    $form->validateForm($built, $state);
    $this->assertSame([], $state->getErrors());
  }

}
