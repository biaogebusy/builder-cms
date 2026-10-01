<?php

namespace Drupal\Tests\xinshi_api\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\xinshi_api\EntityJsonBase;
use PHPUnit\Framework\TestCase;

/** Verifies dependencies from layouts whose child arrays are read directly. */
final class EntityJsonCacheabilityTest extends TestCase {

  public function testUnrenderedLayoutAccessAndReferencedContentMetadata(): void {
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->createMock(EntityTypeManagerInterface::class));
    \Drupal::setContainer($container);
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getCacheTags')->willReturn(['node:1']);
    $entity->method('getCacheContexts')->willReturn(['languages:language_content']);
    $entity->method('getCacheMaxAge')->willReturn(-1);
    $json = new class($entity) extends EntityJsonBase {
      public function collect(array $build): void { $this->addRenderCacheability($build); }
    };
    $json->collect([
      '#cache' => ['tags' => ['config:core.entity_view_display.node.landing_page.json']],
      'section' => ['component' => [
        '#access' => AccessResult::forbidden()->addCacheContexts(['user'])->addCacheTags(['access:block']),
        'content' => ['#cache' => ['tags' => ['block_content:2'], 'contexts' => ['timezone'], 'max-age' => 60]],
      ]],
    ]);
    $json->addCacheableDependency((new CacheableMetadata())->setCacheTags(['media:3'])->setCacheMaxAge(30));
    foreach (['node:1', 'block_content:2', 'access:block', 'media:3', 'config:core.entity_view_display.node.landing_page.json'] as $tag) {
      $this->assertContains($tag, $json->getCacheTags());
    }
    foreach (['languages:language_content', 'user', 'timezone'] as $context) {
      $this->assertContains($context, $json->getCacheContexts());
    }
    $this->assertSame(30, $json->getCacheMaxAge());
    $json->collect(['content' => ['#cache' => ['max-age' => 0]]]);
    $this->assertSame(0, $json->getCacheMaxAge());
  }

}
