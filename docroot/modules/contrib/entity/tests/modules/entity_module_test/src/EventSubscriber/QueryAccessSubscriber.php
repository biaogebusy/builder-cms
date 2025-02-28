<?php

namespace Drupal\entity_module_test\EventSubscriber;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\entity\QueryAccess\QueryAccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class QueryAccessSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'entity.query_access' => 'onGenericQueryAccess',
      'entity.query_access.entity_test_enhanced' => 'onQueryAccess',
      'entity.query_access.node' => 'onEventOnlyQueryAccess',
    ];
  }

  /**
   * Modifies the access conditions based on the entity type.
   *
   * This is just a convenient example for testing the catch-all event. A real
   * subscriber would probably extend the conditions based on the third party
   * settings it set on the entity type(s).
   *
   * @param \Drupal\entity\QueryAccess\QueryAccessEvent $event
   *   The event.
   */
  public function onGenericQueryAccess(QueryAccessEvent $event) {
    $conditions = $event->getConditions();
    $email = $event->getAccount()->getEmail();
    if ($event->getEntityTypeId() == 'entity_test_enhanced_with_owner') {
      // Disallow access to entity_test_enhanced_with_owner for the user with
      // email address user9000@example.com. Anyone else has access.
      if ($email == 'user9000@example.com') {
        $conditions->alwaysFalse();
      }
      elseif ($email == 'user9001@example.com') {
        $conditions->alwaysFalse(FALSE);
      }
    }
  }

  /**
   * Modifies the access conditions based on the current user.
   *
   * This is just a convenient example for testing. A real subscriber would
   * ignore the account and extend the conditions to cover additional factors,
   * such as a custom entity field.
   *
   * @param \Drupal\entity\QueryAccess\QueryAccessEvent $event
   *   The event.
   */
  public function onQueryAccess(QueryAccessEvent $event) {
    $conditions = $event->getConditions();
    $email = $event->getAccount()->getEmail();

    if ($email == 'user1@example.com') {
      // This user should not have access to any entities.
      $conditions->alwaysFalse();
    }
    elseif ($email == 'user2@example.com') {
      // This user should have access to entities with the IDs 1, 2, and 3.
      // The query access handler might have already set ->alwaysFalse()
      // due to the user not having any other access, so we make sure
      // to undo it with $conditions->alwaysFalse(TRUE).
      $conditions->alwaysFalse(FALSE);
      $conditions->addCondition('id', ['1', '2', '3']);
    }
    elseif ($email == 'user3@example.com') {
      // This user should only have access to entities assigned to "marketing",
      // or unassigned entities.
      $conditions->alwaysFalse(FALSE);
      $conditions->addCondition((new ConditionGroup('OR'))
        ->addCondition('assigned', NULL, 'IS NULL')
        // Confirm that explicitly specifying the property name works.
        ->addCondition('assigned.value', 'marketing')
      );
    }
  }

  /**
   * Modifies the access conditions based on the node type.
   *
   * This is just a convenient example for testing whether the event-only query
   * access subscriber is added to entity types that do not specify a query
   * access handler; in this case: node.
   *
   * @param \Drupal\entity\QueryAccess\QueryAccessEvent $event
   *   The event.
   */
  public function onEventOnlyQueryAccess(QueryAccessEvent $event) {
    if (\Drupal::state()->get('test_event_only_query_access')) {
      $conditions = $event->getConditions();
      $conditions->addCondition('type', 'foo');

      $cacheability = \Drupal::state()->get('event_only_query_acccess_cacheability');
      if ($cacheability instanceof CacheableDependencyInterface) {
        $conditions->addCacheableDependency($cacheability);
      }
    }
  }

}
