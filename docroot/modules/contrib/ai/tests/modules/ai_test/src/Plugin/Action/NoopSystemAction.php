<?php

declare(strict_types=1);

namespace Drupal\ai_test\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A no-op system action used to test the AI action access fallback.
 *
 * It is a non-entity ('system') action that is intentionally absent from
 * ActionPluginBase::KNOWN_ACTION_PERMISSIONS, so it exercises the path where
 * an unknown action simply defers to its own access() check. Its access()
 * here is gated on the 'administer site configuration' permission so the test
 * can assert the wrapper honours the action's own decision.
 */
#[\Drupal\Core\Action\Attribute\Action(
  id: 'ai_test_noop_action',
  label: new TranslatableMarkup('AI test no-op action'),
  type: 'system',
)]
class NoopSystemAction extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    // Intentionally does nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIfHasPermission($account, 'administer site configuration');
    return $return_as_object ? $result : $result->isAllowed();
  }

}
