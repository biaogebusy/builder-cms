<?php

namespace Drupal\private_message\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the admin uninstall form for the Private Message module.
 */
class AdminUninstallForm extends ConfirmFormBase {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new AdminUninstallForm object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'private_message_admin_uninstall_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete all private message content from the system?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('private_message.admin_config');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('The private message module cannot be uninstalled if there is private message content in the database. Clicking the button below will delete all private message content from the system, allowing the module to be uninstalled.') . '<br><strong>' . $this->t('THIS ACTION CANNOT BE REVERSED') . '</strong>';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete all private message content');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'private_message/uninstall_page';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = [
      'title' => $this->t('Deleting private message data'),
      'operations' => [
        [[__CLASS__, 'deletePrivateMessageMessages'], []],
        [[__CLASS__, 'deletePrivateMessageThreads'], []],
      ],
      'progress_message' => static::t('Deleting private message data. Completed: @percentage% (@current of @total).'),
    ];
    batch_set($batch);

    $this->messenger->addMessage($this->t('Private message data has been deleted.'));
  }

  /**
   * Batch callback to delete private messages.
   */
  public static function deletePrivateMessageMessages(&$context) {
    $private_message_ids = \Drupal::entityQuery('private_message')->accessCheck(FALSE)->range(0, 100)->execute();
    $storage = \Drupal::entityTypeManager()->getStorage('private_message');
    if ($private_messages = $storage->loadMultiple($private_message_ids)) {
      $storage->delete($private_messages);
    }
    $context['finished'] = (int) count($private_messages) < 100;
  }

  /**
   * Batch callback to delete private message threads.
   */
  public static function deletePrivateMessageThreads(&$context) {
    $private_message_thread_ids = \Drupal::entityQuery('private_message_thread')->accessCheck(FALSE)->range(0, 100)->execute();
    $storage = \Drupal::entityTypeManager()->getStorage('private_message_thread');
    if ($private_message_threads = $storage->loadMultiple($private_message_thread_ids)) {
      $storage->delete($private_message_threads);
    }
    $context['finished'] = (int) count($private_message_threads) < 100;
  }

}
