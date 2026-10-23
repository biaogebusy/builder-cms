<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/** Adds current, access-checked user labels and avatars to site report responses. */
final class UsageUserLabelService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
  ) {}

  /**
   * Resolves only users present in this response, in one batch.
   *
   * Labels are presentation data, never stored in metering facts or CSV exports.
   * Missing accounts and denied labels leave the original IDs available.
   */
  public function enrich(array $report, AccountInterface $viewer): array {
    $userDimension = ($report['filter']['dimension'] ?? NULL) === 'users';
    $records = array_merge([$report], $report['operations'] ?? [],
      $report['attempt_list'] ?? [], $report['rows'] ?? []);
    $ids = [];
    foreach ($records as $record) {
      $id = $record['actor_user_id'] ?? ($userDimension ? ($record['dimension'] ?? NULL) : NULL);
      // Metering can contain external actor labels; only Drupal UIDs can be loaded.
      if (is_string($id) && preg_match('/^(0|[1-9][0-9]{0,9})\z/', $id) && (int) $id <= 2147483647) {
        $ids[$id] = $id;
      }
    }
    $names = [];
    $avatarIds = [];
    if ($ids !== []) {
      /** @var \Drupal\user\UserInterface[] $users */
      $users = $this->entityTypes->getStorage('user')->loadMultiple(array_values($ids));
      foreach ($users as $id => $user) {
        if ($user->access('view label', $viewer)) {
          $name = trim((string) $user->getDisplayName());
          if ($name !== '') {
            $names[$id] = $name;
          }
        }
        if ($userDimension && $user->hasField('user_picture') && $user->access('view', $viewer)) {
          $picture = $user->get('user_picture');
          if ($picture->access('view', $viewer) && !$picture->isEmpty()) {
            $fileId = $picture->getValue()[0]['target_id'] ?? NULL;
            if ($fileId) {
              $avatarIds[$id] = $fileId;
            }
          }
        }
      }
    }
    $avatars = [];
    if ($avatarIds !== []) {
      // Resolve image references in one batch, without loading each user's file separately.
      /** @var \Drupal\file\FileInterface[] $files */
      $files = $this->entityTypes->getStorage('file')->loadMultiple(array_values(array_unique($avatarIds)));
      foreach ($files as $id => $file) {
        if ($file->access('view', $viewer) && $file->get('uri')->access('view', $viewer)) {
          $avatars[$id] = $file->createFileUrl(FALSE);
        }
      }
    }
    $labelActor = static function (array $record) use ($names): array {
      if (array_key_exists('actor_user_id', $record)) {
        $record['actor_user_name'] = $names[$record['actor_user_id'] ?? ''] ?? NULL;
      }
      return $record;
    };
    $report = $labelActor($report);
    foreach (['operations', 'attempt_list', 'rows'] as $section) {
      if (!isset($report[$section])) {
        continue;
      }
      foreach ($report[$section] as &$record) {
        $record = $labelActor($record);
        if ($section === 'rows' && $userDimension) {
          $record['dimension_label'] = $names[$record['dimension']] ?? NULL;
          $record['dimension_avatar'] = $avatars[$avatarIds[$record['dimension']] ?? ''] ?? NULL;
        }
      }
      unset($record);
    }
    return $report;
  }

}
