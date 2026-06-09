<?php

namespace Drupal\ai_automators\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Should the rule be visible to use at all.
 */
class RuleIsAllowedEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai_automator.rule_is_allowed';

  // The rule should be forced hidden.
  const RULE_FORCE_HIDDEN = 'force_hidden';

  // The rule should be forced visible.
  const RULE_FORCE_VISIBLE = 'force_visible';

  // Neutral, let the system decide.
  const RULE_NEUTRAL = 'neutral';

  /**
   * The entity to process.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  public $entity;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  public $fieldDefinition;

  /**
   * The actions to take.
   *
   * @var array
   */
  public $actions = [];

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to process.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   */
  public function __construct(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    $this->entity = $entity;
    $this->fieldDefinition = $fieldDefinition;
  }

  /**
   * Force the field to be processed.
   */
  public function setRuleVisible() {
    $this->actions[] = self::RULE_FORCE_VISIBLE;
  }

  /**
   * Force the field to be hidden.
   */
  public function setRuleHidden() {
    $this->actions[] = self::RULE_FORCE_HIDDEN;
  }

  /**
   * Neutral, let the system decide.
   */
  public function setRuleNeutral() {
    $this->actions[] = self::RULE_NEUTRAL;
  }

}
