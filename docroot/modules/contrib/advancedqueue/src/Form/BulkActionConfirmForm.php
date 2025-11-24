<?php

namespace Drupal\advancedqueue\Form;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Plugin\views\field\AdvancedQueueBulkForm;
use Drupal\advancedqueue\QueueAction\QueueActionInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirm form for bulk actions applied to jobs.
 *
 * Inspired by \Drupal\Core\Entity\Form\DeleteMultipleForm.
 *
 * @see \Drupal\advancedqueue\Plugin\views\field\AdvancedQueueBulkForm
 */
class BulkActionConfirmForm extends ConfirmFormBase {

  /**
   * The action to apply to the jobs.
   */
  protected QueueActionInterface $action;

  /**
   * The list of jobs to process. The key is the queue ID.
   *
   * @var array<string, list<string>>
   */
  protected array $selection;

  /**
   * The private temp store.
   */
  protected PrivateTempStore $tempStore;

  public function __construct(protected AccountInterface $currentUser, PrivateTempStoreFactory $temp_store_factory, MessengerInterface $messenger) {
    $this->tempStore = $temp_store_factory->get(AdvancedQueueBulkForm::TEMP_STORE);
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // @phpstan-ignore new.static
    return new static(
      $container->get('current_user'),
      $container->get('tempstore.private'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $action = NULL): array {
    if (!is_string($action)) {
      throw new \LogicException("The action argument must be a string.");
    }
    $this->action = AdvancedQueueBulkForm::getActions()[$action];
    $this->selection = $this->tempStore->get($this->currentUser->id() . ':' . $this->action->id());

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): \Stringable {
    $count = array_reduce($this->selection, fn($carry, $item) => $carry + count($item), 0);
    return $this->action->confirmFormQuestion($count);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    // This will be overridden by the destination query parameter.
    return new Url('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'advancedqueue_bulk_action_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $processed_count = $unprocessed_count = 0;
    foreach ($this->selection as $queue_id => $jobs) {
      $queue_backend = Queue::load($queue_id)->getBackend();
      foreach ($jobs as $job_id) {
        if ($this->action->execute($queue_backend, $job_id)) {
          $processed_count++;
        }
        else {
          $unprocessed_count++;
        }
      }
    }
    $this->tempStore->delete($this->currentUser->id() . ':' . $this->action->id());

    if ($processed_count) {
      $this->messenger->addStatus($this->action->processedMessage($processed_count));
    }

    if ($unprocessed_count) {
      $this->messenger->addStatus($this->formatPlural($unprocessed_count, 'Failed to process ' . $unprocessed_count . ' job.', 'Fail to process ' . $unprocessed_count . ' jobs.'));
    }
  }

}
