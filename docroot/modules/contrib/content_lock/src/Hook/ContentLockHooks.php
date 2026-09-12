<?php

namespace Drupal\content_lock\Hook;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionHandler;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Drupal\views\ViewEntityInterface;

/**
 * Hook implementations for the Content Lock module.
 */
class ContentLockHooks {
  use StringTranslationTrait;

  public function __construct(
    private ContentLockInterface $contentLock,
    private MessengerInterface $messenger,
    private ConfigFactoryInterface $configFactory,
    private AccountInterface $currentUser,
    private Connection $database,
    private EntityTypeManagerInterface $entityTypeManager,
    private DateFormatterInterface $dateFormatter,
    private LoggerChannelFactoryInterface $logger,
    private ConfigInstallerInterface $configInstaller,
  ) {
  }

  /**
   * Implements hook_user_predelete().
   *
   * Delete content locks entries when a user gets deleted. If a user has
   * permission to cancel or delete a user then it is not necessary to check
   * whether they can break locks.
   */
  #[Hook('user_predelete', order: Order::First)]
  public function userPredelete(UserInterface $account): void {
    $this->contentLock->releaseAllUserLocks((int) $account->id());
  }

  /**
   * Implements hook_user_cancel().
   */
  #[Hook('user_cancel')]
  public function userCancel($edit, UserInterface $account, $method): void {
    $this->contentLock->releaseAllUserLocks((int) $account->id());
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for views.
   *
   * When a view is saved, prevent using a cache if the content_lock data is
   * displayed.
   */
  #[Hook('view_presave')]
  public function viewPresave(ViewEntityInterface $view): void {
    $viewDependencies = $view->getDependencies();
    if (in_array('content_lock', $viewDependencies['module'] ?? [], TRUE)) {
      $changed_cache = FALSE;
      $displays = $view->get('display');
      foreach ($displays as &$display) {
        if (isset($display['display_options']['cache']['type']) && $display['display_options']['cache']['type'] !== 'none') {
          $display['display_options']['cache']['type'] = 'none';
          $changed_cache = TRUE;
        }
      }
      if ($changed_cache) {
        $view->set('display', $displays);
        $warning = $this->t('The selected caching mechanism does not work with views including content lock information. The selected caching mechanism was changed to none accordingly for the view %view.', ['%view' => $view->label()]);
        $this->messenger->addWarning($warning);
      }
    }
  }

  /**
   * Implements hook_content_lock_entity_lockable().
   */
  #[Hook('content_lock_entity_lockable', module: 'trash')]
  public function trashContentEntityLockable(EntityInterface $entity, array $config, ?string $form_op = NULL): bool {
    return !trash_entity_is_deleted($entity);
  }

  /**
   * Implements hook_entity_operation().
   *
   * @todo remove NULL default when minimum version of drupal is 11.3.0.
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
    $operations = [];

    if ($cacheability) {
      $cacheability->addCacheableDependency($this->configFactory->get('content_lock.settings'));
    }

    if ($this->contentLock->isLockable($entity)) {
      $lock = $this->contentLock->fetchLock($entity);

      if ($lock && ($this->currentUser->hasPermission('break content lock') || $this->currentUser->id() == $lock->uid)) {
        $entity_type = $entity->getEntityTypeId();
        $route_parameters = [
          'entity' => $entity->id(),
          'langcode' => $this->contentLock->isTranslationLockEnabled($entity_type) ? $entity->language()
            ->getId() : LanguageInterface::LANGCODE_NOT_SPECIFIED,
          'form_op' => '*',
        ];
        $url = 'content_lock.break_lock.' . $entity->getEntityTypeId();
        $operations['break_lock'] = [
          'title' => $this->t('Break lock'),
          'url' => Url::fromRoute($url, $route_parameters),
          'weight' => 50,
        ];
      }

      if ($cacheability) {
        $cacheability->setCacheContexts(['user']);
        // If the entity type is lockable this access result cannot be cached as
        // you can lock an entity just by visiting the edit form.
        $cacheability->setCacheMaxAge(0);
      }
    }

    return $operations;
  }

  /**
   * Implements hook_entity_delete().
   *
   * Releases locks when an entity is deleted. Note that users are prevented
   * from deleting locked content by content_lock_entity_access() if they do not
   * have the break lock permission.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if (!$this->contentLock->isLockable($entity)) {
      return;
    }

    $data = $this->contentLock->fetchLock($entity, include_stale_locks: TRUE);
    if ($data !== FALSE) {
      $this->contentLock->release($entity);
    }
  }

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    $result = AccessResult::neutral();
    if ($operation === 'delete') {
      // Check if we must lock this entity.
      $result->addCacheableDependency($this->configFactory->get('content_lock.settings'));
      if ($this->contentLock->hasLockEnabled($entity->getEntityTypeId())) {
        // The result is dependent on user IDs.
        $result->cachePerUser();
        $data = $this->contentLock->fetchLock($entity);
        if ($data !== FALSE && $account->id() != $data->uid && !$account->hasPermission('break content lock')) {
          // If the entity is locked, current user is not the lock's owner and
          // the user does not have the break lock permission, then forbid
          // access.
          $result = $result->andIf(AccessResult::forbidden('The entity is locked'));
        }
        // If the entity type is lockable this access result cannot be cached as
        // you can lock an entity just by visiting the edit form.
        $result->setCacheMaxAge(0);
      }
    }

    return $result;
  }

  /**
   * Implements hook_cron().
   *
   * Breaks batches of stale locks whenever the cron hooks are run.
   */
  #[Hook('cron')]
  public function cron(): void {
    $count = $this->contentLock->releaseExpiredLocks();
    if ($count) {
      $timeout = $this->configFactory->get('content_lock.settings')->get('timeout');
      $period = $this->dateFormatter->formatInterval($timeout);
      $this->logger->get('content_lock')->notice(
        'Released @count stale content lock(s) which lasted at least @period.',
        ['@count' => $count, '@period' => $period]
      );
    }
  }

  /**
   * Implements hook_user_logout().
   */
  #[Hook('user_logout')]
  public function userLogout(AccountInterface $account): void {
    $session_count = FALSE;
    // Only do the database check if the original drupal session manager is
    // used. Otherwise, it's not sure if sessions table has correct data.
    // @phpstan-ignore-next-line
    if (\Drupal::service('session_handler.storage') instanceof SessionHandler) {
      // The session table may not exist yet, all queries against the sessions
      // table must catch database exceptions. We can ignore exceptions.
      try {
        $session_count = (int) $this->database->select('sessions')
          ->condition('uid', $account->id())
          ->countQuery()
          ->execute()->fetchField();
      }
      catch (DatabaseException $e) {
      }
    }
    // Only remove all locks of user if it's the last session of the user.
    if ($session_count === 1) {
      $this->contentLock->releaseAllUserLocks((int) $account->id());
    }
  }

  /**
   * Implements hook_entity_bundle_delete().
   */
  #[Hook('entity_bundle_delete')]
  public function entityBundleDelete($entity_type_id, $bundle_id): void {
    if ($this->configInstaller->isSyncing()) {
      return;
    }
    $config = $this->configFactory->getEditable('content_lock.settings');
    $entity_type_config = $config->get('types.' . $entity_type_id) ?? [];
    if (in_array($bundle_id, $entity_type_config, TRUE)) {
      $entity_type_config = array_diff($entity_type_config, [$bundle_id]);
      if (empty($entity_type_config)) {
        $config = self::removeEntityTypeFromConfig($entity_type_id, $config);
      }
      else {
        $config->set('types.' . $entity_type_id, $entity_type_config);
      }
      $config->save();
    }
  }

  /**
   * Removes the provided entity type from the content lock config.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\Core\Config\Config $config
   *   The content lock config.
   *
   * @return \Drupal\Core\Config\Config
   *   The content lock config.
   *
   * @internal
   */
  public static function removeEntityTypeFromConfig(string $entity_type_id, Config $config): Config {
    $config->clear('types.' . $entity_type_id);
    $config->clear('form_op_lock.' . $entity_type_id);
    $translation_lock = $config->get('types_translation_lock') ?? [];
    if (in_array($entity_type_id, $translation_lock, TRUE)) {
      $config->set('types_translation_lock', array_diff($translation_lock, [$entity_type_id]));
    }
    return $config;
  }

}
