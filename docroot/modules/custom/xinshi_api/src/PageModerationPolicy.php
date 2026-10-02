<?php

namespace Drupal\xinshi_api;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/** Applies the same transition checks to builder and tool-created pages. */
final class PageModerationPolicy {

  public function __construct(
    private readonly ?ModerationInformationInterface $moderation,
    private readonly ?StateTransitionValidationInterface $transitions,
    private readonly AccountInterface $account,
  ) {}

  public function apply(ContentEntityInterface $entity, ?string $requested = NULL, bool $draft_only = FALSE, bool $publish = FALSE): void {
    if (!$this->moderation || !$this->moderation->isModeratedEntity($entity)) {
      if ($requested !== NULL && !$draft_only) {
        throw new PageDraftException('invalid_input', 422);
      }
      return;
    }
    $workflow = $this->moderation->getWorkflowForEntity($entity);
    $type = $workflow->getTypePlugin();
    $original = $this->moderation->getOriginalState($entity);
    $target = $requested ?? ($publish ? 'published' : ($entity->isNew() ? $type->getInitialState($entity)->id() : $original->id()));
    if (!$type->hasState($target)) {
      throw new PageDraftException($draft_only ? 'draft_not_supported' : 'invalid_input', $draft_only ? 503 : 422);
    }
    $state = $type->getState($target);
    if ($draft_only && $state->isPublishedState()) {
      throw new PageDraftException('draft_not_supported', 503);
    }
    if (!$this->transitions || !$original->canTransitionTo($target) ||
      !$this->transitions->isTransitionValid($workflow, $original, $state, $this->account, $entity)) {
      throw new PageDraftException('permission_denied', 403, $entity->getEntityTypeId() === 'node' ? 'page_moderation_denied' : 'component_moderation_denied');
    }
    $entity->set('moderation_state', $target);
  }

}
