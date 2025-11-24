<?php

namespace Drupal\content_lock\ContentLock;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ContentLock.
 *
 * The content lock service.
 */
class ContentLock implements ContentLockInterface {

  use StringTranslationTrait;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   *   The database service.
   */
  protected $database;

  /**
   * The module_handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   *   The module_handler service.
   */
  protected $moduleHandler;

  /**
   * The date.formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   *   The date.formatter service.
   */
  protected $dateFormatter;

  /**
   * The current_user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   *   The current_user service.
   */
  protected $currentUser;

  /**
   * The config.factory service.
   *
   * @var \Drupal\Core\Config\Config
   *   The config settings.
   */
  protected $config;

  /**
   * The redirect.destination service.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   *   The current request.
   */
  protected $currentRequest;

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity_type.manager service.
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module Handler service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date.formatter service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current_user service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config.factory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity_type.manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock service.
   */
  public function __construct(
    Connection $database,
    ModuleHandlerInterface $moduleHandler,
    DateFormatterInterface $dateFormatter,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $configFactory,
    RequestStack $requestStack,
    EntityTypeManagerInterface $entityTypeManager,
    MessengerInterface $messenger,
    TimeInterface $time,
    LockBackendInterface $lock
  ) {
    $this->database = $database;
    $this->moduleHandler = $moduleHandler;
    $this->dateFormatter = $dateFormatter;
    $this->currentUser = $currentUser;
    $this->config = $configFactory->get('content_lock.settings');
    $this->currentRequest = $requestStack->getCurrentRequest();
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
    $this->time = $time;
    $this->lock = $lock;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchLock($entity_id, $langcode, $form_op = NULL, $entity_type = 'node') {
    if (!$this->isTranslationLockEnabled($entity_type)) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    if (!$this->isFormOperationLockEnabled($entity_type)) {
      $form_op = '*';
    }
    $query = $this->database->select('content_lock', 'c');
    $query->leftJoin('users_field_data', 'u', '%alias.uid = c.uid');
    $query->fields('c')
      ->fields('u', ['name'])
      ->condition('c.entity_type', $entity_type)
      ->condition('c.entity_id', $entity_id)
      ->condition('c.langcode', $langcode);
    if (isset($form_op)) {
      $query->condition('c.form_op', $form_op);
    }

    return $query->execute()->fetchObject();
  }

  /**
   * {@inheritdoc}
   */
  public function displayLockOwner($lock, $translation_lock) {
    $username = $this->entityTypeManager->getStorage('user')->load($lock->uid);
    $date = $this->dateFormatter->formatInterval($this->time->getRequestTime() - $lock->timestamp);

    if ($translation_lock) {
      $message = $this->t('This content translation is being edited by the user @name and is therefore locked to prevent other users changes. This lock is in place since @date.', [
        '@name' => $username->getDisplayName(),
        '@date' => $date,
      ]);
    }
    else {
      $message = $this->t('This content is being edited by the user @name and is therefore locked to prevent other users changes. This lock is in place since @date.', [
        '@name' => $username->getDisplayName(),
        '@date' => $date,
      ]);
    }
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function isLockedBy($entity_id, $langcode, $form_op, $uid, $entity_type = 'node') {
    if (!$this->isTranslationLockEnabled($entity_type)) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    if (!$this->isFormOperationLockEnabled($entity_type)) {
      $form_op = '*';
    }
    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    $query = $this->database->select('content_lock', 'c')
      ->fields('c')
      ->condition('entity_id', $entity_id)
      ->condition('uid', $uid)
      ->condition('entity_type', $entity_type)
      ->condition('langcode', $langcode)
      ->condition('form_op', $form_op);
    $num_rows = $query->countQuery()->execute()->fetchField();
    return (bool) $num_rows;
  }

  /**
   * {@inheritdoc}
   */
  public function release($entity_id, $langcode, $form_op = NULL, $uid = NULL, $entity_type = 'node') {
    if (!$this->isTranslationLockEnabled($entity_type)) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    if (!$this->isFormOperationLockEnabled($entity_type)) {
      $form_op = '*';
    }
    // Delete locking item from database.
    $this->lockingDelete($entity_id, $langcode, $form_op, $uid, $entity_type);

    $this->moduleHandler->invokeAll(
      'content_lock_release',
      [$entity_id, $langcode, $form_op, $entity_type]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function releaseAllUserLocks($uid) {
    $this->database->delete('content_lock')
      ->condition('uid', $uid)
      ->execute();
  }

  /**
   * Save locking into database.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $langcode
   *   The translation language of the entity.
   * @param string $form_op
   *   The entity form operation.
   * @param int $uid
   *   The user uid.
   * @param string $entity_type
   *   The entity type.
   *
   * @return bool
   *   The result of the merge query.
   */
  protected function lockingSave($entity_id, $langcode, $form_op, $uid, $entity_type = 'node') {
    if (!$this->isTranslationLockEnabled($entity_type)) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    if (!$this->isFormOperationLockEnabled($entity_type)) {
      $form_op = '*';
    }
    $result = $this->database->merge('content_lock')
      ->keys([
        'entity_id' => $entity_id,
        'entity_type' => $entity_type,
        'langcode' => $langcode,
        'form_op' => $form_op,
      ])
      ->fields([
        'entity_id' => $entity_id,
        'entity_type' => $entity_type,
        'langcode' => $langcode,
        'form_op' => $form_op,
        'uid' => $uid,
        'timestamp' => $this->time->getRequestTime(),
      ])
      ->execute();

    return $result;
  }

  /**
   * Delete locking item from database.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $langcode
   *   The translation language of the entity.
   * @param string $form_op
   *   (optional) The entity form operation.
   * @param int $uid
   *   The user uid.
   * @param string $entity_type
   *   The entity type.
   *
   * @return bool
   *   The result of the delete query.
   */
  protected function lockingDelete($entity_id, $langcode, $form_op = NULL, $uid = NULL, $entity_type = 'node') {
    if (!$this->isTranslationLockEnabled($entity_type)) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    if (!$this->isFormOperationLockEnabled($entity_type)) {
      $form_op = '*';
    }
    $query = $this->database->delete('content_lock')
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->condition('langcode', $langcode);
    if (isset($form_op)) {
      $query->condition('form_op', $form_op);
    }
    if (!empty($uid)) {
      $query->condition('uid', $uid);
    }

    $result = $query->execute();

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function verbose() {
    return $this->config->get('verbose');
  }

  /**
   * {@inheritdoc}
   */
  public function locking($entity_id, $langcode, $form_op, $uid, $entity_type = 'node', $quiet = FALSE, $destination = NULL) {
    $translation_lock = $this->isTranslationLockEnabled($entity_type);
    if (!$translation_lock) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    if (!$this->isFormOperationLockEnabled($entity_type)) {
      $form_op = '*';
    }

    // Acquire a lock from the lock service to prevent a race condition when
    // checking if a lock exists and update the content lock table with the new
    // information.
    $lock_service_name = "content_lock:$entity_type:$entity_id";
    $lock_service_acquired = $this->lock->acquire($lock_service_name);
    if (!$lock_service_acquired) {
      $this->lock->wait($lock_service_name, 5);
      $lock_service_acquired = $this->lock->acquire($lock_service_name);
    }

    // Check locking status.
    $lock = $this->fetchLock($entity_id, $langcode, $form_op, $entity_type);

    // No lock yet.
    if ($lock_service_acquired && ($lock === FALSE || !is_object($lock))) {
      // Save locking into database.
      $this->lockingSave($entity_id, $langcode, $form_op, $uid, $entity_type);

      if ($this->verbose() && !$quiet) {
        if ($translation_lock) {
          $this->messenger->addStatus($this->t('This content translation is now locked against simultaneous editing. This content translation will remain locked if you navigate away from this page without saving or unlocking it.'));
        }
        else {
          $this->messenger->addStatus($this->t('This content is now locked against simultaneous editing. This content will remain locked if you navigate away from this page without saving or unlocking it.'));
        }
      }
      // Post locking hook.
      $this->moduleHandler->invokeAll('content_lock_locked', [
        $entity_id,
        $langcode,
        $form_op,
        $uid,
        $entity_type,
      ]);

      // Send success flag.
      $return = TRUE;
    }
    else {
      // Currently locking by other user.
      if (is_object($lock) && $lock->uid != $uid) {
        // Send message.
        $message = $this->displayLockOwner($lock, $translation_lock);
        $this->messenger->addWarning($message);

        // Higher permission user can unblock.
        if ($this->currentUser->hasPermission('break content lock')) {

          $link = Link::createFromRoute(
            $this->t('Break lock'),
            'content_lock.break_lock.' . $entity_type,
            [
              'entity' => $entity_id,
              'langcode' => $langcode,
              'form_op' => $form_op,
            ],
            ['query' => ['destination' => isset($destination) ? $destination : $this->currentRequest->getRequestUri()]]
          )->toString();

          // Let user break lock.
          $this->messenger->addWarning($this->t('Click here to @link', ['@link' => $link]));
        }

        // Return FALSE flag.
        $return = FALSE;
      }
      elseif ($lock_service_acquired) {
        // Save locking into database.
        $this->lockingSave($entity_id, $langcode, $form_op, $uid, $entity_type);

        // Locked by current user.
        if ($this->verbose() && !$quiet) {
          if ($translation_lock) {
            $this->messenger->addStatus($this->t('This content translation is now locked by you against simultaneous editing. This content translation will remain locked if you navigate away from this page without saving or unlocking it.'));
          }
          else {
            $this->messenger->addStatus($this->t('This content is now locked by you against simultaneous editing. This content will remain locked if you navigate away from this page without saving or unlocking it.'));
          }
        }

        // Send success flag.
        $return = TRUE;
      }
      else {
        $this->messenger->addWarning('This content is being edited by another user.');
        // Return FALSE flag.
        $return = FALSE;
      }
    }
    if ($lock_service_acquired) {
      $this->lock->release($lock_service_name);
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function isLockable(EntityInterface $entity, $form_op = NULL) {
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    $config = $this->config->get("types.$entity_type");

    $allowed = TRUE;
    $this->moduleHandler->invokeAllWith('content_lock_entity_lockable', function (callable $hook) use (&$allowed, $entity, $config, $form_op) {
      if ($allowed && $hook($entity, $config, $form_op) === FALSE) {
        $allowed = FALSE;
      }
    });

    if ($allowed && is_array($config) && (in_array($bundle, $config) || in_array('*', $config))) {
      if (isset($form_op) && $this->isFormOperationLockEnabled($entity_type)) {
        $mode = $this->config->get("form_op_lock.$entity_type.mode");
        $values = $this->config->get("form_op_lock.$entity_type.values");

        if ($mode == self::FORM_OP_MODE_BLACKLIST) {
          return !in_array($form_op, $values);
        }
        elseif ($mode == self::FORM_OP_MODE_WHITELIST) {
          return in_array($form_op, $values);
        }
      }
      return TRUE;
    }

    // Always return FALSE.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isJsLock($entity_type_id) {
    return in_array($entity_type_id, $this->config->get("types_js_lock") ?: []);
  }

  /**
   * {@inheritdoc}
   */
  public function unlockButton($entity_type, $entity_id, $langcode, $form_op, $destination) {
    $unlock_url_options = [];
    if ($destination) {
      $unlock_url_options['query'] = ['destination' => $destination];
    }
    $route_parameters = [
      'entity' => $entity_id,
      'langcode' => $this->isTranslationLockEnabled($entity_type) ? $langcode : LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'form_op' => $this->isFormOperationLockEnabled($entity_type) ? $form_op : '*',
    ];
    return [
      '#type' => 'link',
      '#title' => $this->t('Unlock'),
      '#access' => TRUE,
      '#attributes' => [
        'class' => ['button'],
      ],
      '#url' => Url::fromRoute('content_lock.break_lock.' . $entity_type, $route_parameters, $unlock_url_options),
      '#weight' => 99,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isTranslationLockEnabled($entity_type_id) {
    return $this->moduleHandler->moduleExists('conflict') && in_array($entity_type_id, $this->config->get("types_translation_lock"));
  }

  /**
   * {@inheritdoc}
   */
  public function hasLockEnabled(string $entity_type_id): bool {
    return !empty($this->config->get("types")[$entity_type_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function isFormOperationLockEnabled($entity_type_id) {
    return $this->config->get("form_op_lock.$entity_type_id.mode") != self::FORM_OP_MODE_DISABLED;
  }

}
