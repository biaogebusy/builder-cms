<?php

namespace Drupal\otp_login\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserDataInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Form builder for the otp_login basic settings form.
 */
class UserPurgeForm extends FormBase {

    /**
     * The entity type manager.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $currentTime;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'otp_login_user_purge_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('user.data'),
      $container->get('datetime.time')
    );
  }

  /**
   * Creates an object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, UserDataInterface $user_data, TimeInterface $time) {
    $this->entityTypeManager = $entity_type_manager;
    $this->userData = $user_data;
    $this->currentTime = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['user_purge'] = [
      '#type' => 'fieldset',
      '#description' => $this->t('Purge blocked / never logged-in users.'),
    ];
    $form['user_purge']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Purge users'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get OTP from database.
    $config = $this->config('otp_login.settings');
    $is_purge_enabled = $config->get('enabled_blocked_users');
    $purge_days = $config->get('user_blocked_value');
    $data = $this->userData->get('otp_login');
    $uids = array_keys($data);
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($uids);
    $current_time = $this->currentTime->getCurrentTime();
    $purge_user = '';
    if ($is_purge_enabled) {
      foreach ($users as $uid => $user) {
        $last_otp_time = $data[$uid]['otp_user_data']['last_otp_time'];
        $timestamp_diff = $current_time - $last_otp_time;
        $days_since_last_otp_generate = round($timestamp_diff / (60 * 60 * 24));
        $user_status = $user->isActive();
        $user_last_accessed = $user->getLastLoginTime();
        if (!$user_status && $days_since_last_otp_generate > $purge_days) {
          $purge_user = $uid;
        }
        if (!$user_status && $user_last_accessed == 0 && $purge_user) {
          user_delete($purge_user);
          $this->messenger()->addMessage($this->t('Blocked / never logged-in users are successfully purged.'));
        }
      }
      if (!$purge_user) {
        $this->messenger()->addError($this->t('No user found to purge.'), 'error');
      }
    }
    else {
      $this->messenger()->addError($this->t('Purge option seems to be disabled.'), 'error');
    }
  }

}
