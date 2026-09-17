<?php

namespace Drupal\Tests\xinshi_api\Unit;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\xinshi_api\Controller\PanelsIPEPageController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Tests stored layout history through the actual controller and Section objects. */
final class LayoutHistoryTest extends TestCase {

  private const UUID_A = '11111111-1111-4111-8111-111111111111';
  private const UUID_B = '22222222-2222-4222-8222-222222222222';

  public static function layouts(): array {
    return [
      'source shared pinned revision' => [['id' => 'block_content:' . self::UUID_A, 'vid' => 73], 'old', [73], []],
      'target inline pinned revision' => [['id' => 'inline_block:json', 'block_revision_id' => 73], 'old', [73], []],
      'shared modern revision key' => [['id' => 'block_content:' . self::UUID_A, 'block_revision_id' => 73], 'old', [73], []],
      'shared without pinned version' => [['id' => 'block_content:' . self::UUID_A], 'current', [], [self::UUID_A]],
      'missing historical version' => [['id' => 'block_content:' . self::UUID_A, 'vid' => 999], NULL, [999], []],
      'revision belongs to another UUID' => [['id' => 'block_content:' . self::UUID_A, 'vid' => 74], NULL, [74], []],
      'source UUID is absent' => [['id' => 'block_content:' . self::UUID_B], NULL, [], [self::UUID_B]],
      'invalid explicit revision' => [['id' => 'block_content:' . self::UUID_A, 'vid' => 0], NULL, [], []],
      'inline revision has wrong bundle' => [['id' => 'inline_block:basic', 'block_revision_id' => 73], NULL, [73], []],
      'other block plugins are not content references' => [['id' => 'system_powered_by_block', 'block_revision_id' => 73], NULL, [], []],
    ];
  }

  #[DataProvider('layouts')]
  public function testHistoricalLayout(array $configuration, ?string $expected, array $revisionLookups, array $uuidLookups): void {
    $translations = [];
    $blocks = [];
    foreach (['old' => self::UUID_A, 'current' => self::UUID_A, 'other' => self::UUID_B] as $name => $uuid) {
      $translations[$name] = $this->createMock(BlockContent::class);
      $blocks[$name] = $this->createMock(BlockContent::class);
      $blocks[$name]->method('uuid')->willReturn($uuid);
      $blocks[$name]->method('bundle')->willReturn('json');
      $blocks[$name]->method('hasTranslation')->with('zh-hans')->willReturn(TRUE);
      $blocks[$name]->method('getTranslation')->with('zh-hans')->willReturn($translations[$name]);
    }
    $actualRevisions = $actualUuids = [];
    $blockStorage = $this->createMock(RevisionableStorageInterface::class);
    $blockStorage->method('loadRevision')->willReturnCallback(function ($id) use (&$actualRevisions, $blocks) {
      $actualRevisions[] = $id;
      return [73 => $blocks['old'], 74 => $blocks['other']][$id] ?? NULL;
    });
    $blockStorage->method('loadByProperties')->willReturnCallback(function ($properties) use (&$actualUuids, $blocks) {
      $actualUuids[] = $properties['uuid'];
      return $properties['uuid'] === self::UUID_A ? [1 => $blocks['current']] : [];
    });
    $display = $this->createMock(LayoutBuilderEntityViewDisplay::class);
    $display->method('get')->with('third_party_settings')->willReturn(['layout_builder' => ['enabled' => TRUE]]);
    $displayStorage = $this->createMock(EntityStorageInterface::class);
    $displayStorage->method('load')->willReturn($display);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['block_content', $blockStorage],
      ['entity_view_display', $displayStorage],
    ]);
    $languages = $this->createMock(LanguageManagerInterface::class);
    $languages->method('getCurrentLanguage')->willReturn(new Language(['id' => 'zh-hans']));
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entities);
    $container->set('language_manager', $languages);
    \Drupal::setContainer($container);

    $section = new Section('layout_onecol');
    $section->appendComponent(new SectionComponent('33333333-3333-4333-8333-333333333333', 'content', $configuration));
    $field = $this->createMock(FieldItemList::class);
    $field->method('getIterator')->willReturn(new \ArrayIterator([(object) ['section' => $section]]));
    $node = $this->createMock(Node::class);
    $node->method('getCacheTags')->willReturn([]);
    $node->method('getEntityTypeId')->willReturn('node');
    $node->method('bundle')->willReturn('landing_page');
    $node->method('get')->with('layout_builder__layout')->willReturn($field);

    $method = new \ReflectionMethod(PanelsIPEPageController::class, 'getRevisionBlocks');
    $result = $method->invoke(new PanelsIPEPageController(), $node);
    self::assertSame($expected === NULL ? [] : [$translations[$expected]], $result);
    self::assertSame($revisionLookups, $actualRevisions);
    self::assertSame($uuidLookups, $actualUuids);
  }

  public function testCurrentLayoutSkipsMissingBlocksAndPreservesRenderedTranslations(): void {
    $translated = $this->createMock(BlockContent::class);
    $current = $this->createMock(BlockContent::class);
    $current->method('hasTranslation')->with('zh-hans')->willReturn(TRUE);
    $current->method('getTranslation')->with('zh-hans')->willReturn($translated);
    $untranslated = $this->createMock(BlockContent::class);
    $untranslated->method('hasTranslation')->with('zh-hans')->willReturn(FALSE);

    $node = $this->createMock(Node::class);
    $node->method('getCacheTags')->willReturn([]);
    $node->method('getEntityTypeId')->willReturn('node');
    $node->method('bundle')->willReturn('landing_page');
    $display = $this->createMock(LayoutBuilderEntityViewDisplay::class);
    $display->method('get')->with('third_party_settings')->willReturn(['layout_builder' => ['enabled' => TRUE]]);
    $display->expects(self::once())->method('build')->with($node)->willReturn([
      '_layout_builder' => [
        '#cache' => ['tags' => []],
        0 => ['#markup' => 'Missing section content'],
        1 => [
          'content' => [
            '#sorted' => TRUE,
            'missing_plugin' => ['#markup' => 'Missing block plugin'],
            'empty_content' => ['content' => []],
            'current' => ['content' => ['#entity_type' => 'block_content', '#block_content' => $current]],
            'missing_entity' => ['content' => ['#entity_type' => 'block_content']],
            'invalid_entity' => ['content' => ['#entity_type' => 'block_content', '#block_content' => new \stdClass()]],
            'other_entity_type' => ['content' => ['#entity_type' => 'node', '#block_content' => $current]],
            'untranslated' => ['content' => ['#entity_type' => 'block_content', '#block_content' => $untranslated]],
          ],
        ],
      ],
    ]);
    $displayStorage = $this->createMock(EntityStorageInterface::class);
    $displayStorage->method('load')->willReturn($display);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('entity_view_display')->willReturn($displayStorage);
    $languages = $this->createMock(LanguageManagerInterface::class);
    $languages->method('getCurrentLanguage')->willReturn(new Language(['id' => 'zh-hans']));
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entities);
    $container->set('language_manager', $languages);
    \Drupal::setContainer($container);

    self::assertSame([$translated, $untranslated], (new PanelsIPEPageController())->getLayoutBuilderBlocks($node));
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

}
