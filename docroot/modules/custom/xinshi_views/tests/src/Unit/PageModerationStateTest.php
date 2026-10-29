<?php

namespace Drupal\Tests\xinshi_views\Unit;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\Plugin\WorkflowType\ContentModeration;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\workflows\WorkflowInterface;
use Drupal\xinshi_views\Plugin\views\style\PagerSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\SerializerInterface;

/** Checks translated workflow metadata independently of the public status flag. */
final class PageModerationStateTest extends TestCase {

  public static function states(): array {
    return [
      'draft translation' => ['draft', FALSE, TRUE, TRUE, TRUE, TRUE],
      'withdrawn translation' => ['unpublished', FALSE, TRUE, TRUE, TRUE, TRUE],
      'published translation' => ['published', TRUE, TRUE, TRUE, TRUE, TRUE],
      'custom review state' => ['review', FALSE, TRUE, TRUE, TRUE, TRUE],
      'unknown retired state' => ['retired', FALSE, TRUE, TRUE, TRUE, TRUE],
      'state field denied' => ['draft', FALSE, FALSE, TRUE, TRUE, TRUE],
      'public status denied' => ['draft', FALSE, TRUE, FALSE, TRUE, TRUE],
      'entity denied' => ['draft', FALSE, TRUE, TRUE, FALSE, TRUE],
      'unmoderated bundle' => ['draft', FALSE, TRUE, TRUE, TRUE, FALSE],
      'moderation module absent' => [NULL, FALSE, TRUE, TRUE, TRUE, FALSE],
    ];
  }

  #[DataProvider('states')]
  public function testMetadata(?string $state, bool $published, bool $state_allowed, bool $status_allowed, bool $entity_allowed, bool $moderated): void {
    $source = $this->createMock(ModeratedPageInterface::class);
    $translation = $this->createMock(ModeratedPageInterface::class);
    $source->method('getEntityTypeId')->willReturn('node');
    $source->method('bundle')->willReturn('landing_page');
    $source->method('hasTranslation')->with('fr')->willReturn(TRUE);
    $source->method('getTranslation')->with('fr')->willReturn($translation);
    $translation->method('isPublished')->willReturn($published);
    $translation->method('access')->willReturn(AccessResult::allowedIf($entity_allowed)->cachePerPermissions());
    $translation->method('getCacheTags')->willReturn(['node:12']);
    $translation->method('getCacheContexts')->willReturn([]);
    $translation->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $status = $this->createMock(FieldItemListInterface::class);
    $status->method('access')->willReturn(AccessResult::allowedIf($status_allowed));
    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('__get')->with('value')->willReturn($state);
    $items->method('access')->willReturn(AccessResult::allowedIf($state_allowed)->addCacheContexts(['user']));
    $translation->method('get')->willReturnMap([['status', $status], ['moderation_state', $items]]);

    $container = new ContainerBuilder();
    $serializer = $this->createMock(SerializerInterface::class);
    $serializer->method('serialize')->willReturnCallback(fn($data) => json_encode($data));
    $container->set('serializer', $serializer);
    $container->setParameter('serializer.formats', ['json']);
    $container->setParameter('serializer.format_providers', []);
    $labels = ['draft' => 'Draft', 'published' => 'Published', 'unpublished' => 'Unpublished', 'review' => 'Legal review'];
    if ($state !== NULL) {
      $moderation = $this->createMock(ModerationInformationInterface::class);
      $moderation->method('isModeratedEntity')->with($translation)->willReturn($moderated);
      $workflow = $this->createMock(WorkflowInterface::class);
      $workflow->method('getCacheTags')->willReturn(['config:workflows.workflow.editorial']);
      $workflow->method('getCacheContexts')->willReturn([]);
      $workflow->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
      $states = [];
      foreach ($labels as $id => $label) {
        $states[$id] = ['label' => $label, 'weight' => 0, 'published' => $id === 'published', 'default_revision' => TRUE];
      }
      $type = new ContentModeration(['states' => $states, 'transitions' => []], 'content_moderation', [],
        $this->createMock(EntityTypeManagerInterface::class), $this->createMock(EntityTypeBundleInfoInterface::class), $moderation);
      $workflow->method('getTypePlugin')->willReturn($type);
      $moderation->expects($moderated ? self::once() : self::never())
        ->method('getWorkflowForEntity')->with($translation)->willReturn($workflow);
      $container->set('content_moderation.moderation_information', $moderation);
    }
    $view = $this->createMock(ViewExecutable::class);
    $view->result = [new ResultRow(['_entity' => $source])];
    $view->element = ['#cache' => ['tags' => ['view:existing']]];
    $view->live_preview = TRUE;
    $view->rowPlugin = $this->createMock(RowPluginBase::class);
    $view->rowPlugin->method('render')->willReturn(['nid' => '12', 'langcode' => 'fr']);
    $plugin = PagerSerializer::create($container, [], 'xinshi_pager_serializer', []);
    $plugin->view = $view;
    $plugin->options = ['formats' => ['json']];
    $row = json_decode($plugin->render(), TRUE)['rows'][0];
    if ($entity_allowed && $status_allowed) {
      self::assertSame($published, $row['published']);
    }
    else {
      self::assertArrayNotHasKey('published', $row);
    }
    if ($entity_allowed && $state_allowed && $moderated && isset($labels[$state])) {
      self::assertSame($state, $row['moderation_state']);
      self::assertSame($labels[$state], $row['moderation_state_label']);
    }
    else {
      self::assertArrayNotHasKey('moderation_state', $row);
      self::assertArrayNotHasKey('moderation_state_label', $row);
    }
    if ($moderated) {
      self::assertContains('config:workflows.workflow.editorial', $view->element['#cache']['tags']);
      if ($entity_allowed) {
        self::assertContains('user', $view->element['#cache']['contexts']);
      }
      self::assertContains('languages:language_interface', $view->element['#cache']['contexts']);
    }
  }

}

interface ModeratedPageInterface extends EntityPublishedInterface, ContentEntityInterface {}
