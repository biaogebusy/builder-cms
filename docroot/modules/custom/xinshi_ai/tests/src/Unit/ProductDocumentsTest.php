<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Drupal\xinshi_ai\Service\HarnessSettings;
use Drupal\xinshi_ai\Service\ProductDocuments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductDocumentsTest extends TestCase {

  private ProductDocuments $documents;
  private array $settings;
  private array $nodes = [];
  private array $conditions = [];
  private string $revision = '88';
  private EntityStorageInterface $storage;
  private AccountInterface $account;
  private const ID = '12345678-1234-4234-8234-123456789abc';

  protected function setUp(): void {
    $this->settings = HarnessSettings::DEFAULTS;
    $this->settings['mcp']['product_documents'] = ['enabled' => TRUE, 'content_types' => ['product']];
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('harness')->willReturnCallback(fn() => $this->settings);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('xinshi_ai.settings')->willReturn($config);
    $this->account = $this->createMock(AccountInterface::class);
    $this->account->method('isAuthenticated')->willReturn(TRUE);
    $language = $this->createMock(LanguageManagerInterface::class);
    $language->method('getLanguage')->willReturnCallback(fn($id) => in_array($id, ['zh-hans', 'en']) ? new Language(['id' => $id]) : NULL);
    $types = $this->createMock(EntityStorageInterface::class);
    $product = $this->createMock(NodeTypeInterface::class);
    $product->method('id')->willReturn('product');
    $product->method('label')->willReturn('产品资料');
    $types->method('loadMultiple')->willReturn(['product' => $product]);
    $fields = $this->createMock(EntityFieldManagerInterface::class);
    $body = $this->createMock(FieldDefinitionInterface::class);
    $body->method('getType')->willReturn('text_with_summary');
    $fields->method('getFieldDefinitions')->with('node', 'product')->willReturn(['body' => $body]);
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(TRUE)->willReturnSelf();
    $query->method('condition')->willReturnCallback(function (...$args) use ($query) {
      $this->conditions[] = $args;
      return $query;
    });
    $or = $this->createMock(ConditionInterface::class);
    $or->method('condition')->willReturnSelf();
    $query->method('orConditionGroup')->willReturn($or);
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturnCallback(fn() => array_keys($this->nodes));
    $this->storage = $this->createMock(EntityStorageInterface::class);
    $this->storage->method('getQuery')->willReturn($query);
    $this->storage->method('loadMultiple')->willReturnCallback(fn($ids) => array_intersect_key($this->nodes, array_flip($ids)));
    $this->storage->method('loadByProperties')->willReturnCallback(fn() => $this->nodes);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnMap([['node', $this->storage], ['node_type', $types]]);
    $this->documents = new ProductDocuments($factory, $manager, $fields, $this->account, $language);
  }

  private function node(string $content, bool $published = TRUE, bool $entityAccess = TRUE,
    bool $bodyAccess = TRUE, bool $titleAccess = TRUE, bool $translated = TRUE): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('uuid')->willReturn(self::ID);
    $node->method('bundle')->willReturn('product');
    $node->method('label')->willReturn('智能客服');
    $node->method('hasTranslation')->willReturn($translated);
    $node->method('getTranslation')->willReturnSelf();
    $node->method('isPublished')->willReturn($published);
    $node->method('access')->with('view', $this->account)->willReturn($entityAccess);
    $node->method('hasField')->with('body')->willReturn(TRUE);
    $body = $this->createMock(FieldItemListInterface::class);
    $body->method('__get')->with('value')->willReturn($content);
    $body->method('access')->with('view', $this->account)->willReturn($bodyAccess);
    $title = $this->createMock(FieldItemListInterface::class);
    $title->method('access')->with('view', $this->account)->willReturn($titleAccess);
    $node->method('get')->willReturnMap([['body', $body], ['title', $title]]);
    $node->method('getChangedTime')->willReturn(1789260000);
    $node->method('getRevisionId')->willReturnCallback(fn() => $this->revision);
    $node->method('language')->willReturn(new Language(['id' => 'zh-hans']));
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('/zh-hans/node/17');
    $node->method('toUrl')->willReturn($url);
    return $node;
  }

  public function testSearchScopesPublicationLanguageBundleAndEntityAccess(): void {
    $this->nodes = [17 => $this->node('<p>智能客服产品说明</p>')];
    $result = $this->documents->call('search_product_documents', ['query' => '智能客服', 'language' => 'zh-hans']);
    $this->assertSame('智能客服产品说明', $result['documents'][0]['excerpt']);
    $this->assertSame('88', $result['documents'][0]['revisionId']);
    $this->assertContains(['type', ['product'], 'IN'], array_map(fn($condition) => array_slice($condition, 0, 3), $this->conditions));
    $this->assertContains(['status', 1, '=', 'zh-hans'], $this->conditions);
    $this->assertContains(['langcode', 'zh-hans'], array_map(fn($condition) => array_slice($condition, 0, 2), $this->conditions));
  }

  public static function inaccessible(): array {
    return [[FALSE, TRUE, TRUE, TRUE, TRUE], [TRUE, FALSE, TRUE, TRUE, TRUE],
      [TRUE, TRUE, FALSE, TRUE, TRUE], [TRUE, TRUE, TRUE, FALSE, TRUE], [TRUE, TRUE, TRUE, TRUE, FALSE]];
  }

  #[DataProvider('inaccessible')]
  public function testNeitherSearchNorReadLeaksUnavailableContent(bool ...$access): void {
    $this->nodes = [17 => $this->node('private-product-facts', ...$access)];
    $this->assertSame([], $this->documents->call('search_product_documents', ['query' => 'product', 'language' => 'zh-hans'])['documents']);
    $this->expectExceptionMessage('not_found');
    $this->documents->call('get_product_document', ['id' => self::ID, 'language' => 'zh-hans']);
  }

  public function testFullTextChunksKeepUnicodeAndBindTheRevision(): void {
    $body = str_repeat('资料', 9000);
    $this->nodes = [17 => $this->node('<p>' . $body . '</p><script>doNotRead()</script>')];
    $first = $this->documents->call('get_product_document', ['id' => self::ID, 'language' => 'zh-hans']);
    $this->assertSame(16000, $first['nextOffset']);
    $second = $this->documents->call('get_product_document', ['id' => self::ID,
      'language' => 'zh-hans', 'offset' => $first['nextOffset'], 'revision' => $first['document']['revisionId']]);
    $this->assertSame($body, $first['content'] . $second['content']);
    $this->assertNull($second['nextOffset']);
    $this->revision = '89';
    $this->expectExceptionMessage('changed');
    $this->documents->call('get_product_document', ['id' => self::ID, 'language' => 'zh-hans', 'offset' => 16000, 'revision' => '88']);
  }

  public function testDisabledOrUnselectedSourceHasNoTools(): void {
    $this->settings['mcp']['product_documents']['content_types'] = ['unknown_type'];
    $this->assertSame([], $this->documents->toolNames());
    $this->settings['mcp']['product_documents'] = ['enabled' => FALSE, 'content_types' => ['product']];
    $this->assertSame([], $this->documents->toolNames());
    $this->expectExceptionMessage('disabled');
    $this->documents->call('search_product_documents', ['query' => 'product', 'language' => 'zh-hans']);
  }

  public static function invalidCalls(): array {
    return [
      ['search_product_documents', ['query' => '']],
      ['search_product_documents', ['query' => 'product', 'page' => -1]],
      ['search_product_documents', ['query' => 'product', 'language' => 'unknown']],
      ['search_product_documents', ['query' => 'product', 'endpoint' => '/private']],
      ['get_product_document', ['id' => '17']],
      ['get_product_document', ['id' => self::ID, 'offset' => 1]],
    ];
  }

  #[DataProvider('invalidCalls')]
  public function testInvalidInputsDoNotQueryNodes(string $name, array $arguments): void {
    $this->storage->expects($this->never())->method('getQuery');
    $this->storage->expects($this->never())->method('loadByProperties');
    $this->expectException(\InvalidArgumentException::class);
    $this->documents->call($name, $arguments + ['language' => 'zh-hans']);
  }

}
