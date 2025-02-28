<?php

namespace Drupal\state_machine_test\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WorkflowTransitionEventSubscriber implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new WorkflowTransitionEventSubscriber object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'entity_test_with_bundle.create.pre_transition' => 'onPreTransition',
      'entity_test_with_bundle.create.post_transition' => 'onPostTransition',
      'entity_test_with_bundle.pre_transition' => 'onGroupPreTransition',
      'entity_test_with_bundle.post_transition' => 'onGroupPostTransition',
      'state_machine.pre_transition' => 'onGenericPreTransition',
      'state_machine.post_transition' => 'onGenericPostTransition',
    ];
    return $events;
  }

  /**
   * Reacts to the 'entity_test_with_bundle.create.pre_transition' event.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onPreTransition(WorkflowTransitionEvent $event) {
    $this->setMessage($event, 'pre-transition');
  }

  /**
   * Reacts to the 'entity_test_with_bundle.create.post_transition' event.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onPostTransition(WorkflowTransitionEvent $event) {
    $this->setMessage($event, 'post-transition');
  }

  /**
   * Reacts to the 'entity_test_with_bundle.pre_transition' event.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onGroupPreTransition(WorkflowTransitionEvent $event) {
    $this->setMessage($event, 'group pre-transition');
  }

  /**
   * Reacts to the 'entity_test_with_bundle.post_transition' event.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onGroupPostTransition(WorkflowTransitionEvent $event) {
    $this->setMessage($event, 'group post-transition');
  }

  /**
   * Reacts to the 'state_machine.pre_transition' event.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onGenericPreTransition(WorkflowTransitionEvent $event) {
    $this->setMessage($event, 'generic pre-transition');
  }

  /**
   * Reacts to the 'state_machine.post_transition' event.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onGenericPostTransition(WorkflowTransitionEvent $event) {
    $this->setMessage($event, 'generic post-transition');
  }

  /**
   * Sets a message with event information for test purposes.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   * @param string $phase
   *   The phase during which the event occurred.
   */
  protected function setMessage(WorkflowTransitionEvent $event, $phase) {
    $this->messenger->addMessage(new TranslatableMarkup('@entity_label (@field_name) - @state_label at @phase (workflow: @workflow, transition: @transition).', [
      '@entity_label' => (string) $event->getEntity()->label(),
      '@field_name' => $event->getFieldName(),
      '@state_label' => $event->getTransition()->getToState()->getLabel(),
      '@workflow' => $event->getWorkflow()->getId(),
      '@transition' => $event->getTransition()->getId(),
      '@phase' => $phase,
    ]));
  }

}
