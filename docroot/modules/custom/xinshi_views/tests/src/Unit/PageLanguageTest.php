<?php

namespace Drupal\Tests\xinshi_views\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\Language;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\xinshi_views\Plugin\views\style\PagerSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

/** Verifies original-language identity and protected translation metadata. */
final class PageLanguageTest extends TestCase {

  public static function cases(): array {
    return [
      'English original' => ['en', TRUE, TRUE, TRUE],
      'Chinese translation' => ['zh-hans', FALSE, TRUE, TRUE],
      'language fields denied' => ['en', TRUE, FALSE, TRUE],
      'another translation denied' => ['en', TRUE, TRUE, FALSE],
    ];
  }

  #[DataProvider('cases')]
  public function testLanguageIdentity(string $langcode, bool $original, bool $fields_allowed, bool $other_allowed): void {
    $page = $this->createMock(LanguagePageInterface::class);
    $other = $this->createMock(LanguagePageInterface::class);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('access')->willReturn($fields_allowed
      ? AccessResult::allowed()->cachePerPermissions()
      : AccessResult::forbidden()->cachePerPermissions());
    foreach ([$page, $other] as $entity) {
      $entity->method('get')->willReturn($field);
      $entity->method('getCacheTags')->willReturn(['node:7']);
      $entity->method('getCacheContexts')->willReturn([]);
      $entity->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    }
    $page->method('getEntityTypeId')->willReturn('node');
    $page->method('bundle')->willReturn('landing_page');
    $page->method('hasField')->willReturn(TRUE);
    $page->method('isDefaultTranslation')->willReturn($original);
    $page->method('access')->willReturn(AccessResult::allowed()->cachePerPermissions());
    $other->method('access')->willReturn($other_allowed
      ? AccessResult::allowed()->cachePerUser() : AccessResult::forbidden()->cachePerUser());
    $page->method('getTranslationLanguages')->willReturn([
      'en' => new Language(['id' => 'en']), 'zh-hans' => new Language(['id' => 'zh-hans']),
    ]);
    $page->method('getTranslation')->willReturnCallback(fn($code) => $code === $langcode ? $page : $other);
    $view = $this->createMock(ViewExecutable::class);
    $view->result = [new ResultRow(['_entity' => $page])];
    $view->element = [];
    $view->live_preview = TRUE;
    $view->rowPlugin = $this->createMock(RowPluginBase::class);
    $view->rowPlugin->method('render')->willReturn(['langcode' => $langcode]);
    $serializer = $this->createMock(SerializerInterface::class);
    $serializer->method('serialize')->willReturnCallback(fn($value) => json_encode($value));
    $plugin = new PagerSerializer([], 'xinshi_pager_serializer', [], $serializer, ['json'], []);
    $plugin->view = $view;
    $plugin->options = ['formats' => ['json']];
    $row = json_decode($plugin->render(), TRUE)['rows'][0];
    if ($fields_allowed) {
      $this->assertSame($original, $row['default_langcode']);
      $this->assertContains('user', $view->element['#cache']['contexts']);
    }
    else {
      $this->assertArrayNotHasKey('default_langcode', $row);
    }
    if ($fields_allowed && $other_allowed) {
      $this->assertSame(['en', 'zh-hans'], $row['translation_languages']);
    }
    else {
      $this->assertArrayNotHasKey('translation_languages', $row);
    }
    $this->assertContains('node:7', $view->element['#cache']['tags']);
    $this->assertContains('user.permissions', $view->element['#cache']['contexts']);
  }
}

interface LanguagePageInterface extends ContentEntityInterface, EntityPublishedInterface {}
