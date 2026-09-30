<?php

namespace Drupal\Tests\xinshi_api\Unit;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\Plugin\WorkflowType\ContentModeration;
use Drupal\content_moderation\StateTransitionValidation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\WorkflowInterface;
use Drupal\xinshi_api\PageDraftException;
use Drupal\xinshi_api\PageModerationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Uses Drupal's workflow plugin and real transition permission validator. */
final class PageModerationPolicyTest extends TestCase {

  public static function transitions(): array {
    return [
      'author cannot publish new page' => [TRUE, TRUE, FALSE, ['create_new_draft'], 'draft', NULL],
      'reviewer can publish new page' => [TRUE, TRUE, FALSE, ['publish'], 'draft', 'published'],
      'unprivileged published edit denied' => [FALSE, FALSE, FALSE, ['create_new_draft'], 'published', NULL],
      'reviewer can update published page' => [FALSE, FALSE, FALSE, ['publish'], 'published', 'published'],
      'draft edit remains draft' => [FALSE, FALSE, FALSE, ['create_new_draft'], 'draft', 'draft'],
      'tool draft stays draft' => [TRUE, FALSE, TRUE, ['create_new_draft', 'publish'], 'draft', 'draft'],
      'tool draft requires transition permission' => [TRUE, FALSE, TRUE, ['publish'], 'draft', NULL],
    ];
  }

  #[DataProvider('transitions')]
  public function testAllowedTransitions(bool $new, bool $publish, bool $draftOnly, array $permissions, string $original, ?string $expected): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('isNew')->willReturn($new);
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(fn($permission) => in_array($permission,
      array_map(fn($id) => 'use editorial transition ' . $id, $permissions), TRUE));
    $moderation = $this->createMock(ModerationInformationInterface::class);
    $moderation->method('isModeratedEntity')->willReturn(TRUE);
    $workflow = $this->createMock(WorkflowInterface::class);
    $workflow->method('id')->willReturn('editorial');
    $type = new ContentModeration([
      'states' => [
        'draft' => ['label' => 'Draft', 'weight' => 0, 'published' => FALSE, 'default_revision' => FALSE],
        'published' => ['label' => 'Published', 'weight' => 1, 'published' => TRUE, 'default_revision' => TRUE],
      ],
      'transitions' => [
        'create_new_draft' => ['label' => 'Draft', 'from' => ['draft', 'published'], 'to' => 'draft', 'weight' => 0],
        'publish' => ['label' => 'Publish', 'from' => ['draft', 'published'], 'to' => 'published', 'weight' => 1],
      ], 'default_moderation_state' => 'draft',
    ], 'content_moderation', [], $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityTypeBundleInfoInterface::class), $moderation);
    $workflow->method('getTypePlugin')->willReturn($type);
    $moderation->method('getWorkflowForEntity')->willReturn($workflow);
    $moderation->method('getOriginalState')->willReturn($type->getState($original));
    $policy = new PageModerationPolicy($moderation, new StateTransitionValidation($moderation), $account);
    if ($expected === NULL) {
      $entity->expects(self::never())->method('set');
      $this->expectException(PageDraftException::class);
      $this->expectExceptionMessage('permission_denied');
    }
    else {
      $entity->expects(self::once())->method('set')->with('moderation_state', $expected);
    }
    $policy->apply($entity, $draftOnly ? 'draft' : NULL, $draftOnly, $publish);
  }

  public function testOptionalModerationDisabledPreservesCurrentPublishingBehavior(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects(self::never())->method('set');
    $policy = new PageModerationPolicy(NULL, NULL, $this->createMock(AccountInterface::class));
    $policy->apply($entity, publish: TRUE);
    $policy->apply($entity, 'draft', TRUE);
  }

}
