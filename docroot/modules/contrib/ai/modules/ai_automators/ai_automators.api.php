<?php

/**
 * @file
 * Hooks and event documentation for AI Automators.
 */

/**
 * @addtogroup events
 * @{
 */

/**
 * Subscribe to the should-process decision for automator fields.
 *
 * This event is dispatched after checkIfEmpty() (and optional
 * postCheckIfEmpty()) has normalized the field value and produced the initial
 * process/skip decision.
 *
 * Event name: ai_automator.should_process_field
 *
 * @code
 * namespace Drupal\my_module\EventSubscriber;
 *
 * use Drupal\ai_automators\Event\ShouldProcessFieldEvent;
 * use Symfony\Component\EventDispatcher\EventSubscriberInterface;
 *
 * final class MyAutomatorSubscriber implements EventSubscriberInterface {
 *
 *   public static function getSubscribedEvents(): array {
 *     return [
 *       ShouldProcessFieldEvent::EVENT_NAME => 'onShouldProcessField',
 *     ];
 *   }
 *
 *   public function onShouldProcessField(ShouldProcessFieldEvent $event): void {
 *     if ($event->getEntity()->bundle() === 'article') {
 *       $event->setShouldProcess(TRUE);
 *     }
 *   }
 *
 * }
 * @endcode
 *
 * @see \Drupal\ai_automators\Event\ShouldProcessFieldEvent
 * @see \Drupal\ai_automators\PluginInterfaces\AiAutomatorPostCheckIfEmptyInterface
 */

/**
 * @} End of "addtogroup events".
 */
