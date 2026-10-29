<?php

namespace Drupal\Tests\xinshi_views\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\xinshi_views\Plugin\views\style\PagerSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

/** Verifies publication metadata on the actual paged row serializer. */
final class PagePublicationTest extends TestCase {

  public static function states(): array {
    return [[TRUE, TRUE], [FALSE, TRUE], [FALSE, FALSE]];
  }

  #[DataProvider('states')]
  public function testExactTranslationAndFieldAccess(bool $published, bool $allowed): void {
    $entity = $this->createMock(PublishedPageInterface::class);
    $translation = $this->createMock(PublishedPageInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('landing_page');
    $entity->method('hasTranslation')->with('fr')->willReturn(TRUE);
    $entity->method('getTranslation')->with('fr')->willReturn($translation);
    $translation->method('isPublished')->willReturn($published);
    $translation->method('getCacheTags')->willReturn(['node:12']);
    $translation->method('getCacheContexts')->willReturn([]);
    $translation->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $translation->method('access')->willReturn(AccessResult::allowed()->cachePerPermissions());
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('access')->willReturn($allowed ? AccessResult::allowed() : AccessResult::forbidden());
    $translation->method('get')->with('status')->willReturn($field);
    $view = $this->createMock(ViewExecutable::class);
    $view->result = [new ResultRow(['_entity' => $entity])];
    $view->element = ['#cache' => ['tags' => ['view:existing']]];
    $view->live_preview = TRUE;
    $view->rowPlugin = $this->createMock(RowPluginBase::class);
    $view->rowPlugin->method('render')->willReturn(['nid' => '12', 'langcode' => 'fr', 'title' => 'Page']);
    $serializer = $this->createMock(SerializerInterface::class);
    $serializer->method('serialize')->willReturnCallback(fn($value) => json_encode($value));
    $plugin = new PagerSerializer([], 'xinshi_pager_serializer', [], $serializer, ['json'], []);
    $plugin->view = $view;
    $plugin->options = ['formats' => ['json']];
    $data = json_decode($plugin->render(), TRUE);
    $this->assertSame('Page', $data['rows'][0]['title']);
    if ($allowed) {
      $this->assertSame($published, $data['rows'][0]['published']);
    } else {
      $this->assertArrayNotHasKey('published', $data['rows'][0]);
    }
    $this->assertContains('node:12', $view->element['#cache']['tags']);
    $this->assertContains('view:existing', $view->element['#cache']['tags']);
    $this->assertContains('user.permissions', $view->element['#cache']['contexts']);
  }
}

interface PublishedPageInterface extends EntityPublishedInterface, ContentEntityInterface {}
