<?php

namespace Drupal\workspace\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UnblockReplicationForm.
 *
 * @package Drupal\replication\Form
 */
class UnblockReplicationForm extends FormBase {

  /**
   * Stores the state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * UnblockReplicationForm constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key value store.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('state'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'unblock_replication_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $last_replication_failed = $this->state->get('workspace.last_replication_failed', FALSE);
    $form['unblock'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Unblock replication'),
    );
    $form['unblock']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Unblock replication'),
      '#disabled' => !$last_replication_failed,
    ];
    $form['unblock']['description'] = [
      '#type' => 'markup',
      '#markup' => '<div class="description">' . $this->t('Replications will be blocked after any kind of failure. This action will unblock replications so that they can run again (but make sure to resolve the underlying issue first).') . '</div>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->state->set('workspace.last_replication_failed', FALSE);
    $this->messenger()->addMessage($this->t('Replications have been unblocked.'));
  }

}
