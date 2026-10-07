<?php

namespace Drupal\Tests\xinshi_api\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\views\ViewEntityInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\ViewExecutableFactory;
use Drupal\xinshi_api\EntityJsonBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Covers nullable Views output at the layout serialization boundary. */
final class LayoutViewRenderTest extends TestCase {

  public static function outputCases(): array {
    return [
      'build failed' => ['fail', NULL, [], 0],
      'build denied' => ['denied', NULL, [], 0],
      'empty output' => [NULL, [], [], -1],
      'rows and metadata' => [NULL, [
        '#rows' => [['#rows' => [['title' => 'Visible row']]]],
        '#cache' => ['tags' => ['node:2'], 'contexts' => ['url.query_args'], 'max-age' => 60],
      ], [['title' => 'Visible row']], 60],
    ];
  }

  #[DataProvider('outputCases')]
  public function testLayoutOutput(?string $failure, ?array $output, array $rows, int $max_age): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('article');
    $entity->method('getCacheTags')->willReturn(['node:1']);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
    $storage = $this->createMock(ViewEntityInterface::class);
    $storage->method('getCacheTags')->willReturn(['config:views.view.related']);
    $storage->method('getCacheContexts')->willReturn([]);
    $storage->method('getCacheMaxAge')->willReturn(-1);
    $methods = ['access', 'setDisplay', 'preExecute', 'execute', 'getTitle', 'getCacheTags'];
    if ($failure === NULL) {
      $methods[] = 'render';
    }
    $view = $this->getMockBuilder(ViewExecutable::class)->disableOriginalConstructor()->onlyMethods($methods)->getMock();
    $view->storage = $storage;
    $view->method('access')->with('block_1')->willReturn(TRUE);
    $view->method('getTitle')->willReturn('Related');
    $view->method('getCacheTags')->willReturn(['node_list']);
    if ($failure !== NULL) {
      // Use core render() for both documented early-return paths.
      $view->build_info[$failure] = TRUE;
    }
    else {
      $view->method('render')->willReturn($output);
    }
    $display = $this->createMock(LayoutBuilderEntityViewDisplay::class);
    $display->method('getCacheTags')->willReturn(['config:core.entity_view_display.node.article.json']);
    $display->method('getCacheContexts')->willReturn([]);
    $display->method('getCacheMaxAge')->willReturn(-1);
    $display->method('getSections')->willReturn([new Section('layout_onecol', [], [
      new SectionComponent('test-component', 'content', ['provider' => 'views', 'id' => 'views_block:related-block_1']),
    ])]);
    $display_storage = $this->createMock(EntityStorageInterface::class);
    $display_storage->method('load')->with('node.article.json')->willReturn($display);
    $view_storage = $this->createMock(EntityStorageInterface::class);
    $view_storage->method('load')->with('related')->willReturn($storage);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnMap([['entity_view_display', $display_storage], ['view', $view_storage]]);
    $repository = $this->createMock(EntityTypeRepositoryInterface::class);
    $repository->method('getEntityTypeFromClass')->willReturn('entity_view_display');
    $factory = $this->createMock(ViewExecutableFactory::class);
    $factory->method('get')->with($storage)->willReturn($view);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $manager);
    $container->set('entity_type.repository', $repository);
    $container->set('views.executable', $factory);
    \Drupal::setContainer($container);
    $json = new class($entity) extends EntityJsonBase {
      public function layout(): array { return $this->renderLayoutBuilder(); }
    };
    $this->assertSame(['related_block_1' => ['rows' => $rows, 'title' => 'Related']], $json->layout());
    $this->assertSame($max_age, $json->getCacheMaxAge());
    $this->assertContains('config:views.view.related', $json->getCacheTags());
    $this->assertContains('node_list', $json->getCacheTags());
    if ($rows) {
      $this->assertContains('node:2', $json->getCacheTags());
      $this->assertContains('url.query_args', $json->getCacheContexts());
    }
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

}
