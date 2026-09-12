<?php

declare(strict_types=1);

namespace Drupal\diff\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\diff\Routing\DiffRouteProvider;

/**
 * Entity hooks for Diff module.
 */
class DiffEntityHooks {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityType $entity_type */
    foreach ($entity_types as $entity_type) {
      if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
        // Not a content entity.
        continue;
      }
      $handlers = $entity_type->get('handlers');
      if (!\array_key_exists('route_provider', $handlers)) {
        // No route providers.
        continue;
      }
      $revision_handlers = \array_filter($handlers['route_provider'], static fn (string $handler) => \is_a($handler, RevisionHtmlRouteProvider::class, TRUE));
      if (\count($revision_handlers) === 0) {
        // No revision route provider.
        continue;
      }
      if (!$entity_type->hasLinkTemplate('version-history')) {
        // No version history route.
        continue;
      }
      $history_link_template = $entity_type->getLinkTemplate('version-history');
      // Add our route provider.
      $handlers['route_provider']['html_diff'] = DiffRouteProvider::class;
      $entity_type->set('handlers', $handlers);
      $entity_type->setLinkTemplate('revisions-diff', \sprintf("%s/diff/{left_revision}/{right_revision}/{filter}", $history_link_template));
    }
  }

}
