<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\xinshi_ai_usage\Form\PriceBookForm;
use Drupal\xinshi_ai_usage\Service\PriceBookService;
use Drupal\xinshi_ai_usage\Service\ProducerVault;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The price book form keys price books by the producer-registered site id,
 * refuses the unedited example rates, and can activate saved drafts.
 */
final class PriceBookFormTest extends TestCase {

  private const KIND = PriceBookService::KIND_SUPPLIER_CHAT;

  private Connection $database;
  private PriceBookService $priceBook;
  private ProducerVault $vault;
  private TimeInterface $time;
  private array $messages = [];
  private int $now = 1_700_000_000;

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('price_form_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'price_form_test');
    $this->database->schema()->createTable(PriceBookService::TABLE, xinshi_ai_usage_schema()[PriceBookService::TABLE]);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn() => $this->now + 0.25);
    $this->time = $time;
    $this->priceBook = new PriceBookService($this->database, $time);
    // Settings is a static singleton the vault reads the hash salt from; initialise it
    // here so every test is self-contained regardless of run order.
    new Settings(['hash_salt' => 'isolated-hash-salt']);
    $privateKey = $this->createMock(PrivateKey::class);
    $privateKey->method('get')->willReturn('isolated-site-private-key');
    $this->vault = new ProducerVault(new KeyValueMemoryFactory(), $privateKey);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(
      fn(TranslatableMarkup $markup) => $markup->getUntranslatedString());
    $messenger = $this->createMock(MessengerInterface::class);
    foreach (['addStatus', 'addWarning', 'addError'] as $method) {
      $messenger->method($method)->willReturnCallback(function ($message) use ($method, $messenger) {
        $this->messages[] = [$method, (string) $message];
        return $messenger;
      });
    }
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(1);
    $container = new ContainerBuilder();
    $container->set('string_translation', $translation);
    $container->set('messenger', $messenger);
    $container->set('current_user', $account);
    \Drupal::setContainer($container);
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    Database::removeConnection('price_form_test');
    parent::tearDown();
  }

  public function testSiteIdsComeFromRegisteredProducersNotTheCmsHost(): void {
    // No producer yet: the CMS host is a documented fallback and the form says so.
    $form = $this->form()->buildForm([], new FormState());
    $this->assertSame(['cms.example'], array_keys($form['create']['site_id']['#options']));
    $this->assertArrayHasKey('unregistered', $form['overview']);

    // Producers registered with the frontend host: that is what events carry.
    $this->vault->set('chat-node', 'secret', 'app.example', $this->now);
    $this->vault->set('image-node', 'secret', 'app.example', $this->now);
    $form = $this->form()->buildForm([], new FormState());
    $this->assertSame(['app.example'], array_keys($form['create']['site_id']['#options']));
    $this->assertArrayNotHasKey('unregistered', $form['overview']);

    // settings.php producers are included; an explicit site id setting wins over all.
    $form = $this->form(['xinshi_ai_usage.producers' => ['deploy' => ['secret' => 'x', 'site_id' => 'deploy.example']]])
      ->buildForm([], new FormState());
    $this->assertSame(['deploy.example', 'app.example'], array_keys($form['create']['site_id']['#options']));
    $form = $this->form(['xinshi_ai_usage.site_id' => 'fixed.example'])->buildForm([], new FormState());
    $this->assertSame(['fixed.example'], array_keys($form['create']['site_id']['#options']));
  }

  public function testUneditedExampleRatesAreRefused(): void {
    $this->vault->set('chat-node', 'secret', 'app.example', $this->now);
    $form = $this->form();
    $built = $form->buildForm([], new FormState());
    $state = (new FormState())->setValues([
      'site_id' => 'app.example', 'version' => 'v1', 'currency' => 'CNY',
      'rates_json' => $built['create']['rates_json']['#default_value'],
    ]);
    $form->validateForm($built, $state);
    $this->assertArrayHasKey('rates_json', $state->getErrors());
    $this->assertStringContainsString('示例', (string) $state->getErrors()['rates_json']);

    // Editing the example (even to explicit zeros) is accepted.
    $rates = PriceBookForm::seedRates();
    $rates['accounts']['xinshi']['models'] = ['free-model' => ['per_million_input' => 0,
      'per_million_cache_read' => 0, 'per_million_cache_write' => 0, 'per_million_output' => 0]];
    $state = (new FormState())->setValues(['site_id' => 'app.example', 'version' => 'v1',
      'currency' => 'CNY', 'rates_json' => json_encode($rates)]);
    $form->validateForm($built, $state);
    $this->assertSame([], $state->getErrors());
  }

  public function testDraftsAreListedAndCanBeActivatedLater(): void {
    $this->vault->set('chat-node', 'secret', 'app.example', $this->now);
    $form = $this->form();
    $built = $form->buildForm([], new FormState());
    $rates = PriceBookForm::seedRates();
    $rates['accounts']['xinshi']['models']['real-model'] = $rates['accounts']['xinshi']['models']['example-model'];
    unset($rates['accounts']['xinshi']['models']['example-model']);
    $state = (new FormState())->setValues(['site_id' => 'app.example', 'version' => 'v1',
      'currency' => 'CNY', 'rates_json' => json_encode($rates), 'source_ref' => 'quote-1']);
    $state->setTriggeringElement(['#name' => 'save_draft']);
    $form->submitForm($built, $state);
    $this->assertSame('addStatus', end($this->messages)[0]);
    $this->assertNull($this->priceBook->loadActive('app.example', self::KIND));

    // The list shows the draft with an activate button.
    $built = $form->buildForm([], new FormState());
    $versions = $this->priceBook->listVersions('app.example', self::KIND);
    $this->assertCount(1, $versions);
    $row = $built['versions']['table']['app.example:' . $versions[0]['id']];
    $this->assertSame('草稿', $row['status']['#plain_text']);
    $this->assertSame('submit', $row['activate']['#type']);

    $activate = new FormState();
    $activate->setTriggeringElement($row['activate']);
    $form->activateVersion($built, $activate);
    $this->assertSame('v1', $this->priceBook->loadActive('app.example', self::KIND)['version']);

    // Once active, the row no longer offers activation.
    $built = $form->buildForm([], new FormState());
    $row = $built['versions']['table']['app.example:' . $versions[0]['id']];
    $this->assertSame('生效中', $row['status']['#plain_text']);
    $this->assertArrayNotHasKey('#type', $row['activate']);

    // A site that is not registered cannot receive a price book.
    $state = (new FormState())->setValues(['site_id' => 'other.example', 'version' => 'v2',
      'currency' => 'CNY', 'rates_json' => json_encode($rates)]);
    $state->setTriggeringElement(['#name' => 'save_draft']);
    $form->submitForm($built, $state);
    $this->assertSame('addError', end($this->messages)[0]);
  }

  private function form(array $settings = []): PriceBookForm {
    $requests = new RequestStack();
    $requests->push(Request::create('https://cms.example/admin/config/xinshi/ai/usage/pricing'));
    return new PriceBookForm($this->priceBook, $this->vault, $requests,
      new Settings($settings + ['hash_salt' => 'isolated-hash-salt']), $this->time);
  }

}
